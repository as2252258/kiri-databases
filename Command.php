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
use ReflectionException;
use Throwable;

/**
 * Class Command
 * @package Database
 */
class Command extends Component
{

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
            if ($this->canReconnect($throwable->getMessage())) {
                return $this->all();
            }
            return $this->error($throwable);
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
            if ($this->canReconnect($throwable->getMessage())) {
                return $this->one();
            }
            return $this->error($throwable);
        } finally {
            $this->connection->release($client ?? null);
        }
    }

    /**
     * @return bool|array|null
     * @throws Exception
     */
    public function fetchColumn(): null|bool|array
    {
        try {
            $client = $this->connection->getConnection();
            if (($prepare = $client->prepare($this->sql)) === false) {
                throw new Exception($client->errorInfo()[1]);
            }
            $prepare->execute($this->params);
            return $prepare->fetchColumn(PDO::FETCH_ASSOC);
        } catch (Throwable $throwable) {
            if ($this->canReconnect($throwable->getMessage())) {
                return $this->fetchColumn();
            }
            return $this->error($throwable);
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
            if ($this->canReconnect($throwable->getMessage())) {
                return $this->rowCount();
            }
            return $this->error($throwable);
        } finally {
            $this->connection->release($client ?? null);
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
            $client = $this->connection->getTransactionClient();
            if (($prepare = $client->prepare($this->sql)) === false) {
                throw new Exception($client->errorInfo()[1]);
            }
            if ($prepare->execute($this->params) === false) {
                throw new Exception($prepare->errorInfo()[1]);
            }
            $result = $client->lastInsertId();
            $prepare->closeCursor();

            if (!$client->inTransaction()) {
                $this->connection->release($client);
            }
            return $result == 0 ? true : (int)$result;
        } catch (Throwable $throwable) {
            if ($this->canReconnect($throwable->getMessage())) {
                return $this->_execute();
            }
            return $this->error($throwable);
        }
    }


    /**
     * @param string $message
     * @return bool
     */
    protected function canReconnect(string $message): bool
    {
        $errors = [
            'MySQL server has gone away',
            'Packets out of order. Expected 1 received 0.'
        ];
        foreach ($errors as $error) {
            if (str_contains($message, $error)) {
                return true;
            }
        }
        return false;
    }


    /**
     * @param Throwable $throwable
     * @return bool
     */
    private function error(Throwable $throwable): bool
    {
        $message = $this->sql . '.' . json_encode($this->params, JSON_UNESCAPED_UNICODE) . PHP_EOL . jTraceEx($throwable);
        return addError($message, 'mysql');
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
