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

    public Connection $connection;
    public ?string    $sql    = '';
    public array      $params = [];


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
        return $this->_prepare();
    }

    /**
     * @return int|bool
     * @throws Exception
     */
    public function save(): int|bool
    {
        return $this->_prepare();
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
     * @throws Exception
     */
    protected function search(string $method): mixed
    {
        $client = $this->connection->getConnection();
        try {
            if (($prepare = $client->prepare($this->sql)) === false) {
                throw new Exception('(' . $prepare->errorInfo()[0] . ')' . $client->errorInfo()[2]);
            }

            $prepare->execute($this->params);

            return $prepare->{$method}(PDO::FETCH_ASSOC);
        } catch (Throwable $throwable) {
            if ($this->isRefresh($throwable)) {
                return $this->search($method);
            }
            return $this->error($throwable);
        } finally {
            $this->connection->release($client);
        }
    }


    /**
     * @return int|bool
     * @throws Exception
     */
    public function flush(): int|bool
    {
        return $this->_prepare();
    }


    /**
     * @return PDOStatement|int
     * @throws Exception
     */
    private function _prepare(): bool|int
    {
        $client = $this->connection->getConnection();
        try {
            if (($prepare = $client->prepare($this->sql)) === false) {
                throw new Exception('(' . $prepare->errorInfo()[0] . ')' . $prepare->errorInfo()[2]);
            }
            if ($prepare->execute($this->params) === false) {
                throw new Exception('(' . $prepare->errorInfo()[0] . ')' . $prepare->errorInfo()[2]);
            }
            $prepare->closeCursor();

            $result = $client->lastInsertId();

            return $result == 0 ? $prepare->rowCount() > 0 : (int)$result;
        } catch (Throwable $throwable) {
            if ($this->isRefresh($throwable)) {
                return $this->_prepare();
            }
            return $this->error($throwable);
        } finally {
            $this->connection->release($client);
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
        return $this->_prepare();
    }

    /**
     * @return int|bool
     * @throws Exception
     */
    public function exec(): int|bool
    {
        return $this->_prepare();
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
