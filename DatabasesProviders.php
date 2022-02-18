<?php
declare(strict_types=1);

namespace Database;


use Exception;
use Kiri;
use Kiri\Abstracts\Config;
use Kiri\Abstracts\Providers;
use Kiri\Application;
use Kiri\Events\OnBeforeCommandExecute;
use Kiri\Exception\ConfigException;
use Kiri\Server\Events\OnServerBeforeStart;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Class DatabasesProviders
 * @package Database
 */
class DatabasesProviders extends Providers
{


	/**
	 * @param Application $application
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function onImport(Application $application)
	{
		$this->getEventProvider()->on(OnServerBeforeStart::class, [$this, 'createPool']);
	}


	/**
	 * @param $name
	 * @return Connection
	 * @throws Exception
	 */
	public function get($name): Connection
	{
		return Kiri::app()->get($name);
	}


	/**
	 * @throws ConfigException
	 * @throws Exception
	 */
	public function createPool(OnBeforeCommandExecute $onWorkerStart)
	{
		$databases = Config::get('databases.connections', []);
		if (empty($databases)) {
			return;
		}

		$app = Kiri::app();
		foreach ($databases as $key => $database) {
			$database = $this->_settings($database);

			$connection = Kiri::getDi()->create(Connection::class, [$database]);
			$connection->fill();

			$app->set($key, $connection);
		}
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
			'username'        => $database['username'],
			'password'        => $database['password'],
			'tablePrefix'     => $database['tablePrefix'],
			'database'        => $database['database'],
			'connect_timeout' => $database['connect_timeout'] ?? 30,
			'read_timeout'    => $database['read_timeout'] ?? 10,
			'pool'            => $clientPool,
			'attributes'      => $database['attributes'] ?? [],
			'charset'         => $database['charset'] ?? 'utf8mb4',
			'slaveConfig'     => $database['slaveConfig']
		];
	}


}
