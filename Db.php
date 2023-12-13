<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/4/4 0004
 * Time: 15:40
 */
declare(strict_types=1);

namespace Database;

use Closure;
use Database\Affair\BeginTransaction;
use Database\Affair\Commit;
use Database\Affair\Rollback;
use Database\Traits\QueryTrait;
use Exception;
use Kiri;
use Throwable;

/**
 * Class Db
 * @package Database
 */
class Db extends QueryTrait implements ISqlBuilder
{

    private static bool $_inTransaction = false;


    /**
     * @var Connection|null
     */
    private ?Connection $connection = null;


    /**
     * @param string|Connection $dbname
     * @return Db
     * @throws
     */
    public static function connect(string|Connection $dbname): Db
    {
        $db = new Db();
        if (is_string($dbname)) {
            $db->connection = Kiri::getDi()->get(DatabasesProviders::class)->get($dbname);
        } else {
            $db->connection = $dbname;
        }
        return $db;
    }

    /**
     * @return void
     * @throws
     */
    public static function beginTransaction(): void
    {
        fire(new BeginTransaction());
    }


    /**
     * @return void
     * @throws
     */
    public static function commit(): void
    {
        fire(new Commit());
    }


    /**
     * @return void
     * @throws
     */
    public static function rollback(): void
    {
        fire(new Rollback());
    }


    /**
     * @param string $table
     * @param string $database
     * @return static
     */
    public static function table(string $table, string $database = 'db'): Db|static
    {
        $connection             = new Db();
        $connection->connection = current(\config('databases.connections'));
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
     * @return array|bool
     * @throws
     */
    public function get(): array|bool
    {
        return $this->connection->createCommand(SqlBuilder::builder($this)->all())->all();
    }

    /**
     * @return array|bool|null
     * @throws
     */
    public function first(): array|bool|null
    {
        return $this->connection->createCommand(SqlBuilder::builder($this)->all())->one();
    }

    /**
     * @return int
     */
    public function count(): int
    {
        $count = $this->connection->createCommand(SqlBuilder::builder($this)->count())->one();
        return current($count);
    }

    /**
     * @return bool
     */
    public function exists(): bool
    {
        $fetchColumn = $this->connection->createCommand(SqlBuilder::builder($this)->one())->fetchColumn();
        return !empty($fetchColumn);
    }

    /**
     * @param string $sql
     * @param array $attributes
     * @return array|bool|int|string|null
     * @throws
     */
    public function query(string $sql, array $attributes = []): int|bool|array|string|null
    {
        return $this->connection->createCommand($sql, $attributes)->all();
    }

    /**
     * @param string $sql
     * @param array $attributes
     * @return array|null
     */
    public function one(string $sql, array $attributes = []): ?array
    {
        return $this->connection->createCommand($sql, $attributes)->one();
    }


    /**
     * @return bool
     */
    public function delete(): bool
    {
        return $this->connection->createCommand(SqlBuilder::builder($this)->delete())->delete();
    }

    /**
     * @param string $table
     * @return array|bool|null
     * @throws
     */
    public static function show(string $table): array|bool|null
    {
        if ($table == '') {
            return null;
        }
        $connection = static::getDefaultConnection();

        $table = ['	const TABLE = \'select * from %s  where REFERENCED_TABLE_NAME=%s\';'];
        return $connection->createCommand((new Query())
            ->select('*')
            ->from('INFORMATION_SCHEMA.KEY_COLUMN_USAGE')
            ->where(['REFERENCED_TABLE_NAME' => $table])
            ->build())->one();
    }


    /**
     * @param string $table
     * @param Connection|null $connection
     * @param string $database
     * @return array|null
     * @throws
     */
    public static function desc(string $table, ?Connection $connection = null, string $database = 'db'): ?array
    {
        $sql = SqlBuilder::builder(new Query())->columns($table);

        $connection = self::getDefaultConnection($connection, $database);

        return $connection->createCommand($sql)->all();
    }


    /**
     * @param null|Connection $connection
     * @param string $database
     * @return mixed
     * @throws
     */
    public static function getDefaultConnection(?Connection $connection = null, string $database = 'db'): Connection
    {
        if ($connection instanceof Connection) {
            return $connection;
        }
        $databases = \config('databases.connections', []);
        $providers = Kiri::getDi()->get(DatabasesProviders::class);
        if (empty($databases) || !is_array($databases)) {
            throw new Exception('Please configure the database link.');
        }
        return $providers->get($databases[$database]);
    }


}
