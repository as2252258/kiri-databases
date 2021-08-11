<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/4/4 0004
 * Time: 15:40
 */
declare(strict_types=1);

namespace Database;

use Database\Affair\BeginTransaction;
use Database\Affair\Commit;
use Database\Affair\Rollback;
use Database\Traits\QueryTrait;
use Exception;
use Kiri\Abstracts\Config;
use Kiri\Events\EventDispatch;
use Kiri\Exception\ConfigException;

/**
 * Class Db
 * @package Database
 */
class Db implements ISqlBuilder
{
	use QueryTrait;


	private static bool $_inTransaction = false;

	/**
	 * @return bool
	 */
	public static function transactionsActive(): bool
	{
		return static::$_inTransaction === true;
	}

	/**
	 * @throws Exception
	 */
	public static function beginTransaction()
	{
		if (!static::transactionsActive()) {
			di(EventDispatch::class)->dispatch(new BeginTransaction());
		}
		static::$_inTransaction = true;
	}


	/**
	 * @throws Exception
	 */
	public static function commit()
	{
		if (static::transactionsActive()) {
			di(EventDispatch::class)->dispatch(new Commit());
		}
		static::$_inTransaction = false;
	}


	/**
	 * @throws Exception
	 */
	public static function rollback()
	{
		if (static::transactionsActive()) {
			di(EventDispatch::class)->dispatch(new Rollback());
		}
		static::$_inTransaction = false;
	}


	/**
	 * @param $table
	 *
	 * @return static
	 */
	public static function table($table): Db|static
	{
		$connection = new Db();
		$connection->from($table);
		return $connection;
	}


	/**
	 * @param string $column
	 * @param string $alias
	 * @return string
	 */
	public static function any_value(string $column, string $alias = ''): string
	{
		if (empty($alias)) {
			$alias = $column . '_any_value';
		}
		return 'ANY_VALUE(' . $column . ') as ' . $alias;
	}


	/**
	 * @param string $column
	 * @return string
	 */
	public static function increment(string $column): string
	{
		return '+ ' . $column;
	}


	/**
	 * @param string $column
	 * @return string
	 */
	public static function decrement(string $column): string
	{
		return '- ' . $column;
	}


	/**
	 * @param Connection|null $connection
	 * @return mixed
	 * @throws Exception
	 */
	public function get(Connection $connection = NULL): mixed
	{
		$connection = static::getDefaultConnection($connection);

		return $connection->createCommand(SqlBuilder::builder($this)->one())
			->all();
	}

	/**
	 * @param $column
	 * @return string
	 */
	public static function raw($column): string
	{
		return '`' . $column . '`';
	}

	/**
	 * @param Connection|null $connection
	 * @return mixed
	 * @throws Exception
	 */
	public function find(Connection $connection = NULL): mixed
	{
		$connection = static::getDefaultConnection($connection);

		return $connection->createCommand(SqlBuilder::builder($this)->all())
			->one();
	}

	/**
	 * @param Connection|NULL $connection
	 * @return bool|int
	 * @throws Exception
	 */
	public function count(Connection $connection = NULL): bool|int
	{
		$connection = static::getDefaultConnection($connection);

		return $connection->createCommand(SqlBuilder::builder($this)->count())
			->exec();
	}

	/**
	 * @param Connection|NULL $connection
	 * @return bool|int
	 * @throws Exception
	 */
	public function exists(Connection $connection = NULL): bool|int
	{
		$connection = static::getDefaultConnection($connection);

		return $connection->createCommand(SqlBuilder::builder($this)->one())
			->fetchColumn();
	}

	/**
	 * @param string $sql
	 * @param array $attributes
	 * @param Connection|null $connection
	 * @return array|bool|int|string|null
	 * @throws Exception
	 */
	public static function findAllBySql(string $sql, array $attributes = [], Connection $connection = NULL): int|bool|array|string|null
	{
		$connection = static::getDefaultConnection($connection);

		return $connection->createCommand($sql, $attributes)->all();
	}

	/**
	 * @param string $sql
	 * @param array $attributes
	 * @param Connection|NULL $connection
	 * @return string|array|bool|int|null
	 * @throws Exception
	 */
	public static function findBySql(string $sql, array $attributes = [], Connection $connection = NULL): string|array|bool|int|null
	{
		$connection = static::getDefaultConnection($connection);

		return $connection->createCommand($sql, $attributes)->one();
	}

	/**
	 * @param string $field
	 * @return array|null
	 * @throws Exception
	 */
	public function values(string $field): ?array
	{
		$data = $this->get();
		if (empty($data) || empty($field)) {
			return NULL;
		}
		$first = current($data);
		if (!isset($first[$field])) {
			return NULL;
		}
		return array_column($data, $field);
	}

	/**
	 * @param $field
	 * @return mixed
	 * @throws Exception
	 */
	public function value($field): mixed
	{
		$data = $this->find();
		if (!empty($field) && isset($data[$field])) {
			return $data[$field];
		}
		return $data;
	}

	/**
	 * @param Connection|null $connection
	 * @return bool|int
	 * @throws ConfigException
	 * @throws Exception
	 */
	public function delete(?Connection $connection = null): bool|int
	{
		$connection = static::getDefaultConnection($connection);

		return $connection->createCommand($connection->getBuild()->builder($this))->delete();
	}

	/**
	 * @param string $table
	 * @param null $connection
	 * @return bool|int
	 * @throws ConfigException
	 * @throws Exception
	 */
	public static function drop(string $table, $connection = null): bool|int
	{
		$connection = static::getDefaultConnection($connection);

		$sprint = sprintf('DROP TABLE `%s`.`%s`', $connection->database, $table);
		return $connection->createCommand($sprint)->delete();
	}

	/**
	 * @param string $table
	 * @param null $connection
	 * @return bool|int
	 * @throws Exception
	 */
	public static function truncate(string $table, $connection = null): bool|int
	{
		$connection = static::getDefaultConnection($connection);

		$sprint = sprintf('TRUNCATE `%s`.`%s`', $connection->database, $table);
		return $connection->createCommand($sprint)->exec();
	}

	/**
	 * @param string $table
	 * @param Connection|NULL $connection
	 * @return mixed
	 * @throws ConfigException
	 * @throws Exception
	 */
	public static function showCreateSql(string $table, Connection $connection = NULL): mixed
	{
		$connection = static::getDefaultConnection($connection);

		$sprint = sprintf('SHOW CREATE TABLE `%s`.`%s`', $connection->database, $table);
		return $connection->createCommand($sprint)->one();
	}

	/**
	 * @param string $table
	 * @param Connection|NULL $connection
	 * @return bool|int|null
	 * @throws ConfigException
	 * @throws Exception
	 */
	public static function desc(string $table, Connection $connection = NULL): bool|int|null
	{
		$connection = static::getDefaultConnection($connection);

		$sprint = sprintf('SHOW FULL FIELDS FROM `%s`.`%s`', $connection->database, $table);
		return $connection->createCommand($sprint)->all();
	}


	/**
	 * @param string $table
	 * @param Connection|NULL $connection
	 * @return mixed
	 * @throws Exception
	 */
	public static function show(string $table, Connection $connection = NULL): mixed
	{
		if (empty($table)) {
			return null;
		}
		$connection = static::getDefaultConnection($connection);

		$table = ['	const TABLE = \'select * from %s  where REFERENCED_TABLE_NAME=%s\';'];
		return $connection->createCommand((new Query())
			->select('*')
			->from('INFORMATION_SCHEMA.KEY_COLUMN_USAGE')
			->where(['REFERENCED_TABLE_NAME' => $table])
			->getSql())->one();
	}


	/**
	 * @param null|Connection $connection
	 * @param null $name
	 * @return mixed
	 * @throws ConfigException
	 * @throws Exception
	 */
	public static function getDefaultConnection(?Connection $connection, $name = null): Connection
	{
		if ($connection instanceof Connection) {
			return $connection;
		}
		$databases = Config::get('databases.connections', []);
		if (empty($databases) || !is_array($databases)) {
			throw new Exception('Please configure the database link.');
		}
		if (!empty($name)) {
			if (!isset($databases[$name])) {
				throw new Exception('Please configure the database link.');
			}
			return $databases[$name];
		}
		return current($databases);
	}


}
