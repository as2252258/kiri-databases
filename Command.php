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
		$pdo = $this->db->getSlaveClient();

		$result = $pdo->fetchAll($this->sql, $this->params);

		$this->db->release($pdo);
		return $result;
	}

	/**
	 * @return array|null
	 * @throws Exception
	 */
	public function one(): ?array
	{
		$pdo = $this->db->getSlaveClient();

		$result = $pdo->fetch($this->sql, $this->params);

		$this->db->release($pdo);
		return $result;
	}

	/**
	 * @return mixed
	 * @throws Exception
	 */
	public function fetchColumn(): mixed
	{
		$pdo = $this->db->getSlaveClient();

		$result = $pdo->fetchColumn($this->sql, $this->params);

		$this->db->release($pdo);
		return $result;
	}

	/**
	 * @return int|null
	 * @throws Exception
	 */
	public function rowCount(): ?int
	{
		$pdo = $this->db->getSlaveClient();

		$result = $pdo->rowCount($this->sql, $this->params);

		$this->db->release($pdo);
		return $result;
	}


	private function printErrorMessage(\Throwable $throwable): mixed
	{
		$this->logger->addError($this->sql . '. error: ' . $throwable->getMessage(), 'mysql');

		return null;
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
	 * @param bool $restore
	 * @return bool|int
	 * @throws Exception
	 */
	private function _execute(): bool|int
	{
		$pdo = $this->db->getPdo();

		$result = $pdo->execute($this->sql, $this->params);

		$this->db->release($pdo);

		return $result == 0 ? true : $result;
	}

	/**
	 * @return array<Mysql\PDO, PDOStatement>|bool
	 * @throws Exception
	 */
	private function search(): bool|array
	{
		$pdo = $this->db->getSlaveClient();
		return [$pdo, null];
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
