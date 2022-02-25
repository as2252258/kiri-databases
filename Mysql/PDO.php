<?php

namespace Database\Mysql;

use Exception;
use Kiri;
use Kiri\Abstracts\Config;
use Kiri\Events\EventProvider;
use Kiri\Pool\StopHeartbeatCheck;
use Kiri\Server\Events\OnWorkerExit;
use PDOStatement;
use Swoole\Timer;

/**
 *
 */
class PDO implements StopHeartbeatCheck
{

	const DB_ERROR_MESSAGE = 'The system is busy, please try again later.';


	private ?\PDO $pdo = null;


	private int $_transaction = 0;


	private int $_timer = -1;

	private int $_last = 0;

	public string $dbname;
	public string $cds;
	public string $username;
	public string $password;
	public string $charset;
	public int $connect_timeout;
	public int $read_timeout;


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
	 */
	public function init(): void
	{
		$this->heartbeat_check();
		$eventProvider = Kiri::getDi()->get(EventProvider::class);
		$eventProvider->on(OnWorkerExit::class, [$this, 'onWorkerExit']);
	}


	/**
	 * @return bool
	 */
	public function inTransaction(): bool
	{
		echo __FUNCTION__, $this->_transaction, PHP_EOL;
		return $this->_transaction > 0;
	}


	/**
	 * @param Kiri\Server\Events\OnWorkerExit $exit
	 * @return void
	 */
	public function onWorkerExit(OnWorkerExit $exit)
	{
		$this->stopHeartbeatCheck();
	}


	/**
	 *
	 */
	public function heartbeat_check(): void
	{
		if ($this->_timer === -1) {
			$this->_timer = Timer::tick(1000, fn() => $this->waite());
		}
	}


	/**
	 * @throws Exception
	 */
	private function waite(): void
	{
		try {
			if ($this->_timer == -1) {
				$this->stopHeartbeatCheck();
			}
			if (time() - $this->_last > (int)Config::get('databases.pool.tick', 60)) {
				$this->stopHeartbeatCheck();

				$this->pdo = null;
			}
		} catch (\Throwable $throwable) {
			error($throwable);
		}
	}


	/**
	 *
	 */
	public function stopHeartbeatCheck(): void
	{
		if ($this->_timer > -1) {
			Timer::clear($this->_timer);
		}
		$this->_timer = -1;
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

		echo __FUNCTION__, $this->_transaction, PHP_EOL;
	}


	/**
	 *
	 */
	public function commit()
	{
		echo __FUNCTION__, $this->_transaction, PHP_EOL;
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
		echo __FUNCTION__, $this->_transaction, PHP_EOL;
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
	public function count(string $sql, array $params = []): int
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
		$this->_last = time();
		try {
			if (($statement = $this->_pdo()->query($sql)) === false) {
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
		$this->_last = time();
		$pdo = $this->_pdo();
		if (!(($prepare = $pdo->prepare($sql)) instanceof PDOStatement)) {
			throw new Exception($prepare->errorInfo()[2] ?? static::DB_ERROR_MESSAGE);
		}
		if ($prepare->execute($params) === false) {
			throw new Exception($prepare->errorInfo()[2] ?? static::DB_ERROR_MESSAGE);
		}
		$result = (int)$pdo->lastInsertId();
		$prepare->closeCursor();
		if ($result == 0) {
			return true;
		}
		return $result;
	}


	/**
	 * @return \PDO
	 */
	public function _pdo(): \PDO
	{
		if ($this->_timer === -1) {
			$this->heartbeat_check();
		}
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
			\PDO::ATTR_EMULATE_PREPARES         => false,
			\PDO::ATTR_CASE                     => \PDO::CASE_NATURAL,
			\PDO::ATTR_TIMEOUT                  => $this->connect_timeout,
			\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
			\PDO::MYSQL_ATTR_INIT_COMMAND       => 'SET NAMES ' . $this->charset
		]);
		$link->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$link->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, false);
		$link->setAttribute(\PDO::ATTR_ORACLE_NULLS, \PDO::NULL_EMPTY_STRING);
		if (!empty($this->attributes)) {
			foreach ($this->attributes as $key => $attribute) {
				$link->setAttribute($key, $attribute);
			}
		}
		return $link;
	}

}
