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
     * @throws
     */
    public function __construct(array $params = [])
    {
        parent::__construct();
        Container::configure($this, $params);
    }


    /**
     * @return bool
     * @throws
     */
    public function incrOrDecr(): bool
    {
        return (bool)$this->_prepare();
    }

    /**
     * @return bool
     * @throws
     */
    public function save(): bool
    {
        return (bool)$this->_prepare();
    }


    /**
     * @return bool|array
     * @throws
     */
    public function all(): bool|array
    {
        return $this->search('fetchAll');
    }

    /**
     * @return array|bool|null
     * @throws
     */
    public function one(): array|null|bool
    {
        return $this->search('fetch');
    }

    /**
     * @return mixed
     * @throws
     */
    public function fetchColumn(): mixed
    {
        return $this->search('fetchColumn');
    }

    /**
     * @return mixed
     * @throws
     */
    public function rowCount(): int
    {
        return $this->search('rowCount');
    }


    /**
     * @return bool
     * @throws Exception
     */
    public function exists(): bool
    {
        $total = $this->search('rowCount');
        if ($total === false) {
            throw new Exception('Query data is has error.');
        }
        return $total > 0;
    }


    /**
     * @param string $method
     * @return mixed
     * @throws
     */
    protected function search(string $method): mixed
    {
        $client = $this->connection->getConnection();
        try {
            if (($prepare = $client->prepare($this->sql)) === false) {
                throw new Exception('(' . $prepare->errorInfo()[0] . ')' . $client->errorInfo()[2]);
            }

            $prepare->execute($this->params);
            $prepare->closeCursor();

            if ($method == 'rowCount') {
                return $prepare->rowCount();
            }
            return $prepare->{$method}(PDO::FETCH_ASSOC);
        } catch (Throwable $throwable) {
            if ($this->isRefresh($throwable)) {
                return $this->search($method);
            }
            return $this->getLogger()->failure(throwable($throwable), 'mysql');
        } finally {
            $this->connection->release($client);
        }
    }


    /**
     * @return bool
     * @throws
     */
    public function flush(): bool
    {
        return (bool)$this->_prepare();
    }


    /**
     * @return int|bool
     * @throws
     */
    private function _prepare(): int|bool
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

            return $result == 0 ? $prepare->rowCount() : (int)$result;
        } catch (Throwable $throwable) {
            if ($this->isRefresh($throwable)) {
                return $this->_prepare();
            }
            return $this->getLogger()->failure(throwable($throwable), 'mysql');
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
     * @return bool
     * @throws
     */
    public function delete(): bool
    {
        return (bool)$this->_prepare();
    }

    /**
     * @return int|bool
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

}
