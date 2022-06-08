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
	 * @return array|bool|int|string|PDOStatement|null
	 * @throws Exception
	 */
	public function incrOrDecr(): array|bool|int|string|PDOStatement|null
	{
		return $this->execute(static::EXECUTE);
	}

	/**
	 * @return int|bool|array|string|null
	 * @throws Exception
	 */
	public function save(): int|bool|array|string|null
	{
		return $this->execute(static::EXECUTE);
	}


	/**
	 * @return int|bool|array|string|null
	 * @throws Exception
	 */
	public function all(): int|bool|array|string|null
	{
		return $this->execute(static::FETCH_ALL);
	}

	/**
	 * @return array|bool|int|string|null
	 * @throws Exception
	 */
	public function one(): null|array|bool|int|string
	{
		return $this->execute(static::FETCH);
	}

	/**
	 * @return int|bool|array|string|null
	 * @throws Exception
	 */
	public function fetchColumn(): int|bool|array|string|null
	{
		return $this->execute(static::FETCH_COLUMN);
	}

	/**
	 * @return int|bool|array|string|null
	 * @throws Exception
	 */
	public function rowCount(): int|bool|array|string|null
	{
		return $this->execute(static::ROW_COUNT);
	}

	/**
	 * @return int|bool|array|string|null
	 * @throws Exception
	 */
	public function flush(): int|bool|array|string|null
	{
		return $this->execute(static::EXECUTE);
	}


	/**
	 * @param string $type
	 * @return int|bool|array|string|null
	 * @throws Exception
	 */
	private function execute(string $type): int|bool|array|string|null
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
		return print_r([$this->sql, $this->params], true) . (microtime(true) - $time);
	}


	/**
	 * @return bool|int
	 * @throws Exception
	 */
	private function _execute(): bool|int
	{
		try {
			$pdo = $this->db->masterInstance();

			$result = $pdo->execute($this->sql, $this->params);

			$this->db->release($pdo, true);
		} catch (\Throwable $exception) {
			$result = $this->logger->addError($this->sql . '. error: ' . $exception->getMessage(), 'mysql');
		} finally {
			return $result;
		}
	}


	/**
	 * @param string $type
	 * @return array|int|bool|null
	 * @throws Exception
	 */
	private function search(string $type): array|int|bool|null
	{
		try {
			$pdo = $this->db->slaveInstance();

			$data = $pdo->{$type}($this->sql, $this->params);

			$this->db->release($pdo, false);
		} catch (\Throwable $throwable) {
			$data = $this->logger->addError($this->sql . '. error: ' . $throwable->getMessage(), 'mysql');
		} finally {
			return $data;
		}
	}


	/**
	 * @return int|bool|array|string|null
	 * @throws Exception
	 */
	public function delete(): int|bool|array|string|null
	{
		return $this->execute(static::EXECUTE);
	}

	/**
	 * @return int|bool|array|string|null
	 * @throws Exception
	 */
	public function exec(): int|bool|array|string|null
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
