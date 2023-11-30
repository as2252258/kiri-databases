<?php
declare(strict_types=1);

namespace Database;


use Exception;
use Kiri;
use Kiri\Abstracts\Providers;

/**
 * Class DatabasesProviders
 * @package Database
 */
class DatabasesProviders extends Providers
{


    /**
     * @var array
     */
    protected array $connections = [];


    /**
     * @return void
     * @throws
     */
    public function onImport(): void
    {
        $main = Kiri::getDi()->get(Kiri\Application::class);
        $main->command(BackupCommand::class, ImplodeCommand::class);
        $databases = \config('databases.connections', []);
        if (count($databases) < 1) {
            return;
        }
        foreach ($databases as $key => $database) {
            $this->set($key, $this->_settings($database));
        }
    }


    /**
     * @param $name
     * @return Connection
     * @throws Exception
     */
    public function get($name): Connection
    {
        return $this->connections[$name];
    }


    /**
     * @param $key
     * @param array $connection
     * @return void
     * @throws Exception
     */
    protected function set($key, array $connection): void
    {
        $this->connections[$key] = Kiri::createObject($connection);
    }


    /**
     * @param $database
     * @return array
     */
    private function _settings($database): array
    {
        $clientPool = $database['pool'] ?? ['min' => 1, 'max' => 5, 'tick' => 60];
        return [
            'id'          => $database['id'],
            'cds'         => $database['cds'],
            'class'       => Connection::class,
            'username'    => $database['username'],
            'password'    => $database['password'],
            'tablePrefix' => $database['tablePrefix'],
            'database'    => $database['database'],
            'timeout'     => $database['timeout'] ?? 10,
            'tick_time'   => $database['tick_time'] ?? 60,
            'waite_time'  => $database['waite_time'] ?? 3,
            'pool'        => $clientPool,
            'attributes'  => $database['attributes'] ?? [],
            'charset'     => $database['charset'] ?? 'utf8mb4'
        ];
    }


}
