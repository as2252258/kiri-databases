<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/3/30 0030
 * Time: 14:09
 */
declare(strict_types=1);


namespace Database;


use Annotation\Inject;
use Database\Mysql\Schema;
use Exception;
use JetBrains\PhpStorm\Pure;
use Database\Mysql\PDO;
use ReflectionException;
use Server\Events\OnWorkerExit;
use Server\Events\OnWorkerStop;
use Kiri\Abstracts\Component;
use Kiri\Abstracts\Config;
use Kiri\Events\EventProvider;
use Kiri\Exception\NotFindClassException;
use Kiri\Kiri;
use Database\Affair\BeginTransaction;
use Database\Affair\Commit;
use Database\Affair\Rollback;

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

	public int $timeout = 1900;

	/**
	 * @var bool
	 * enable database cache
	 */
	public bool $enableCache = false;


	/**
	 * @var string
	 */
	public string $cacheDriver = 'redis';

	/**
	 * @var array
	 */
	public array $slaveConfig = [];


	/**
	 * @var Schema
	 */
	#[Inject(Schema::class)]
	public Schema $_schema;


	/**
	 * @var EventProvider
	 */
	#[Inject(EventProvider::class)]
	public EventProvider $eventProvider;


	/**
	 * execute by __construct
	 * @throws Exception
	 */
	public function init()
	{
		$this->eventProvider->on(OnWorkerStop::class, [$this, 'clear_connection'], 0);
		$this->eventProvider->on(OnWorkerExit::class, [$this, 'clear_connection'], 0);
		$this->eventProvider->on(BeginTransaction::class, [$this, 'beginTransaction'], 0);
		$this->eventProvider->on(Rollback::class, [$this, 'rollback'], 0);
		$this->eventProvider->on(Commit::class, [$this, 'commit'], 0);

		if (Db::transactionsActive()) {
		    $this->beginTransaction();
        }

		$this->_schema->db = $this;
	}


	/**
	 * @param null $sql
	 * @return PDO
	 * @throws Exception
	 */
	public function getConnect($sql = NULL): PDO
	{
		return $this->getPdo($sql);
	}


	/**
	 * @throws Exception
	 */
	public function fill()
	{
		$connections = $this->connections();
		$pool = Config::get('databases.pool.max', 10);

		$connections->initConnections('Mysql:' . $this->cds, true, $pool);
		if (!empty($this->slaveConfig) && $this->cds != $this->slaveConfig['cds']) {
			$connections->initConnections('Mysql:' . $this->slaveConfig['cds'], false, $pool);
		}
	}


	/**
	 * @param $sql
	 * @return PDO
	 * @throws Exception
	 */
	private function getPdo($sql): PDO
	{
		if ($this->isWrite($sql)) {
			return $this->masterInstance();
		} else {
			return $this->slaveInstance();
		}
	}

	/**
	 * @return mixed
	 * @throws ReflectionException
	 * @throws NotFindClassException
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
	 * @param $sql
	 * @return bool
	 */
	#[Pure] public function isWrite($sql): bool
	{
		if (empty($sql)) return false;
		if (str_starts_with(strtolower($sql), 'select')) {
			return false;
		}
		return true;
	}

	/**
	 * @return mixed
	 * @throws Exception
	 */
	public function getCacheDriver(): mixed
	{
		if (!$this->enableCache) {
			return null;
		}
		return Kiri::app()->get($this->cacheDriver);
	}

	/**
	 * @return PDO
	 * @throws Exception
	 */
	public function masterInstance(): PDO
	{
		return $this->connections()->get([
			'cds'      => $this->cds,
			'username' => $this->username,
			'password' => $this->password,
			'database' => $this->database
		], true);
	}

	/**
	 * @return PDO
	 * @throws Exception
	 */
	public function slaveInstance(): PDO
	{
		if (empty($this->slaveConfig) || Db::transactionsActive()) {
			return $this->masterInstance();
		}
		if ($this->slaveConfig['cds'] == $this->cds) {
			return $this->masterInstance();
		}
		return $this->connections()->get($this->slaveConfig, false);
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
		$this->connections()->beginTransaction($this->cds);
		return $this;
	}

	/**
	 * @return $this|bool
	 * @throws Exception
	 */
	public function inTransaction(): bool|static
	{
		return $this->connections()->inTransaction($this->cds);
	}

	/**
	 * @throws Exception
	 * 事务回滚
	 */
	public function rollback()
	{
		$this->connections()->rollback($this->cds);
		$this->release();
	}

	/**
	 * @throws Exception
	 * 事务提交
	 */
	public function commit()
	{
		$this->connections()->commit($this->cds);
		$this->release();
	}

	/**
	 * @param $sql
	 * @return PDO
	 * @throws Exception
	 */
	public function refresh($sql): PDO
	{
		if ($this->isWrite($sql)) {
			$instance = $this->masterInstance();
		} else {
			$instance = $this->slaveInstance();
		}
		return $instance;
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
	public function release()
	{
		if (!Kiri::isWorker() && !Kiri::isProcess()) {
			$this->clear_connection();
			return;
		}
		$connections = $this->connections();
		$connections->release($this->cds, true);
		$connections->release($this->slaveConfig['cds'], false);
	}


	/**
	 * @throws Exception
	 */
	public function recovery()
	{
		$connections = $this->connections();

		$connections->release($this->cds, true);
		$connections->release($this->slaveConfig['cds'], false);
	}

	/**
	 *
	 * 回收链接
	 * @throws
	 */
	public function clear_connection()
	{
		$connections = $this->connections();

		$connections->connection_clear($this->cds, true);
		$connections->connection_clear($this->slaveConfig['cds'], false);
	}


	/**
	 * @throws Exception
	 */
	public function disconnect()
	{
		$connections = $this->connections();
		$connections->disconnect($this->cds, true);
		$connections->disconnect($this->slaveConfig['cds'], false);
	}

}
