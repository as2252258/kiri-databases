<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/3/30 0030
 * Time: 14:09
 */
declare(strict_types=1);


namespace Database;


use Database\Affair\BeginTransaction;
use Database\Affair\Commit;
use Database\Affair\Rollback;
use Database\Mysql\Schema;
use Exception;
use Kiri;
use Kiri\Server\Events\OnWorkerExit;
use Kiri\Waite;
use Kiri\Abstracts\Component;
use Kiri\Di\Context;
use Kiri\Pool\Pool;
use Kiri\Events\EventProvider;
use Kiri\Exception\NotFindClassException;
use PDO;
use Kiri\Error\StdoutLogger;
use Psr\Log\LoggerInterface;
use ReflectionException;
use Kiri\Server\Events\OnWorkerStart;
use Kiri\Server\Events\OnTaskerStart;
use Kiri\Server\Events\OnAfterRequest;
use Kiri\Di\Inject\Container;
use Swoole\Timer;

/**
 * Class Connection
 * @package Database
 */
class Connection extends Component
{

    public string $id = 'db';
    public string $cds = '';
    public string $password = '';
    public string $username = '';
    public string $charset = 'utf-8';

    public string $tablePrefix = '';

    public string $database = '';

    public int $connect_timeout = 30;


    public int $waite_time = 3;

    public int $idle_time = 60;

    public array $pool = ['max' => 10, 'min' => 1];


    private int $storey = 0;

    protected int $timerId = -1;

    /**
     * @var bool
     * enable database cache
     */
    public bool $enableCache = false;


    private ?PDO $_pdo = null;


    /**
     * @var string
     */
    public string $cacheDriver = 'redis';

    /**
     * @var array
     */
    public array $attributes = [];


    /**
     * @var Schema|null
     */
    private ?Schema $_schema = null;


    /**
     * @var StdoutLogger
     */
    #[Container(LoggerInterface::class)]
    public StdoutLogger $logger;

    /**
     * @param Pool $connections
     */
    public function __construct(public Pool $connections)
    {
        parent::__construct();
    }


    /**
     * @return void
     * @throws Exception
     */
    public function init(): void
    {
        $eventProvider = Kiri::getDi()->get(EventProvider::class);
        $eventProvider->on(BeginTransaction::class, [$this, 'beginTransaction'], 0);
        $eventProvider->on(Rollback::class, [$this, 'rollback'], 0);
        $eventProvider->on(Commit::class, [$this, 'commit'], 0);
        $eventProvider->on(OnAfterRequest::class, [$this, 'clear']);
        $eventProvider->on(OnWorkerExit::class, [$this, 'disconnect']);
        $eventProvider->on(OnWorkerStart::class, [$this, 'tick']);
        $eventProvider->on(OnTaskerStart::class, [$this, 'tick']);
    }


    /**
     * @return void
     */
    public function tick(): void
    {
        $this->timerId = Timer::tick(120000, fn() => $this->checkClientHealth($this->pool()));
    }


    /**
     * @param Pool $pool
     * @return void
     * @throws Exception
     */
    protected function checkClientHealth(Pool $pool): void
    {
        $length = $pool->size($this->cds);
        for ($i = 0; $i < $length; $i++) {
            try {
                if (($client = $this->validator($pool)) === false) {
                    break;
                }
                $pool->push($this->cds, $client);
            } catch (\Throwable $exception) {
                if (!str_contains($exception->getMessage(), 'Client timeout.')) {
                    $this->logger->error(throwable($exception), [$this->cds]);
                }
                $pool->abandon($this->cds);
            }
        }
    }


    /**
     * @param Pool $pool
     * @return PDO|bool
     * @throws Exception
     */
    protected function validator(Pool $pool): PDO|bool
    {
        if (($bool = $pool->get($this->cds)) === false) {
            return false;
        }
        /** @var PDO $client */
        [$client, $time] = $bool;
        if ($client->query('select 1') === false) {
            throw new Exception($client->errorInfo()[1]);
        }
        return $client;
    }


    /**
     * @return mixed
     * @throws ReflectionException
     * @throws NotFindClassException
     * @throws Exception
     */
    public function getSchema(): Schema
    {
        if ($this->_schema === null) {
            $this->_schema = Kiri::createObject([
                'class' => Schema::class,
                'db' => $this
            ]);
        }
        return $this->_schema;
    }


    /**
     * @return PDO
     * @throws Exception
     */
    public function getConnection(): PDO
    {
        if (!$this->inTransaction()) {
            return $this->getNormalClientHealth();
        } else {
            return $this->getTransactionClient();
        }
    }


    /**
     * @return PDO
     * @throws Exception
     */
    protected function getNormalClientHealth(): PDO
    {
        $data = $this->pool()->get($this->cds, $this->waite_time);
        if ($data === false) {
            throw new Exception('Client Waite timeout.');
        }
        return $data[0];
    }


    /**
     * @return $this
     * @throws Exception
     */
    public function beginTransaction(): static
    {
        if ($this->storey == 0) {
            /** @var PDO $pdo */
            $pdo = Context::get($this->cds);
            if ($pdo == null) {
                $pdo = $this->getTransactionClient();
            }
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
            }
        }
        $this->storey++;
        return $this;
    }


    /**
     * @return PDO
     * @throws Exception
     */
    public function getTransactionClient(): PDO
    {
        $pdo = Context::get($this->cds);
        if ($pdo === null) {
            $pdo = $this->getNormalClientHealth();
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
            }
            Context::set($this->cds, $pdo);
        }
        return $pdo;
    }


    /**
     * @return bool
     * @throws Exception
     */
    public function inTransaction(): bool
    {
        return $this->storey > 0;
    }


    /**
     * @throws Exception
     * 事务回滚
     */
    public function rollback(): void
    {
        $this->storey--;
        if ($this->storey == 0) {
            /** @var PDO $pdo */
            $pdo = Context::get($this->cds);
            if ($pdo === null) {
                throw new Exception('Failed to rollback transaction: connection was exists.');
            }
            if ($pdo->inTransaction()) {
                $pdo->rollback();
            }
            $this->pool()->push($this->cds, [$pdo, time()]);
            Context::remove($this->cds);
        }
    }

    /**
     * @throws Exception
     * 事务提交
     */
    public function commit(): void
    {
        $this->storey--;
        if ($this->storey == 0) {
            $pdo = Context::get($this->cds);
            if ($pdo === null) {
                throw new Exception('Failed to commit transaction: connection was exists.');
            }
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            $this->pool()->push($this->cds, [$pdo, time()]);
            Context::remove($this->cds);
        }
    }


    /**
     * @return void
     * @throws Exception
     */
    public function clear(): void
    {
        /** @var PDO $pdo */
        $pdo = Context::get($this->cds);
        if ($pdo === null) {
            return;
        }
        if ($this->inTransaction()) {
            $pdo->rollback();
        }
        $this->pool()->push($this->cds, [$pdo, time()]);
        Context::remove($this->cds);
    }


    /**
     * @param null $sql
     * @param array $attributes
     * @return Command
     * @throws Exception
     */
    public function createCommand($sql = null, array $attributes = []): Command
    {
        $command = new Command(['connection' => $this, 'sql' => $sql]);
        return $command->bindValues($attributes);
    }


    /**
     * 回收链接
     * @throws
     */
    public function release(PDO $PDO): void
    {
        if (!$this->inTransaction()) {
            $this->pool()->push($this->cds, [$PDO, time()]);
        }
    }


    /**
     *
     * 回收链接
     * @throws
     */
    public function clear_connection(): void
    {
        $this->pool()->flush($this->cds, 0);
    }


    /**
     * @throws Exception
     */
    public function disconnect(): void
    {
        if ($this->timerId > -1) {
            Timer::clear($this->timerId);
        }
        $this->pool()->close($this->cds);
    }


    /**
     * @return array<PDO, int>
     */
    public function newConnect(): array
    {
        return [new PDO('mysql:dbname=' . $this->database . ';host=' . $this->cds,
            $this->username, $this->password, array_merge($this->attributes, [
                PDO::ATTR_CASE => PDO::CASE_NATURAL,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_EMULATE_PREPARES => true,
                PDO::ATTR_TIMEOUT => $this->connect_timeout,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . $this->charset
            ])), time()];
    }


    /**
     * @return Pool
     */
    protected function pool(): Pool
    {
        if (!$this->connections->hasChannel($this->cds)) {
            $this->connections->created($this->cds, $this->pool['max'] ?? 1, [$this, 'newConnect']);
        }
        return $this->connections;
    }

}
