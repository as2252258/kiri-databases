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
use Exception;
use Kiri;
use Kiri\Server\Events\OnWorkerExit;
use Kiri\Abstracts\Component;
use Kiri\Di\Context;
use Kiri\Pool\Pool;
use Kiri\Events\EventProvider;
use PDO;
use Kiri\Error\StdoutLogger;
use Psr\Log\LoggerInterface;
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

    public string   $id          = 'db';
    public string   $cds         = '';
    public string   $password    = '';
    public string   $username    = '';
    public string   $charset     = 'utf-8';
    public string   $tablePrefix = '';
    public string   $database    = '';
    public int      $timeout     = 30;
    public int      $waite_time  = 3;
    public int      $tick_time   = 60;
    public int      $idle_count  = 3;
    public int      $idle_time   = 60;
    public array    $pool        = ['max' => 10, 'min' => 1];
    private int     $storey      = 0;
    protected int   $timerId     = -1;
    public bool     $enableCache = false;
    public string   $cacheDriver = 'redis';
    public array    $attributes  = [];


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
     * @throws
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
        $this->timerId = Timer::tick($this->tick_time, fn() => $this->checkClientHealth($this->pool()));
    }


    /**
     * @param Pool $pool
     * @return void
     * @throws
     */
    protected function checkClientHealth(Pool $pool): void
    {
        $pool->flush($this->cds, $this->pool['min'] ?? 1);
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
            }
        }
    }


    /**
     * @param Pool $pool
     * @return PDO|bool
     * @throws
     */
    protected function validator(Pool $pool): PDO|bool
    {
        /** @var $client PDO */
        if (($client = $pool->get($this->cds)) === false) {
            return false;
        }
        if ($client->query('select 1') === false) {
            throw new Exception($client->errorInfo()[1]);
        }
        return $client;
    }


    /**
     * @return PDO
     * @throws
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
     * @throws
     */
    protected function getNormalClientHealth(): PDO
    {
        $data = $this->pool()->get($this->cds, $this->waite_time);
        if ($data === false) {
            throw new Exception('Client Waite timeout.');
        }
        return $data;
    }


    /**
     * @return $this
     * @throws
     */
    public function beginTransaction(): static
    {
        if ($this->storey < 0) {
            $this->storey = 0;
        }
        $this->storey++;
        return $this;
    }


    /**
     * @return PDO
     * @throws
     */
    public function getTransactionClient(): PDO
    {
        $pdo = Context::get($this->cds);
        if ($pdo === null) {
            /** @var PDO $pdo */
            $pdo = Context::set($this->cds, $this->getNormalClientHealth());
        }
        if ($this->storey > 0 && !$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }
        return $pdo;
    }


    /**
     * @return bool
     * @throws
     */
    public function inTransaction(): bool
    {
        return $this->storey > 0;
    }


    /**
     * @throws
     * 事务回滚
     */
    public function rollback(): void
    {
        $this->storey--;
        if ($this->storey == 0) {
            if (!Context::exists($this->cds)) {
                return;
            }
            $pdo = $this->getTransactionClient();
            if ($pdo->inTransaction()) {
                $pdo->rollback();
            }
            $this->release($pdo);
        }
    }

    /**
     * @throws
     * 事务提交
     */
    public function commit(): void
    {
        $this->storey--;
        if ($this->storey == 0) {
            if (!Context::exists($this->cds)) {
                return;
            }
            $pdo = $this->getTransactionClient();
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            $this->release($pdo);
        }
    }


    /**
     * @return void
     * @throws
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
        $this->release($pdo);
    }


    /**
     * @param string $sql
     * @param array $attributes
     * @return Command
     * @throws
     */
    public function createCommand(string $sql, array $attributes = []): Command
    {
        return (new Command(['connection' => $this, 'sql' => $sql]))->bindValues($attributes);
    }


    /**
     * 回收链接
     * @throws
     */
    public function release(PDO $pdo): void
    {
        if (!$this->inTransaction()) {
            $this->pool()->push($this->cds, $pdo);
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
     * @throws
     */
    public function disconnect(): void
    {
        if ($this->timerId > -1) {
            Timer::clear($this->timerId);
        }
        $this->pool()->close($this->cds);
    }


    /**
     * @return PDO
     */
    public function newConnect(): PDO
    {
        $pdo = new PDO('mysql:dbname=' . $this->database . ';host=' . $this->cds, $this->username, $this->password, [
            PDO::ATTR_CASE               => PDO::CASE_NATURAL,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_ORACLE_NULLS       => PDO::NULL_NATURAL,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::ATTR_EMULATE_PREPARES   => true,
            PDO::ATTR_TIMEOUT            => $this->timeout,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . $this->charset
        ]);
        foreach ($this->attributes as $key => $attribute) {
            $pdo->setAttribute($key, $attribute);
        }
        return $pdo;
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
