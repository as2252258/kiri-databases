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
		return $this->_query(self::FETCH_ALL);
	}

	/**
	 * @return bool|array|null
	 * @throws Exception
	 */
	public function one(): null|bool|array
	{
		return $this->_query(self::FETCH);
	}

	/**
	 * @return bool|array|null
	 * @throws Exception
	 */
	public function fetchColumn(): null|bool|array
	{
		return $this->_query(self::FETCH_COLUMN);
	}

	/**
	 * @return int|bool
	 * @throws Exception
	 */
	public function rowCount(): int|bool
	{
		return $this->_query(self::ROW_COUNT);
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
	 * @return bool|int
	 * @throws ConfigException
	 * @throws Exception
	 */
	private function _execute(): bool|int
	{
		return $this->result(static function (PDO $pdo, string $sql, array $params) {
			$prepare = $pdo->prepare($sql);
			if ($prepare === false || $prepare->execute($params) === false) {
				throw new Exception(($prepare ?? $pdo)->errorInfo()[1]);
			}

			$result = (int)$pdo->lastInsertId();
			$prepare->closeCursor();

			return $result == 0 ? true : $result;
		});
	}

	/**
	 * @param string $type
	 * @return bool|array|int|null
	 * @throws Exception
	 */
	private function _query(string $type): bool|array|int|null
	{
		return $this->result(static function (PDO $pdo, string $sql, array $params) use ($type) {
			$prepare = $pdo->query($sql);
			if ($prepare === false || $prepare->execute($params) === false) {
				throw new Exception(($prepare ?? $pdo)->errorInfo()[1]);
			}
			//			$prepare->closeCursor();
			return match ($type) {
				Command::FETCH_COLUMN => $prepare->fetchColumn(PDO::FETCH_ASSOC),
				Command::ROW_COUNT => $prepare->rowCount(),
				Command::FETCH_ALL => $prepare->fetchAll(PDO::FETCH_ASSOC),
				Command::FETCH => $prepare->fetch(PDO::FETCH_ASSOC),
			};
		});
	}


	/**
	 * @param \Closure $callback
	 * @return bool|mixed
	 * @throws Exception
	 */
	private function result(\Closure $callback): mixed
	{
		$pdo = $this->db->getPdo();
		try {
			return $callback($pdo, $this->sql, $this->params);
		} catch (\Throwable $throwable) {
			if (str_contains($throwable->getMessage(), 'MySQL server has gone away')) {
				return $this->result($callback);
			}
			return $this->error($throwable);
		} finally {
			$this->db->release($pdo);
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
