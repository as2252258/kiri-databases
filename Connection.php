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
use Kiri\Di\Context;
use Kiri\Pool\Pool;
use Kiri\Events\EventProvider;
use Kiri\Exception\NotFindClassException;
use PDO;
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
	
	
	private int $storey = 0;
	
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
	 * @return void
	 * @throws Exception
	 */
	public function init(): void
	{

		$eventProvider = Kiri::getDi()->get(EventProvider::class);
		$eventProvider->on(BeginTransaction::class, [$this, 'beginTransaction'], 0);
		$eventProvider->on(Rollback::class, [$this, 'rollback'], 0);
		$eventProvider->on(Commit::class, [$this, 'commit'], 0);
		
		$this->initConnections();
	}


	/**
	 * @return void
	 * @throws ReflectionException
	 */
	public function initConnections(): void
	{
		$connections = Kiri::getDi()->get(Pool::class);
		$connections->initConnections($this->cds, $this->pool['max'] ?? 1, $this->gender([
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
		$connections = Kiri::getDi()->get(Pool::class);
		return $connections->get($this->cds);
	}
	
	
	/**
	 * @return $this
	 * @throws Exception
	 */
	public function beginTransaction(): static
	{
		$pdo = $this->getTransactionClient();
		if ($this->storey == 0) {
			$pdo->beginTransaction();
		}
		$this->storey++;
		return $this;
	}
	
	
	/**
	 * @return PDO
	 * @throws Exception
	 */
	public function getTransactionClient(): PDO
	{
		if (!Db::inTransactionsActive()) {
			return $this->getConnection();
		}
		$pdo = Context::get($this->cds);
		if ($pdo === null) {
			/** @var PDO $pdo */
			$pdo = Context::set($this->cds, $this->getConnection());
		}
		return $pdo;
	}
	
	/**
	 * @return $this|bool
	 * @throws Exception
	 */
	public function inTransaction(): bool|static
	{
		$pdo = $this->getTransactionClient();
		return $pdo->inTransaction();
	}
	
	/**
	 * @throws Exception
	 * 事务回滚
	 */
	public function rollback()
	{
		$this->storey--;
		if ($this->storey == 0) {
			$pdo = $this->getTransactionClient();
			if ($pdo->inTransaction()) {
				$pdo->rollback();
			}
			$connections = Kiri::getDi()->get(Pool::class);
			$connections->push($this->cds, $pdo);
			Context::remove($this->cds);
		}
	}
	
	/**
	 * @throws Exception
	 * 事务提交
	 */
	public function commit()
	{
		$this->storey--;
		if ($this->storey == 0) {
			$pdo = $this->getTransactionClient();
			if ($pdo->inTransaction()) {
				$pdo->commit();
			}
			$connections = Kiri::getDi()->get(Pool::class);
			$connections->push($this->cds, $pdo);
			Context::remove($this->cds);
		}
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
		$connections = Kiri::getDi()->get(Pool::class);
		$connections->push($this->cds, $PDO);
	}
	
	
	/**
	 *
	 * 回收链接
	 * @throws
	 */
	public function clear_connection()
	{
		$connections = Kiri::getDi()->get(Pool::class);
		$connections->clean($this->cds);
	}
	
	
	/**
	 * @throws Exception
	 */
	public function disconnect()
	{
		$connections = Kiri::getDi()->get(Pool::class);
		$connections->clean($this->cds);
	}
	
}
