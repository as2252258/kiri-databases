<?php
declare(strict_types=1);

namespace Database;


use Exception;
use Kiri;
use Kiri\Abstracts\Providers;
use Kiri\Di\LocalService;

/**
 * Class DatabasesProviders
 * @package Database
 */
class DatabasesProviders extends Providers
{


    /**
     * @param LocalService $application
     * @return void
     * @throws Exception
     */
    public function onImport(LocalService $application): void
    {
        $main = Kiri::getDi()->get(Kiri\Application::class);
        $main->command(BackupCommand::class);

        $databases = \config('databases.connections', []);
        if (empty($databases)) {
            return;
        }
        foreach ($databases as $key => $database) {
            $application->set($key, Kiri::createObject($this->_settings($database)));
        }
    }


    /**
     * @param $name
     * @return Connection
     * @throws Exception
     */
    public function get($name): Connection
    {
        return Kiri::service()->get($name);
    }


    /**
     * @param $database
     * @return array
     */
    private function _settings($database): array
    {
        $clientPool = $database['pool'] ?? ['min' => 1, 'max' => 5, 'tick' => 60];
        return [
            'id'              => $database['id'],
            'cds'             => $database['cds'],
            'class'           => Connection::class,
            'username'        => $database['username'],
            'password'        => $database['password'],
            'tablePrefix'     => $database['tablePrefix'],
            'database'        => $database['database'],
            'connect_timeout' => $database['connect_timeout'] ?? 10,
            'idle_time'       => $database['idle_time'] ?? 300,
            'pool'            => $clientPool,
            'attributes'      => $database['attributes'] ?? [],
            'charset'         => $database['charset'] ?? 'utf8mb4',
            'slaveConfig'     => $database['slaveConfig']
        ];
    }


}
