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
		$this->connection->initConnections([
			'cds'             => $this->cds,
			'username'        => $this->username,
			'password'        => $this->password,
			'attributes'      => $this->attributes,
			'connect_timeout' => $this->connect_timeout,
			'read_timeout'    => $this->read_timeout,
			'dbname'          => $this->database,
			'pool'            => $this->pool
		], $this->pool['max']);
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
	 * @return Mysql\PDO
	 * @throws Exception
	 */
	public function getMasterClient(): Mysql\PDO
	{
		$client = $this->connection->get($this->cds);
		if ($client === false) {
			throw new Exception('waite db client timeout.');
		}
		return $client;
	}

	/**
	 * @return Mysql\PDO
	 * @throws Exception
	 */
	public function getSlaveClient(): Mysql\PDO
	{
		$client = $this->connection->get($this->cds);
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
		$pdo = $this->getPdo();
		$pdo->beginTransaction();
		return $this;
	}


	/**
	 * @param bool $restore
	 * @return Mysql\PDO
	 * @throws ConfigException
	 */
	public function getPdo(bool $restore = false): Mysql\PDO
	{
		if ($restore === true) {
			return Context::set($this->cds, $this->getMasterClient());
		}
		if (!Context::exists($this->cds)) {
			return Context::set($this->cds, $this->getMasterClient());
		} else {
			return Context::get($this->cds);
		}
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
		$this->release($pdo);
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
		$command = new Command(['db' => $this, 'sql' => $sql]);
		return $command->bindValues($attributes);
	}


	/**
	 *
	 * 回收链接
	 * @throws
	 */
	public function release(Mysql\PDO $PDO)
	{
		if ($PDO->inTransaction() === false) {
			$this->connection->addItem($this->cds, $PDO);
			Context::remove($this->cds);
		}
	}


	/**
	 * @param bool $isMaster
	 * @return string
	 */
	private function alias(bool $isMaster): string
	{
		return !$isMaster ? ($this->slaveConfig['cds'] ?? $this->cds) . 'slave' : $this->cds . 'master';
	}


	/**
	 *
	 * 回收链接
	 * @throws
	 */
	public function clear_connection()
	{
		$this->connection->connection_clear($this->alias(true));
		$this->connection->connection_clear($this->alias(false));
	}


	/**
	 * @throws Exception
	 */
	public function disconnect()
	{
		$this->connection->connection_clear($this->alias(true));
		$this->connection->connection_clear($this->alias(false));
	}

}
