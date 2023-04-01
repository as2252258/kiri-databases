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
	 * @return array|null
	 * @throws Exception
	 */
	public function all(): ?array
	{
		[$pdo, $statement] = $this->search();
		
		$data = $statement->fetchAll(PDO::FETCH_ASSOC);
		
		$this->db->release($pdo);
		
		return $data;
	}
	
	/**
	 * @return array|null
	 * @throws Exception
	 */
	public function one(): ?array
	{
		[$pdo, $statement] = $this->search();

		$data = $statement->fetch(PDO::FETCH_ASSOC);
		
		$this->db->release($pdo);
		
		return $data;
	}

	/**
	 * @return mixed
	 * @throws Exception
	 */
	public function fetchColumn(): mixed
	{
		[$pdo, $statement] = $this->search();
		
		$data = $statement->fetchColumn(PDO::FETCH_ASSOC);
		
		$this->db->release($pdo);
		
		return $data;
	}

	/**
	 * @return ?int
	 * @throws Exception
	 */
	public function rowCount(): ?int
	{
		[$pdo, $statement] = $this->search();
		
		$data = $statement->rowCount();
		
		$this->db->release($pdo);
		
		return $data;
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
	 * @throws Exception
	 */
	private function _execute(): bool|int
	{
		$pdo = $this->db->getPdo();
		try {
			if (!(($prepare = $pdo->prepare($this->sql)) instanceof PDOStatement)) {
				throw new Exception($prepare->errorInfo()[2] ?? static::DB_ERROR_MESSAGE);
			}
			if ($prepare->execute($this->params) === false) {
				throw new Exception($prepare->errorInfo()[2] ?? static::DB_ERROR_MESSAGE);
			}

			$result = (int)$pdo->lastInsertId();
			$prepare->closeCursor();

			$this->db->release($pdo);

			return $result == 0 ? true : $result;
		} catch (\PDOException|\Throwable $throwable) {
			if (str_contains($throwable->getMessage(), 'MySQL server has gone away')) {
				$this->db->restore();

				return $this->_execute();
			}

			$this->db->release($pdo);

			return $this->logger->addError($this->sql . '. error: ' . $throwable->getMessage(), 'mysql');
		}
	}
	
	/**
	 * @return array<PDO, PDOStatement>|bool
	 * @throws Exception
	 */
	private function search(): bool|array
	{
		$pdo = $this->db->getSlaveClient();
		try {
			if (($statement = $pdo->query($this->sql)) === false) {
				throw new Exception($pdo->errorInfo()[1]);
			}
			foreach ($this->params as $key => $param) {
				$statement->bindValue($key, $param);
			}
			return [$pdo, $statement];
		} catch (\Throwable $throwable) {
			if (str_contains($throwable->getMessage(), 'MySQL server has gone away')) {
				$this->db->restore();

				return $this->search();
			}

			$this->db->release($pdo);

			return $this->logger->addError($this->sql . '. error: ' . $throwable->getMessage(), 'mysql');
		}
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
