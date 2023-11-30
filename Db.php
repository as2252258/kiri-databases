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
use Kiri\Exception\ConfigException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use Throwable;

/**
 * Class Db
 * @package Database
 */
class Db implements ISqlBuilder
{
    use QueryTrait;


    private static bool $_inTransaction = false;


    /**
     * @var Connection|null
     */
    private ?Connection $connection = null;


    /**
     * @param string|Connection $dbname
     * @return Db
     * @throws Exception
     */
    public static function connect(string|Connection $dbname): Db
    {
        $db = new Db();
        if (is_string($dbname)) {
            $dbname = \Kiri::getDi()->get(DatabasesProviders::class)->get($dbname);
        }
        $db->connection = $dbname;
        return $db;
    }

    /**
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public static function beginTransaction(): void
    {
        fire(new BeginTransaction());
    }


    /**
     * @param Closure $closure
     * @param mixed ...$params
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    public static function Transaction(Closure $closure, ...$params): mixed
    {
        static::beginTransaction();
        try {
            $result = call_user_func($closure, ...$params);
        } catch (Throwable $throwable) {
            $result = trigger_print_error($throwable->getMessage(), 'mysql');
        } finally {
            if ($result === false) {
                static::rollback();
            } else {
                static::commit();
            }
            return $result;
        }
    }


    /**
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public static function commit(): void
    {
        fire(new Commit());
    }


    /**
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
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
     * @throws Exception
     */
    public function get(): array|bool
    {
        return $this->connection->createCommand(SqlBuilder::builder($this)->all())->all();
    }

    /**
     * @return array|bool|null
     * @throws Exception
     */
    public function first(): array|bool|null
    {
        return $this->connection->createCommand(SqlBuilder::builder($this)->all())->one();
    }

    /**
     * @return bool|int
     * @throws Exception
     */
    public function count(): bool|int
    {
        return $this->connection->createCommand(SqlBuilder::builder($this)->count())->one()['row_count'];
    }

    /**
     * @return bool|int
     * @throws Exception
     */
    public function exists(): bool|int
    {
        return $this->connection->createCommand(SqlBuilder::builder($this)->one())->fetchColumn();
    }

    /**
     * @param string $sql
     * @param array $attributes
     * @return array|bool|int|string|null
     * @throws Exception
     */
    public function query(string $sql, array $attributes = []): int|bool|array|string|null
    {
        return $this->connection->createCommand($sql, $attributes)->all();
    }

    /**
     * @param string $sql
     * @param array $attributes
     * @return array|bool|int|string|null
     * @throws Exception
     */
    public function one(string $sql, array $attributes = []): int|bool|array|string|null
    {
        return $this->connection->createCommand($sql, $attributes)->one();
    }


    /**
     * @return bool|int
     * @throws ConfigException
     * @throws Exception
     */
    public function delete(): bool|int
    {
        return $this->connection->createCommand(SqlBuilder::builder($this)->delete())->delete();
    }

    /**
     * @param string $table
     * @return array|bool|null
     * @throws Exception
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
            ->getSql())->one();
    }


    /**
     * @param string $table
     * @param Connection|null $connection
     * @param string $database
     * @return array|null
     * @throws Exception
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
     * @throws Exception
     */
    public static function getDefaultConnection(?Connection $connection = null, string $database = 'db'): Connection
    {
        if ($connection instanceof Connection) {
            return $connection;
        }
        $databases = \config('databases.connections', []);
        $providers = \Kiri::getDi()->get(DatabasesProviders::class);
        if (empty($databases) || !is_array($databases)) {
            throw new Exception('Please configure the database link.');
        }
        return $providers->get($databases[$database]);
    }


}
