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
use Kiri\Exception\ConfigException;
use PDO;
use PDOStatement;
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
        return $this->search('fetchAll');
    }

    /**
     * @return bool|array|null
     * @throws Exception
     */
    public function one(): null|bool|array
    {
        return $this->search('fetch');
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function fetchColumn(): mixed
    {
        return $this->search('fetchColumn');
    }


    /**
     * @param string $method
     * @return mixed
     */
    protected function search(string $method): mixed
    {
        try {
            $client = $this->connection->getConnection();
            if (($prepare = $client->prepare($this->sql)) === false) {
                throw new Exception('(' . $prepare->errorInfo()[0] . ')' . $client->errorInfo()[2]);
            }
            $prepare->execute($this->params);
            $data = $prepare->{$method}(PDO::FETCH_ASSOC);
            $this->connection->release($client);
            return $data;
        } catch (Throwable $throwable) {
            if ($this->isRefresh($throwable)) {
                return $this->search($method);
            }
            if (isset($client)) {
                $this->connection->release($client);
            }
            return $this->error($throwable);
        }
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
                throw new Exception('(' . $prepare->errorInfo()[0] . ')' . $prepare->errorInfo()[2]);
            }
            $result = $client->lastInsertId();
            $prepare->closeCursor();
            if ($prepare->rowCount() < 1) {
                return trigger_print_error("更新失败", 'mysql');
            }
            $this->connection->release($client);
            return $result == 0 ? true : (int)$result;
        } catch (Throwable $throwable) {
            if ($this->isRefresh($throwable)) {
                return $this->_execute();
            }
            if (isset($client)) {
                $this->connection->release($client);
            }
            return $this->error($throwable);
        }
    }


    /**
     * @param Throwable $throwable
     * @return bool
     */
    protected function isRefresh(Throwable $throwable): bool
    {
        if (str_contains($throwable->getMessage(), 'MySQL server has gone away')) {
            return true;
        }
        if (str_contains($throwable->getMessage(), 'Send of 14 bytes failed with errno=32 Broken pipe')) {
            return true;
        }
        if (str_contains($throwable->getMessage(), 'Lost connection to MySQL server during query')) {
            return true;
        }
        return false;
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
