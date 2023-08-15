<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/3/30 0030
 * Time: 14:09
 */
declare(strict_types=1);


namespace Database;


use Closure;
use Database\Affair\BeginTransaction;
use Database\Affair\Commit;
use Database\Affair\Rollback;
use Database\Mysql\Schema;
use Exception;
use Kiri;
use Kiri\Abstracts\Component;
use Kiri\Di\Context;
use Kiri\Pool\Pool;
use Kiri\Events\EventProvider;
use Kiri\Exception\NotFindClassException;
use PDO;
use ReflectionException;

/**
 * Class Connection
 * @package Database
 */
class Connection extends Component
{

    public string $id       = 'db';
    public string $cds      = '';
    public string $password = '';
    public string $username = '';
    public string $charset  = 'utf-8';

    public string $tablePrefix = '';

    public string $database = '';

    public int $connect_timeout = 30;

    public int $read_timeout = 10;

    public array $pool = ['max' => 10, 'min' => 1];


    private int $storey = 0;

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
    public array $slaveConfig = [];
    public array $attributes  = [];


    private ?Schema $_schema = null;


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
                'db'    => $this
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
        return $this->pool()->get($this->cds);
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
            if ($pdo !== null && !$pdo->inTransaction()) {
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
            $pdo = $this->getConnection();
            if ($this->storey > 0 && !$pdo->inTransaction()) {
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
            $pdo = Context::get($this->cds);
            if ($pdo === null) {
                return;
            }
            if ($pdo->inTransaction()) {
                $pdo->rollback();
            }
            $this->pool()->push($this->cds, $pdo);
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
                return;
            }
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            $this->pool()->push($this->cds, $pdo);
            Context::remove($this->cds);
        }
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
     *
     * 回收链接
     * @throws
     */
    public function release(?PDO $PDO): void
    {
        $this->pool()->push($this->cds, $PDO);
    }


    /**
     *
     * 回收链接
     * @throws
     */
    public function clear_connection(): void
    {
        $this->pool()->clean($this->cds);
    }


    /**
     * @throws Exception
     */
    public function disconnect(): void
    {
        $this->pool()->close($this->cds);
    }


    /**
     * @return PDO
     */
    public function newConnect(): PDO
    {
        $options = [
            PDO::ATTR_CASE               => PDO::CASE_NATURAL,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_ORACLE_NULLS       => PDO::NULL_NATURAL,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::ATTR_EMULATE_PREPARES   => true,
            PDO::ATTR_TIMEOUT            => $this->connect_timeout,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . $this->charset
        ];
        if (!Context::inCoroutine()) {
            $options[PDO::ATTR_PERSISTENT] = true;
        } else {
            $options[PDO::ATTR_PERSISTENT] = false;
        }
        $link = new PDO('mysql:dbname=' . $this->database . ';host=' . $this->cds,
            $this->username, $this->password, $options);
        foreach ($this->attributes as $key => $attribute) {
            $link->setAttribute($key, $attribute);
        }
        return $link;
    }


    /**
     * @return Pool
     */
    private function pool(): Pool
    {
        if (!$this->connections->hasChannel($this->cds)) {
            $this->connections->created($this->cds, $this->pool['max'] ?? 1, [$this, 'newConnect']);
        }
        return $this->connections;
    }

}
