<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/3/30 0030
 * Time: 15:23
 */
declare(strict_types=1);

namespace Database;


use Exception;
use Kiri\Abstracts\Component;
use Kiri\Di\Context;
use Kiri\Exception\ConfigException;
use PDO;
use PDOStatement;

/**
 * Class Command
 * @package Database
 */
class Command extends Component
{
	const ROW_COUNT = 'count';
	const FETCH = 'fetch';
	const FETCH_ALL = 'fetchAll';
	const EXECUTE = 'execute';
	const FETCH_COLUMN = 'fetchColumn';
	
	const DB_ERROR_MESSAGE = 'The system is busy, please try again later.';
	
	/** @var Connection */
	public Connection $connection;
	
	/** @var ?string */
	public ?string $sql = '';
	
	/** @var array */
	public array $params = [];
	
	
	/**
	 * @return int|bool
	 * @throws Exception
	 */
	public function incrOrDecr(): int|bool
	{
		return $this->_execute();
	}
	
	/**
	 * @return int|bool
	 * @throws Exception
	 */
	public function save(): int|bool
	{
		return $this->_execute();
	}
	
	
	/**
	 * @return bool|array|null
	 * @throws Exception
	 */
	public function all(): null|bool|array
	{
		try {
			$pdo = $this->connection->getConnection();
			if (($prepare = $pdo->query($this->sql)) === false) {
				throw new Exception($pdo->errorInfo()[1]);
			}
			foreach ($this->params as $key => $param) {
				$prepare->bindParam($key, $param);
			}
			return $prepare->fetchAll(PDO::FETCH_ASSOC);
		} catch (\Throwable $throwable) {
			if (str_contains($throwable->getMessage(), 'MySQL server has gone away')) {
				return $this->all();
			}
			return $this->error($throwable);
		} finally {
			$this->connection->release($pdo ?? null);
		}
	}
	
	/**
	 * @return bool|array|null
	 * @throws Exception
	 */
	public function one(): null|bool|array
	{
		try {
			$client = $this->connection->getConnection();
			if (($prepare = $client->prepare($this->sql)) === false) {
				throw new Exception($client->errorInfo()[1]);
			}
			foreach ($this->params as $key => $param) {
				$prepare->bindParam($key, $param, PDO::PARAM_STR | PDO::PARAM_INT);
			}
			return $prepare->fetch(PDO::FETCH_ASSOC);
		} catch (\Throwable $throwable) {
			if (str_contains($throwable->getMessage(), 'MySQL server has gone away')) {
				return $this->one();
			}
			return $this->error($throwable);
		} finally {
			$this->connection->release($client ?? null);
		}
	}
	
	/**
	 * @return bool|array|null
	 * @throws Exception
	 */
	public function fetchColumn(): null|bool|array
	{
		try {
			$client = $this->connection->getConnection();
			if (($prepare = $client->query($this->sql)) === false) {
				throw new Exception($client->errorInfo()[1]);
			}
			foreach ($this->params as $key => $param) {
				$prepare->bindParam($key, $param, PDO::PARAM_STR | PDO::PARAM_INT);
			}
			return $prepare->fetchColumn(PDO::FETCH_ASSOC);
		} catch (\Throwable $throwable) {
			if (str_contains($throwable->getMessage(), 'MySQL server has gone away')) {
				return $this->fetchColumn();
			}
			return $this->error($throwable);
		} finally {
			$this->connection->release($client ?? null);
		}
	}
	
	/**
	 * @return int|bool
	 * @throws Exception
	 */
	public function rowCount(): int|bool
	{
		try {
			$client = $this->connection->getConnection();
			if (($prepare = $client->query($this->sql)) === false) {
				throw new Exception($client->errorInfo()[1]);
			}
			foreach ($this->params as $key => $param) {
				$prepare->bindParam($key, $param, PDO::PARAM_STR | PDO::PARAM_INT);
			}
			return $prepare->rowCount();
		} catch (\Throwable $throwable) {
			if (str_contains($throwable->getMessage(), 'MySQL server has gone away')) {
				return $this->rowCount();
			}
			return $this->error($throwable);
		} finally {
			$this->connection->release($client ?? null);
		}
	}
	
	
	/**
	 * @return int|bool
	 * @throws Exception
	 */
	public function flush(): int|bool
	{
		return $this->_execute();
	}
	
	/**
	 * @return bool|int
	 * @throws ConfigException
	 * @throws Exception
	 */
	private function _execute(): bool|int
	{
		try {
			$client = $this->connection->getTransactionClient();
			if (($prepare = $client->prepare($this->sql)) === false) {
				throw new Exception($client->errorInfo()[1]);
			}
			if ($prepare->execute($this->params) === false) {
				throw new Exception($prepare->errorInfo()[1]);
			}
			$result = $client->lastInsertId();
			$prepare->closeCursor();
			
			if (!$client->inTransaction()) {
				$this->connection->release($client);
			}
			return $result == 0 ? true : $result;
		} catch (\Throwable $throwable) {
			if (str_contains($throwable->getMessage(), 'MySQL server has gone away')) {
				return $this->_execute();
			}
			return $this->error($throwable);
		}
	}
	
	
	/**
	 * @param \Throwable $throwable
	 * @return bool
	 */
	private function error(\Throwable $throwable): bool
	{
		$message = $this->sql . '.' . json_encode($this->params, JSON_UNESCAPED_UNICODE);
		return $this->logger->addError($message . $throwable->getMessage(), 'mysql');
	}
	
	
	/**
	 * @return int|bool
	 * @throws Exception
	 */
	public function delete(): int|bool
	{
		return $this->_execute();
	}
	
	/**
	 * @return int|bool
	 * @throws Exception
	 */
	public function exec(): int|bool
	{
		return $this->_execute();
	}
	
	/**
	 * @param array $data
	 * @return $this
	 */
	public function bindValues(array $data = []): static
	{
		if (count($data) > 0) {
			$this->params = array_merge($this->params, $data);
		}
		return $this;
	}
	
	/**
	 * @param $sql
	 * @return $this
	 * @throws Exception
	 */
	public function setSql($sql): static
	{
		$this->sql = $sql;
		return $this;
	}
	
}
