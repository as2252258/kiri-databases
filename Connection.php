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
use Kiri\Events\EventProvider;
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

	public array $pool;


	private PoolConnection $connection;

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
	 * @param Kiri\Di\ContainerInterface $container
	 * @param array $config
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws Exception
	 */
	public function __construct(public EventProvider $eventProvider, public ContainerInterface $container, array $config = [])
	{
		parent::__construct($config);

		$this->connection = $this->container->get(PoolConnection::class);
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

		$this->connectPoolInstance();
	}


	/**
	 * @throws Exception
	 */
	public function connectPoolInstance()
	{
		if (!empty($this->slaveConfig) && isset($this->slaveConfig['cds'])) {
			$pool = $this->pool ?? ['max' => 10, 'min' => 1];

			$this->connection->initConnections($this->slaveConfig['cds'] . 'slave', $pool['max']);
		} else {
			$pool = $this->slaveConfig['pool'] ?? ['max' => 10, 'min' => 1];

			$this->connection->initConnections($this->cds . 'master', $pool['max']);
		}
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
	public function getMasterClient(): PDO
	{
		return $this->connection->get([
			'cds'             => $this->cds,
			'username'        => $this->username,
			'password'        => $this->password,
			'attributes'      => $this->attributes,
			'connect_timeout' => $this->connect_timeout,
			'read_timeout'    => $this->read_timeout,
			'dbname'          => $this->database,
			'pool'            => $this->pool
		], true);
	}

	/**
	 * @return PDO
	 * @throws Exception
	 */
	public function getSlaveClient(): PDO
	{
		return $this->connection->get([
			'cds'             => $this->slaveConfig['cds'] ?? $this->cds,
			'username'        => $this->slaveConfig['username'] ?? $this->username,
			'password'        => $this->slaveConfig['password'] ?? $this->password,
			'attributes'      => $this->slaveConfig['attributes'] ?? $this->attributes,
			'connect_timeout' => $this->connect_timeout,
			'read_timeout'    => $this->read_timeout,
			'dbname'          => $this->slaveConfig['database'] ?? $this->database,
			'pool'            => $this->pool
		], false);
	}

	/**
	 * @return $this
	 * @throws Exception
	 */
	public function beginTransaction(): static
	{
		$pdo = $this->getPdo();
		$pdo->beginTransaction();
		return $this;
	}


	/**
	 * @return PDO
	 * @throws Exception
	 */
	public function getPdo(): PDO
	{
		return $this->getMasterClient();
	}

	/**
	 * @return $this|bool
	 * @throws Exception
	 */
	public function inTransaction(): bool|static
	{
		return $this->getPdo()->inTransaction();
	}

	/**
	 * @throws Exception
	 * 事务回滚
	 */
	public function rollback()
	{
		$pdo = $this->getPdo();
		if ($pdo->inTransaction()) {
			$pdo->rollback();
		}
		$this->release($pdo, true);
	}

	/**
	 * @throws Exception
	 * 事务提交
	 */
	public function commit()
	{
		$pdo = $this->getPdo();
		if ($pdo->inTransaction()) {
			$pdo->commit();
		}
		$this->release($pdo, true);
	}


	/**
	 * @param null $sql
	 * @param array $attributes
	 * @return Command
	 * @throws Exception
	 */
	public function createCommand($sql = null, array $attributes = []): Command
	{
		$command = new Command(['db' => $this, 'sql' => $sql]);
		return $command->bindValues($attributes);
	}


	/**
	 *
	 * 回收链接
	 * @throws
	 */
	public function release(PDO $pdo, bool $isMaster)
	{
		if (!Context::inCoroutine()) {
			return;
		}
		$connections = $this->connection;
		if (!$isMaster) {
			$connections->addItem(($this->slaveConfig['cds'] ?? $this->cds) . 'slave', $pdo);
		} else {
			if (!$pdo->inTransaction()) {
				$connections->addItem($this->cds . 'master', $pdo);
			}
		}
	}


	/**
	 *
	 * 回收链接
	 * @throws
	 */
	public function clear_connection()
	{
		$cds = $this->slaveConfig['cds'] ?? $this->cds;

		$this->connection->connection_clear($cds . 'master');
		$this->connection->connection_clear($cds . 'slave');
	}


	/**
	 * @throws Exception
	 */
	public function disconnect()
	{
		$cds = $this->slaveConfig['cds'] ?? $this->cds;

		$this->connection->connection_clear($cds . 'master');
		$this->connection->connection_clear($cds . 'slave');
	}

}
