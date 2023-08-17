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
use Kiri\Di\Container;
use Kiri\Di\Context;
use Kiri\Exception\ConfigException;
use PDO;
use PDOStatement;
use ReflectionException;
use Throwable;

/**
 * Class Command
 * @package Database
 */
class Command extends Component
{

    const RETRY_NAME = 'db:retry:count';

    /**
     *
     */
    const DB_ERROR_MESSAGE = 'The system is busy, please try again later.';

    /** @var Connection */
    public Connection $connection;

    /** @var ?string */
    public ?string $sql = '';

    /** @var array */
    public array $params = [];


    /**
     * @param array $params
     * @throws Exception
     */
    public function __construct(array $params = [])
    {
        parent::__construct();
        Container::configure($this, $params);
    }


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
     * @return bool|array
     * @throws Exception
     */
    public function all(): bool|array
    {
        try {
            $client = $this->connection->getConnection();
            if (($prepare = $client->prepare($this->sql)) === false) {
                throw new Exception($client->errorInfo()[1]);
            }
            $prepare->execute($this->params);
            return $prepare->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $throwable) {
            $result = $this->error($throwable);
            if (str_contains($throwable->getMessage(), 'MySQL server has gone away') && $this->retry()) {
                return $this->all();
            }
            return $result;
        } finally {
            $this->connection->release($client ?? null);
        }
    }

    /**
     * @return bool|array|null
     * @throws Exception
     */
    public function one(): null|bool|array
    {
        try {
            $client = $this->connection->getConnection();
            if (($prepare = $client->prepare($this->sql)) === false) {
                throw new Exception($client->errorInfo()[1]);
            }
            $prepare->execute($this->params);
            return $prepare->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $throwable) {
            $result = $this->error($throwable);
            if (str_contains($throwable->getMessage(), 'MySQL server has gone away') && $this->retry()) {
                return $this->one();
            }
            return $result;
        } finally {
            $this->connection->release($client ?? null);
        }
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function fetchColumn(): mixed
    {
        try {
            $client = $this->connection->getConnection();
            if (($prepare = $client->prepare($this->sql)) === false) {
                throw new Exception($client->errorInfo()[1]);
            }
            $prepare->execute($this->params);
            return $prepare->fetchColumn(PDO::FETCH_ASSOC);
        } catch (Throwable $throwable) {
            $result = $this->error($throwable);
            if (str_contains($throwable->getMessage(), 'MySQL server has gone away') && $this->retry()) {
                return $this->fetchColumn();
            }
            return $result;
        } finally {
            $this->connection->release($client ?? null);
        }
    }

    /**
     * @return int|bool
     * @throws Exception
     */
    public function rowCount(): int|bool
    {
        try {
            $client = $this->connection->getConnection();
            if (($prepare = $client->prepare($this->sql)) === false) {
                throw new Exception($client->errorInfo()[1]);
            }
            $prepare->execute($this->params);
            return $prepare->rowCount();
        } catch (Throwable $throwable) {
            $result = $this->error($throwable);
            if (str_contains($throwable->getMessage(), 'MySQL server has gone away') && $this->retry()) {
                return $this->rowCount();
            }
            return $result;
        } finally {
            $this->connection->release($client ?? null);
        }
    }


    /**
     * @return bool
     */
    protected function retry(): bool
    {
        if (Context::increment(self::RETRY_NAME) < 3) {
            return true;
        }
        Context::remove(self::RETRY_NAME);
        return false;
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
            $client = $this->connection->getConnection();
            if (($prepare = $client->prepare($this->sql)) === false) {
                throw new Exception($client->errorInfo()[1]);
            }
            if ($prepare->execute($this->params) === false) {
                throw new Exception($prepare->errorInfo()[1]);
            }
            $result = $client->lastInsertId();
            $prepare->closeCursor();

            return $result == 0 ? true : (int)$result;
        } catch (Throwable $throwable) {
            $result = $this->error($throwable);
            if (str_contains($throwable->getMessage(), 'MySQL server has gone away') && $this->retry()) {
                return $this->_execute();
            }
            return $result;
        } finally {
            $this->connection->release($client ?? null);
        }
    }

    /**
     * @param Throwable $throwable
     * @return bool
     */
    private function error(Throwable $throwable): bool
    {
        return trigger_print_error($this->sql . '.' . json_encode($this->params, JSON_UNESCAPED_UNICODE) . PHP_EOL . throwable($throwable), 'mysql');
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
