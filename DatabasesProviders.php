<?php
declare(strict_types=1);

namespace Database;


use Exception;
use Kiri;
use Kiri\Abstracts\Config;
use Kiri\Abstracts\Providers;
use Kiri\Application;
use Kiri\Pool\Connection as PoolConnection;
use Kiri\Exception\ConfigException;
use Kiri\Events\EventProvider;
use Kiri\Annotation\Inject;
use Kiri\Server\Events\OnWorkerStart;
use Kiri\Server\Events\OnTaskerStart;
use Psr\Log\LoggerInterface;

/**
 * Class DatabasesProviders
 * @package Database
 */
class DatabasesProviders extends Providers
{


	/**
	 * @var EventProvider
	 */
	#[Inject(EventProvider::class)]
	public EventProvider $provider;


	/**
	 * @param Application $application
	 * @return void
	 * @throws ConfigException
	 * @throws Exception
	 */
	public function onImport(Application $application): void
	{
		$databases = Config::get('databases.connections', []);
		if (empty($databases)) {
			return;
		}
		$this->provider->on(OnWorkerStart::class, [$this, 'check']);
		$this->provider->on(OnTaskerStart::class, [$this, 'check']);
		foreach ($databases as $key => $database) {
			$application->set($key, $this->_settings($database));
		}
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
	 * @param OnTaskerStart|OnWorkerStart $start
	 * @return void
	 */
	public function check(OnTaskerStart|OnWorkerStart $start): void
	{
		$start->server->tick(50 * 1000, static function () use ($start) {
			$databases = Config::get('databases.connections', []);
			$logger = Kiri::getDi()->get(LoggerInterface::class);
			$logger->alert('db size ' . count($databases) . ' ticker ' . date('Y-m-d H:i:s'));
			if (!empty($databases)) {
				$valid = 0;
				$count = 0;

				$connection = Kiri::getDi()->get(PoolConnection::class);
				foreach ($databases as $database) {
					$count += 1;

					$success = $connection->check($database['cds']);
					if ($success) {
						$valid += 1;
					}
					if (isset($database['slaveConfig']) && isset($database['slaveConfig']['cds'])) {
						if ($database['slaveConfig']['cds'] != $database['cds']) {
							$count += 1;
							$success = $connection->check($database['slaveConfig']['cds']);
							if ($success) {
								$valid += 1;
							}
						}
					}
				}

				$message = sprintf('Worker %d db client has %d, valid %d', $start->workerId, $count, $valid);
				$logger->alert($message);
			}
		});
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
			'connect_timeout' => $database['connect_timeout'] ?? 30,
			'read_timeout'    => $database['read_timeout'] ?? 10,
			'pool'            => $clientPool,
			'attributes'      => $database['attributes'] ?? [],
			'charset'         => $database['charset'] ?? 'utf8mb4',
			'slaveConfig'     => $database['slaveConfig']
		];
	}


}
