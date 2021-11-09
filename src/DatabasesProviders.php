<?php
declare(strict_types=1);

namespace Database;


use Annotation\Inject;
use Exception;
use Server\Events\OnWorkerStart;
use Kiri\Abstracts\Config;
use Kiri\Abstracts\Providers;
use Kiri\Application;
use Kiri\Events\EventProvider;
use Kiri\Exception\ConfigException;
use Kiri\Kiri;

/**
 * Class DatabasesProviders
 * @package Database
 */
class DatabasesProviders extends Providers
{

	private array $_pooLength = ['min' => 0, 'max' => 1];


	/**
	 * @var EventProvider
	 */
	#[Inject(EventProvider::class)]
	public EventProvider $eventProvider;


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
		$application = Kiri::app();
		if (!$application->has('databases.' . $name)) {
			$application->set('databases.' . $name, $this->_settings($this->getConfig($name)));
		}
		var_dump($application->get('databases.' . $name));
		return $application->get('databases.' . $name);
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
		$application = Kiri::app();
		foreach ($databases as $name => $database) {
			/** @var Connection $connection */
			$application->set('databases.' . $name, $this->_settings($database));
			$database = $application->get('databases.' . $name);
			$database->fill();
		}
	}


	/**
	 * @param $database
	 * @return array
	 */
	private function _settings($database): array
	{
		return [
			'class'       => Connection::class,
			'id'          => $database['id'],
			'cds'         => $database['cds'],
			'username'    => $database['username'],
			'password'    => $database['password'],
			'tablePrefix' => $database['tablePrefix'],
			'database'    => $database['database'],
			'maxNumber'   => $this->_pooLength['max'],
			'minNumber'   => $this->_pooLength['min'],
			'charset'     => $database['charset'] ?? 'utf8mb4',
			'slaveConfig' => $database['slaveConfig']
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
