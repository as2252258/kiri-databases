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
use Database\Mysql\PDO;
use Database\Mysql\Schema;
use Exception;
use Kiri;
use Kiri\Abstracts\Component;
use Kiri\Abstracts\Config;
use Kiri\Events\EventProvider;
use Kiri\Exception\NotFindClassException;
use Kiri\Server\Events\OnWorkerExit;
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
	 * @param array $config
	 * @throws Exception
	 */
	public function __construct(public EventProvider $eventProvider, array $config = [])
	{
		parent::__construct($config);
	}


	/**
	 * @return void
	 * @throws Exception
	 */
	public function init()
	{
		$this->eventProvider->on(OnWorkerExit::class, [$this, 'clear_connection'], 0);
		$this->eventProvider->on(BeginTransaction::class, [$this, 'beginTransaction'], 0);
		$this->eventProvider->on(Rollback::class, [$this, 'rollback'], 0);
		$this->eventProvider->on(Commit::class, [$this, 'commit'], 0);

		$this->connectPoolInstance();
	}


	/**
	 * @param $isSearch
	 * @return PDO
	 * @throws Exception
	 */
	public function getConnect($isSearch): PDO
	{
		return !$isSearch ? $this->getMasterClient() : $this->getSlaveClient();
	}


	/**
	 * @throws Exception
	 */
	public function connectPoolInstance()
	{
		$connections = $this->connections();
		$pool = Config::get('databases.pool.max', 10);
		if (!empty($this->slaveConfig) && $this->cds != $this->slaveConfig['cds']) {
			$connections->initConnections('Mysql:' . $this->slaveConfig['cds'], $pool);
		} else {
			$connections->initConnections('Mysql:' . $this->cds, $pool);
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
		if ($this->_pdo) {
			return $this->_pdo;
		}
		return $this->connections()->get([
			'cds'             => $this->cds,
			'username'        => $this->username,
			'password'        => $this->password,
			'attributes'      => $this->attributes,
			'connect_timeout' => $this->connect_timeout,
			'read_timeout'    => $this->read_timeout,
			'dbname'          => $this->database,
			'pool'            => $this->pool
		]);
	}

	/**
	 * @return PDO
	 * @throws Exception
	 */
	public function getSlaveClient(): PDO
	{
		if (empty($this->slaveConfig) || $this->slaveConfig['cds'] == $this->cds) {
			return $this->getMasterClient();
		}
		return $this->connections()->get($this->slaveConfig);
	}


	/**
	 * @return \Kiri\Pool\Connection
	 * @throws Exception
	 */
	private function connections(): \Kiri\Pool\Connection
	{
		return Kiri::getDi()->get(\Kiri\Pool\Connection::class);
	}


	/**
	 * @return $this
	 * @throws Exception
	 */
	public function beginTransaction(): static
	{
		$pdo = $this->getMasterClient();
		$pdo->beginTransaction();
		return $this;
	}


	/**
	 * @return PDO
	 * @throws Exception
	 */
	public function getPdo(): PDO
	{
		if (!$this->_pdo) {
			$this->_pdo = $this->getMasterClient();
		}
		return $this->_pdo;
	}

	/**
	 * @return $this|bool
	 * @throws Exception
	 */
	public function inTransaction(): bool|static
	{
		if (!$this->_pdo) {
			$this->_pdo = $this->getMasterClient();
		}
		return $this->_pdo->inTransaction();
	}

	/**
	 * @throws Exception
	 * 事务回滚
	 */
	public function rollback()
	{
		if ($this->_pdo->inTransaction()) {
			$this->_pdo->rollback();
		}
		$this->release($this->_pdo);
		$this->_pdo = null;
	}

	/**
	 * @throws Exception
	 * 事务提交
	 */
	public function commit()
	{
		if ($this->_pdo->inTransaction()) {
			$this->_pdo->commit();
		}
		$this->release($this->_pdo);
		$this->_pdo = null;
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
	public function release(PDO $pdo)
	{
		$connections = $this->connections();
		if (!$pdo->inTransaction()) {
			$cds = $this->cds;
			if (isset($this->slaveConfig['cds'])) {
				$cds = $this->slaveConfig['cds'];
			}
			$connections->addItem($cds, $pdo);
		}
	}


	/**
	 *
	 * 回收链接
	 * @throws
	 */
	public function clear_connection()
	{
		$connections = $this->connections();

		$connections->connection_clear($this->cds);

		if (!isset($this->slaveConfig['cds'])) {
			$this->slaveConfig['cds'] = $this->cds;
		}

		$connections->connection_clear($this->slaveConfig['cds']);
	}


	/**
	 * @throws Exception
	 */
	public function disconnect()
	{
		$connections = $this->connections();
		$connections->disconnect($this->cds);

		if (!isset($this->slaveConfig['cds'])) {
			$this->slaveConfig['cds'] = $this->cds;
		}

		$connections->disconnect($this->slaveConfig['cds']);
	}

}
