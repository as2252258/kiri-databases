<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/3/30 0030
 * Time: 14:09
 */
declare(strict_types=1);


namespace Database;


use Database\Affair\BeginTransaction;
use Database\Affair\Commit;
use Database\Affair\Rollback;
use Database\Mysql\Schema;
use Exception;
use Kiri;
use Kiri\Abstracts\Component;
use Kiri\Abstracts\Config;
use Kiri\Di\ContainerInterface;
use Kiri\Di\Context;
use Kiri\Annotation\Inject;
use Kiri\Pool\Pool;
use Kiri\Events\EventProvider;
use Kiri\Exception\ConfigException;
use Kiri\Exception\NotFindClassException;
use Kiri\Pool\Connection as PoolConnection;
use Kiri\Server\Events\OnWorkerExit;
use PDO;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;

/**
 * Class Connection
 * @package Database
 */
class Connection extends Component
{
	
	public string $id = 'db';
	public string $cds = '';
	public string $password = '';
	public string $username = '';
	public string $charset = 'utf-8';
	
	public string $tablePrefix = '';
	
	public string $database = '';
	
	public int $connect_timeout = 30;
	
	public int $read_timeout = 10;
	
	public array $pool = ['max' => 10, 'min' => 1];
	
	/**
	 * @var bool
	 * enable database cache
	 */
	public bool $enableCache = false;
	
	
	private ?PDO $_pdo = null;
	
	
	/**
	 * @var string
	 */
	public string $cacheDriver = 'redis';
	
	/**
	 * @var array
	 */
	public array $slaveConfig = [];
	public array $attributes = [];
	
	
	private ?Schema $_schema = null;
	
	
	/**
	 * @param EventProvider $eventProvider
	 * @param Pool $connections
	 * @param ContainerInterface $container
	 * @param array $config
	 * @throws Exception
	 */
	public function __construct(public EventProvider $eventProvider, public Pool $connections, public ContainerInterface $container, array $config = [])
	{
		parent::__construct($config);
	}
	
	
	/**
	 * @return void
	 * @throws Exception
	 */
	public function init(): void
	{
		$this->eventProvider->on(OnWorkerExit::class, [$this, 'clear_connection'], 9999);
		$this->eventProvider->on(BeginTransaction::class, [$this, 'beginTransaction'], 0);
		$this->eventProvider->on(Rollback::class, [$this, 'rollback'], 0);
		$this->eventProvider->on(Commit::class, [$this, 'commit'], 0);
		
		$this->initConnections();
	}
	
	
	/**
	 * @return void
	 */
	public function initConnections(): void
	{
		$this->connections->initConnections($this->cds, $this->pool['max'] ?? 1, $this->gender([
			'cds'             => $this->cds,
			'username'        => $this->username,
			'password'        => $this->password,
			'attributes'      => $this->attributes,
			'connect_timeout' => $this->connect_timeout,
			'read_timeout'    => $this->read_timeout,
			'dbname'          => $this->database,
			'pool'            => $this->pool
		]));
	}
	
	
	/**
	 * @param array $config
	 * @return \Closure
	 */
	public function gender(array $config): \Closure
	{
		return static function () use ($config) {
			$options = [
				PDO::ATTR_CASE               => PDO::CASE_NATURAL,
				PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_ORACLE_NULLS       => PDO::NULL_NATURAL,
				PDO::ATTR_STRINGIFY_FETCHES  => false,
				PDO::ATTR_EMULATE_PREPARES   => false,
				PDO::ATTR_TIMEOUT            => $config['connect_timeout'],
				PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . ($config['charset'] ?? 'utf8mb4')
			];
			if (!Context::inCoroutine()) {
				$options[PDO::ATTR_PERSISTENT] = true;
			}
			$link = new PDO('mysql:dbname=' . $config['dbname'] . ';host=' . $config['cds'],
				$config['username'], $config['password'], $options);
			foreach ($config['attributes'] as $key => $attribute) {
				$link->setAttribute($key, $attribute);
			}
			return $link;
		};
	}
	
	
	/**
	 * @return mixed
	 * @throws ReflectionException
	 * @throws NotFindClassException
	 * @throws Exception
	 */
	public function getSchema(): Schema
	{
		if ($this->_schema === null) {
			$this->_schema = Kiri::createObject([
				'class' => Schema::class,
				'db'    => $this
			]);
		}
		return $this->_schema;
	}
	
	
	/**
	 * @return PDO
	 * @throws Exception
	 */
	public function getConnection(): PDO
	{
		$client = $this->connections->get($this->cds);
		if ($client === false) {
			throw new Exception('waite db client timeout.');
		}
		return $client;
	}
	
	/**
	 * @return PDO
	 * @throws Exception
	 */
	public function getSlaveClient(): PDO
	{
		$client = $this->connections->get($this->cds);
		if ($client === false) {
			throw new Exception('waite db client timeout.');
		}
		return $client;
	}
	
	/**
	 * @return $this
	 * @throws Exception
	 */
	public function beginTransaction(): static
	{
		$pdo = Context::get($this->cds);
		if ($pdo === null) {
			$pdo = $this->getConnection();
		}
		$pdo->beginTransaction();
		return $this;
	}
	
	/**
	 * @return $this|bool
	 * @throws Exception
	 */
	public function inTransaction(): bool|static
	{
		return Context::get($this->cds)->inTransaction();
	}
	
	/**
	 * @throws Exception
	 * 事务回滚
	 */
	public function rollback()
	{
		$pdo = Context::get($this->cds);
		if ($pdo->inTransaction()) {
			$pdo->rollback();
		}
		$this->release($pdo);
	}
	
	/**
	 * @throws Exception
	 * 事务提交
	 */
	public function commit()
	{
		$pdo = Context::get($this->cds);
		if ($pdo->inTransaction()) {
			$pdo->commit();
		}
		$this->release($pdo);
	}
	
	
	/**
	 * @param null $sql
	 * @param array $attributes
	 * @return Command
	 * @throws Exception
	 */
	public function createCommand($sql = null, array $attributes = []): Command
	{
		$command = new Command(['connection' => $this, 'sql' => $sql]);
		return $command->bindValues($attributes);
	}
	
	
	/**
	 *
	 * 回收链接
	 * @throws
	 */
	public function release(PDO $PDO)
	{
		if ($PDO->inTransaction() === false) {
			$this->connections->push($this->cds, $PDO);
		}
	}
	
	
	/**
	 *
	 * 回收链接
	 * @throws
	 */
	public function clear_connection()
	{
		$this->connections->clean($this->cds);
	}
	
	
	/**
	 * @throws Exception
	 */
	public function disconnect()
	{
		$this->connections->clean($this->cds);
	}
	
}
