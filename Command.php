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
	public Connection $db;
	
	
	public PDO $pdo;

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
			if (($prepare = $this->pdo->query($this->sql)) === false) {
				throw new Exception($this->pdo->errorInfo()[1]);
			}
			$prepare->execute($this->params);
			return $prepare->fetchAll(PDO::FETCH_ASSOC);
		} catch (\Throwable $throwable) {
			if (str_contains($throwable->getMessage(), 'MySQL server has gone away')) {
				return $this->all();
			}
			return $this->error($throwable);
		} finally {
			$this->db->release($this->pdo);
		}
	}

	/**
	 * @return bool|array|null
	 * @throws Exception
	 */
	public function one(): null|bool|array
	{
		try {
			if (($prepare = $this->pdo->query($this->sql)) === false) {
				throw new Exception($this->pdo->errorInfo()[1]);
			}
			$prepare->execute($this->params);
			return $prepare->fetch(PDO::FETCH_ASSOC);
		} catch (\Throwable $throwable) {
			if (str_contains($throwable->getMessage(), 'MySQL server has gone away')) {
				return $this->one();
			}
			return $this->error($throwable);
		} finally {
			$this->db->release($this->pdo);
		}
	}

	/**
	 * @return bool|array|null
	 * @throws Exception
	 */
	public function fetchColumn(): null|bool|array
	{
		try {
			if (($prepare = $this->pdo->query($this->sql)) === false) {
				throw new Exception($this->pdo->errorInfo()[1]);
			}
			$prepare->execute($this->params);
			return $prepare->fetchColumn(PDO::FETCH_ASSOC);
		} catch (\Throwable $throwable) {
			if (str_contains($throwable->getMessage(), 'MySQL server has gone away')) {
				return $this->fetchColumn();
			}
			return $this->error($throwable);
		} finally {
			$this->db->release($this->pdo);
		}
	}

	/**
	 * @return int|bool
	 * @throws Exception
	 */
	public function rowCount(): int|bool
	{
		try {
			if (($prepare = $this->pdo->query($this->sql)) === false) {
				throw new Exception($this->pdo->errorInfo()[1]);
			}
			$prepare->execute($this->params);
			return $prepare->rowCount();
		} catch (\Throwable $throwable) {
			if (str_contains($throwable->getMessage(), 'MySQL server has gone away')) {
				return $this->rowCount();
			}
			return $this->error($throwable);
		} finally {
			$this->db->release($this->pdo);
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
			$prepare = $this->pdo->prepare($this->sql);
			if ($prepare === false) {
				throw new Exception($this->pdo->errorInfo()[1]);
			}
			if ($prepare->execute($this->params) === false) {
				throw new Exception($prepare->errorInfo()[1]);
			}
			$result = $this->pdo->lastInsertId();
			$prepare->closeCursor();
			return $result == 0 ? true : $result;
		} catch (\Throwable $throwable) {
			if (str_contains($throwable->getMessage(), 'MySQL server has gone away')) {
				return $this->_execute();
			}
			return $this->error($throwable);
		} finally {
			$this->db->release($this->pdo);
		}
	}


	/**
	 * @param \Throwable $throwable
	 * @return bool
	 */
	private function error(\Throwable $throwable): bool
	{
		$message = $this->sql . '(' . json_encode($this->params, JSON_UNESCAPED_UNICODE) . ');';
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
