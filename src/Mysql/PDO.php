<?php

namespace Database\Mysql;

use Exception;
use Http\Context\Context;
use Kiri\Events\EventProvider;
use Kiri\Kiri;
use PDOStatement;
use Server\Events\OnWorkerExit;
use Swoole\Timer;

class PDO
{

	const DB_ERROR_MESSAGE = 'The system is busy, please try again later.';


	private ?\PDO $pdo = null;


	private int $_transaction = 0;


	/**
	 * @var EventProvider
	 */
	private EventProvider $eventProvider;

	private int $_timer = -1;

	private int $_last = 0;


	/**
	 * @param string $dbname
	 * @param string $cds
	 * @param string $username
	 * @param string $password
	 * @param string $chatset
	 * @throws
	 */
	public function __construct(public string $dbname, public string $cds,
	                            public string $username, public string $password, public string $chatset = 'utf8mb4')
	{
		$this->eventProvider = Kiri::getDi()->get(EventProvider::class);
	}


	public function init()
	{
		$this->heartbeat_check();
		$this->eventProvider->on(OnWorkerExit::class, [$this, 'stopHeartbeatCheck']);
	}


	/**
	 * @return bool
	 */
	public function inTransaction(): bool
	{
		return $this->_transaction > 0;
	}


	/**
	 *
	 */
	public function heartbeat_check(): void
	{
		if ($this->_timer === -1 && Context::inCoroutine()) {
			$this->_timer = Timer::tick(3000, function () {
				try {
					if (time() - $this->_last > 10 * 60) {
						$this->stopHeartbeatCheck();
						$this->pdo = null;
					}
				} catch (\Throwable $throwable) {
					error($throwable);
				}
			});
		}
	}


	/**
	 *
	 */
	public function stopHeartbeatCheck(): void
	{
		if (Context::inCoroutine()) {
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
	}


	/**
	 *
	 */
	public function commit()
	{
		if ($this->_transaction == 0) {
			$this->_pdo()->commit();
		}
		$this->_transaction--;
	}


	/**
	 *
	 */
	public function rollback()
	{
		if ($this->_transaction == 0) {
			$this->_pdo()->rollBack();
		}
		$this->_transaction--;
	}


	/**
	 * @param string $sql
	 * @param array $params
	 * @return array
	 * @throws Exception
	 */
	public function fetchAll(string $sql, array $params = []): array
	{
		return $this->queryPrev($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
	}


	/**
	 * @param string $sql
	 * @param array $params
	 * @return array
	 * @throws Exception
	 */
	public function fetch(string $sql, array $params = []): array
	{
		return $this->queryPrev($sql, $params)->fetch(\PDO::FETCH_ASSOC);
	}


	/**
	 * @param string $sql
	 * @param array $params
	 * @return array
	 * @throws Exception
	 */
	public function fetchColumn(string $sql, array $params = []): array
	{
		return $this->queryPrev($sql, $params)->fetchColumn(\PDO::FETCH_ASSOC);
	}


	/**
	 * @param string $sql
	 * @param array $params
	 * @return int
	 * @throws Exception
	 */
	public function count(string $sql, array $params = []): int
	{
		return $this->queryPrev($sql, $params)->rowCount();
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
		if (($statement = $this->_pdo()->query($sql)) === false) {
			throw new Exception($this->_pdo()->errorInfo()[1]);
		}
		return $this->bindValue($statement, $params);
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
		if (!(($prepare = $this->_pdo()->prepare($sql)) instanceof PDOStatement)) {
			throw new Exception($prepare->errorInfo()[2] ?? static::DB_ERROR_MESSAGE);
		}
		defer(fn() => $prepare->closeCursor());
		if ($prepare->execute($params) === false) {
			throw new Exception($prepare->errorInfo()[2] ?? static::DB_ERROR_MESSAGE);
		}
		return (int)$this->_pdo()->lastInsertId();
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
			\PDO::ATTR_TIMEOUT                  => 60,
			\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
			\PDO::MYSQL_ATTR_INIT_COMMAND       => 'SET NAMES ' . $this->chatset
		]);
		$link->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$link->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, false);
		$link->setAttribute(\PDO::ATTR_ORACLE_NULLS, \PDO::NULL_EMPTY_STRING);
		return $link;
	}

}
