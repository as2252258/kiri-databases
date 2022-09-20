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

		$this->logger->debug('Mysql:' . $this->print_r($time));

		return $result;
	}


	/**
	 * @param $time
	 * @return string
	 */
	private function print_r($time): string
	{
		return print_r(['time' => microtime(true) - $time, 'sql' => $this->sql, 'param' => $this->params], true);
	}


	/**
	 * @return bool|int
	 * @throws Exception
	 */
	private function _execute(): bool|int
	{
		$pdo = $this->db->getPdo();
		try {
			$result = $pdo->execute($this->sql, $this->params);
		} catch (\Throwable $exception) {
			$result = $this->logger->addError($this->sql . '. error: ' . $exception->getMessage(), 'mysql');
		} finally {
			$this->db->release($pdo, true);
			return $result;
		}
	}


	/**
	 * @param string $type
	 * @return array|int|bool|null
	 * @throws Exception
	 */
	private function search(string $type): mixed
	{
		$pdo = $this->db->getSlaveClient();
		try {
			$data = $pdo->{$type}($this->sql, $this->params);
		} catch (\Throwable $throwable) {
			$data = $this->logger->addError($this->sql . '. error: ' . $throwable->getMessage(), 'mysql');
		} finally {
			$this->db->release($pdo, false);
			return $data;
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
