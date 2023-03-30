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

	/** @var ?string */
	public ?string $sql = '';

	/** @var array */
	public array $params = [];

	/** @var string */
	public string $dbname = '';


	/**
	 * @return mixed
	 * @throws Exception
	 */
	public function incrOrDecr(): mixed
	{
		return $this->execute(static::EXECUTE);
	}

	/**
	 * @return mixed
	 * @throws Exception
	 */
	public function save(): mixed
	{
		return $this->execute(static::EXECUTE);
	}


	/**
	 * @return mixed
	 * @throws Exception
	 */
	public function all(): mixed
	{
		return $this->execute(static::FETCH_ALL);
	}

	/**
	 * @return mixed
	 * @throws Exception
	 */
	public function one(): mixed
	{
		return $this->execute(static::FETCH);
	}

	/**
	 * @return mixed
	 * @throws Exception
	 */
	public function fetchColumn(): mixed
	{
		return $this->execute(static::FETCH_COLUMN);
	}

	/**
	 * @return mixed
	 * @throws Exception
	 */
	public function rowCount(): mixed
	{
		return $this->execute(static::ROW_COUNT);
	}

	/**
	 * @return mixed
	 * @throws Exception
	 */
	public function flush(): mixed
	{
		return $this->execute(static::EXECUTE);
	}


	/**
	 * @param string $type
	 * @return mixed
	 * @throws Exception
	 */
	private function execute(string $type): mixed
	{
		$time = microtime(true);

		$result = $type !== static::EXECUTE ? $this->search($type) : $this->_execute();

//		$this->longExecuteTime($time);

		return $result;
	}


	/**
	 * @return bool|int
	 * @throws Exception
	 */
	private function _execute(): bool|int
	{
		try {
			$pdo = $this->db->getPdo();
			if (!(($prepare = $pdo->prepare($this->sql)) instanceof PDOStatement)) {
				throw new Exception($prepare->errorInfo()[2] ?? static::DB_ERROR_MESSAGE);
			}
			if ($prepare->execute($this->params) === false) {
				throw new Exception($prepare->errorInfo()[2] ?? static::DB_ERROR_MESSAGE);
			}

			$result = (int)$pdo->lastInsertId();
			$prepare->closeCursor();

			$this->db->release(true);

			return $result == 0 ? true : $result;
		} catch (\PDOException|\Throwable $throwable) {
			if (str_contains($throwable->getMessage(), 'MySQL server has gone away')) {
				$this->db->restore(true);

				return $this->_execute();
			}

			$this->db->release(true);

			return $this->logger->addError($this->sql . '. error: ' . $throwable->getMessage(), 'mysql');
		}
	}

	/**
	 * @param string $type
	 * @return array|int|bool|null
	 * @throws Exception
	 */
	private function search(string $type): mixed
	{
		try {
			$pdo = $this->db->getSlaveClient();
			if (($statement = $pdo->query($this->sql)) === false) {
				throw new Exception($pdo->errorInfo()[1]);
			}
			foreach ($this->params as $key => $param) {
				$statement->bindValue($key, $param);
			}
			$data = $statement->{$type}(\PDO::FETCH_ASSOC);
			$this->db->release(false);
			return $data;
		} catch (\Throwable $throwable) {
			if (str_contains($throwable->getMessage(), 'MySQL server has gone away')) {
				$this->db->restore(false);

				return $this->search($type);
			}

			$this->db->release(false);

			return $this->logger->addError($this->sql . '. error: ' . $throwable->getMessage(), 'mysql');
		}
	}


	private function longExecuteTime($time)
	{
		if (($over = microtime(true) - $time) >= 0.05) {
			$this->logger->warning($this->sql . '. use time : ' . $over . 'ms');
		}
	}


	/**
	 * @return mixed
	 * @throws Exception
	 */
	public function delete(): mixed
	{
		return $this->execute(static::EXECUTE);
	}

	/**
	 * @return mixed
	 * @throws Exception
	 */
	public function exec(): mixed
	{
		return $this->execute(static::EXECUTE);
	}

	/**
	 * @param array $data
	 * @return $this
	 */
	public function bindValues(array $data = []): static
	{
		if (!empty($data)) {
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
