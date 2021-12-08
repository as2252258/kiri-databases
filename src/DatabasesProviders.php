<?php
declare(strict_types=1);

namespace Database;


use Exception;
use Kiri\Abstracts\Config;
use Kiri\Abstracts\Providers;
use Kiri\Application;
use Kiri\Events\EventProvider;
use Kiri\Exception\ConfigException;
use Kiri\Kiri;
use Server\Events\OnWorkerStart;

/**
 * Class DatabasesProviders
 * @package Database
 */
class DatabasesProviders extends Providers
{

	private array $_pooLength = ['min' => 0, 'max' => 1];


	/**
	 * @param Application $application
	 * @throws Exception
	 */
	public function onImport(Application $application)
	{
		$application->set('db', $this);

		$this->_pooLength = Config::get('databases.pool', ['min' => 0, 'max' => 1]);

		$this->eventProvider->on(OnWorkerStart::class, [$this, 'createPool']);
	}


	/**
	 * @param $name
	 * @return Connection
	 * @throws ConfigException
	 * @throws Exception
	 */
	public function get($name): Connection
	{
		$config = $this->_settings($this->getConfig($name));

		return Kiri::getDi()->get(Connection::class)->configure($config);
	}


	/**
	 * @throws ConfigException
	 * @throws Exception
	 */
	public function createPool(OnWorkerStart $onWorkerStart)
	{
		$databases = Config::get('databases.connections', []);
		if (empty($databases)) {
			return;
		}
		$connection = Kiri::getDi()->get(Connection::class);
		foreach ($databases as $database) {
			/** @var Connection $connection */
			$connection->configure($database)->fill();
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


	/**
	 * @param $name
	 * @return mixed
	 * @throws ConfigException
	 */
	public function getConfig($name): mixed
	{
		return Config::get('databases.connections.' . $name, null, true);
	}


}
