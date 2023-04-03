<?php

namespace Database\Mysql;

use Database\Db;
use Exception;
use Kiri;
use Kiri\Events\EventProvider;
use Kiri\Pool\StopHeartbeatCheck;
use Kiri\Server\Events\OnWorkerExit;
use PDOStatement;
use Kiri\Server\WorkerStatus;
use Kiri\Server\Abstracts\StatusEnum;
use Swoole\Timer;

/**
 */
class PDO implements StopHeartbeatCheck
{

	const DB_ERROR_MESSAGE = 'The system is busy, please try again later.';


	private ?\PDO $pdo = null;


	private int $_transaction = 0;

	private int $_last = 0;

	public string $dbname;
	public string $cds;
	public string $username;
	public string $password;
	public string $charset;
	public int $connect_timeout;
	public int $read_timeout;

	private int $_timerId = -1;


	public array $attributes = [];


	/**
	 * @param array $config
	 */
	public function __construct(array $config)
	{
		$this->dbname = $config['dbname'];
		$this->cds = $config['cds'];
		$this->username = $config['username'];
		$this->password = $config['password'];
		$this->connect_timeout = $config['connect_timeout'] ?? 30;
		$this->read_timeout = $config['read_timeout'] ?? 10;
		$this->charset = $config['charset'] ?? 'utf8mb4';
		$this->attributes = $config['attributes'] ?? [];
	}


	/**
	 * @return void
	 * @throws Exception
	 */
	public function init(): void
	{
		$eventProvider = Kiri::getDi()->get(EventProvider::class);
		$eventProvider->on(OnWorkerExit::class, [$this, 'onWorkerExit']);
		$this->_timerId = Timer::tick(60000, [$this, 'check']);
	}


	/**
	 * @return bool
	 */
	public function inTransaction(): bool
	{
		return $this->_transaction > 0;
	}


	/**
	 * @param Kiri\Server\Events\OnWorkerExit $exit
	 * @return void
	 */
	public function onWorkerExit(OnWorkerExit $exit): void
	{
		$this->stopHeartbeatCheck();
	}


	/**
	 * @param string $name
	 * @param array $arguments
	 * @return mixed
	 */
	public function __call(string $name, array $arguments)
	{
		return $this->_pdo()->{$name}(...$arguments);
	}


	/**
	 * @param string $sql
	 * @return PDOStatement|bool
	 */
	public function prepare(string $sql): PDOStatement|bool
	{
		return $this->_pdo()->prepare($sql);
	}


	/**
	 *
	 */
	public function stopHeartbeatCheck(): void
	{
		$this->pdo = null;
		if ($this->_timerId > -1) {
			Timer::clear($this->_timerId);
			$this->_timerId = -1;
		}
	}


	/**
	 *
	 */
	public function beginTransaction()
	{
		if ($this->_transaction == 0) {
			$this->_pdo()->beginTransaction();
		}
		$this->_transaction++;
	}


	/**
	 *
	 */
	public function commit()
	{
		$this->_transaction--;
		if ($this->_transaction == 0) {
			$this->_pdo()->commit();
		}
	}


	/**
	 *
	 */
	public function rollback()
	{
		$this->_transaction--;
		if ($this->_transaction == 0) {
			$this->_pdo()->rollBack();
		}
	}


	/**
	 * @param string $sql
	 * @param array $params
	 * @return bool|array|null
	 * @throws Exception
	 */
	public function fetchAll(string $sql, array $params = []): bool|null|array
	{
		$pdo = $this->queryPrev($sql, $params);

		$result = $pdo->fetchAll(\PDO::FETCH_ASSOC);
		$pdo->closeCursor();
		return $result;
	}


	/**
	 * @param string $sql
	 * @param array $params
	 * @return bool|array|null
	 * @throws Exception
	 */
	public function fetch(string $sql, array $params = []): bool|null|array
	{
		$pdo = $this->queryPrev($sql, $params);

		$result = $pdo->fetch(\PDO::FETCH_ASSOC);
		$pdo->closeCursor();
		return $result;
	}


	/**
	 * @param string $sql
	 * @param array $params
	 * @return bool|array|null
	 * @throws \Exception
	 */
	public function fetchColumn(string $sql, array $params = []): bool|null|array
	{
		$pdo = $this->queryPrev($sql, $params);

		$result = $pdo->fetchColumn(\PDO::FETCH_ASSOC);
		$pdo->closeCursor();
		return $result;
	}


	/**
	 * @param string $sql
	 * @param array $params
	 * @return int
	 * @throws Exception
	 */
	public function rowCount(string $sql, array $params = []): int
	{
		$pdo = $this->queryPrev($sql, $params);

		$result = $pdo->rowCount();
		$pdo->closeCursor();
		return $result;
	}


	/**
	 * @param string $sql
	 * @param array $params
	 * @return PDOStatement
	 * @throws Exception
	 */
	private function queryPrev(string $sql, array $params = []): PDOStatement
	{
		try {
//			if ($this->_timerId === -1) {
//				$this->_timerId = Timer::tick(6000, [$this, 'check']);
//			}
//			$this->_last = time();
			if (($statement = $this->_pdo()->query($sql, \PDO::FETCH_ASSOC)) === false) {
				throw new Exception($this->_pdo()->errorInfo()[1]);
			}
			return $this->bindValue($statement, $params);
		} catch (\PDOException|\Throwable $throwable) {
			if (str_contains($throwable->getMessage(), 'MySQL server has gone away')) {
				$this->pdo = null;

				return $this->queryPrev($sql, $params);
			}
			throw new Exception($throwable->getMessage());
		}
	}


	/**
	 * @return bool
	 */
	public function check(): bool
	{
		return true;
		try {
			if ($this->_last == 0) $this->_last = time();
			if (time() - $this->_last >= 600) {
				return $result = false;
			} else if (!($this->pdo instanceof \PDO)) {
				return $result = false;
			}
			$this->_pdo()->getAttribute(\PDO::ATTR_SERVER_INFO);
			$result = true;
		} catch (\Throwable $throwable) {
			if (!str_contains($throwable->getMessage(), 'Idle dis')) {
				Kiri::getLogger()->error($throwable->getMessage());
			}
			$result = false;
		} finally {
			return $this->afterCheck($result);
		}
	}


	/**
	 * @param bool $result
	 * @return bool
	 */
	private function afterCheck(bool $result): bool
	{
		$container = Kiri::getDi()->get(WorkerStatus::class);
		if (!$result || $container->is(StatusEnum::EXIT)) {
			$this->pdo = null;
			$result = Timer::clear($this->_timerId);
			$this->_timerId = -1;
		}
		return $result;
	}


	/**
	 * @param PDOStatement $statement
	 * @param array $params
	 * @return PDOStatement
	 */
	private function bindValue(PDOStatement $statement, array $params = []): PDOStatement
	{
		if (empty($params)) return $statement;
		foreach ($params as $key => $param) {
			$statement->bindValue($key, $param);
		}
		return $statement;
	}


	/**
	 * @param string $sql
	 * @param array $params
	 * @return int
	 * @throws Exception
	 */
	public function execute(string $sql, array $params = []): int
	{
		$pdo = $this->_pdo();
		if ($this->_timerId === -1) {
			$this->_timerId = Timer::tick(6000, [$this, 'check']);
		}
		$this->_last = time();
		if (!(($prepare = $pdo->prepare($sql)) instanceof PDOStatement)) {
			throw new Exception($prepare->errorInfo()[2] ?? static::DB_ERROR_MESSAGE);
		}
		if ($prepare->execute($params) === false) {
			throw new Exception($prepare->errorInfo()[2] ?? static::DB_ERROR_MESSAGE);
		}

		$result = (int)$pdo->lastInsertId();
		$prepare->closeCursor();

		return $result == 0 ? true : $result;
	}


	/**
	 * @return array
	 */
	public function errorInfo(): array
	{
		return $this->pdo->errorInfo();
	}


	/**
	 * @return \PDO
	 */
	public function _pdo(): \PDO
	{
		if (!($this->pdo instanceof \PDO)) {
			$this->pdo = $this->newClient();
		}
		return $this->pdo;
	}


	/**
	 * @return \PDO
	 */
	private function newClient(): \PDO
	{
		$link = new \PDO('mysql:dbname=' . $this->dbname . ';host=' . $this->cds, $this->username, $this->password, [
			\PDO::ATTR_EMULATE_PREPARES   => false,
			\PDO::ATTR_CASE               => \PDO::CASE_NATURAL,
			\PDO::ATTR_PERSISTENT         => true,
			\PDO::ATTR_TIMEOUT            => $this->connect_timeout,
			\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . $this->charset
		]);
		$link->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$link->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, false);
		$link->setAttribute(\PDO::ATTR_ORACLE_NULLS, \PDO::NULL_EMPTY_STRING);
		foreach ($this->attributes as $key => $attribute) {
			$link->setAttribute($key, $attribute);
		}
		if (Db::inTransactionsActive()) {
			$link->beginTransaction();
		}
		return $link;
	}

}
