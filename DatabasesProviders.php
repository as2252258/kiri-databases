<?php
declare(strict_types=1);

namespace Database;


use Exception;
use Kiri;
use Kiri\Abstracts\Config;
use Kiri\Abstracts\Providers;
use Kiri\Pool\Connection as PoolConnection;
use Kiri\Exception\ConfigException;
use Kiri\Events\EventProvider;
use Kiri\Annotation\Inject;
use Kiri\Server\Events\OnWorkerStart;
use Kiri\Server\Events\OnTaskerStart;
use Psr\Log\LoggerInterface;
use Kiri\Server\Events\OnWorkerExit;
use Swoole\Timer;
use Kiri\Server\WorkerStatus;
use Kiri\Server\Abstracts\StatusEnum;
use Kiri\Di\LocalService;

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
	 * @param LocalService $application
	 * @return void
	 * @throws ConfigException
	 * @throws Exception
	 */
	public function onImport(LocalService $application): void
	{
		$databases = Config::get('databases.connections', []);
		if (empty($databases)) {
			return;
		}
		$this->provider->on(OnWorkerExit::class, [$this, 'exit'], 9999);
		$this->provider->on(OnWorkerStart::class, [$this, 'start']);
		foreach ($databases as $key => $database) {
			$application->set($key, $this->_settings($database));
		}
	}


	public function start(OnWorkerStart $start)
	{
		Timer::tick(60000, function () {
			$databases = Config::get('databases.connections', []);
			if (empty($databases)) {
				return;
			}

			$min = Config::get('databases.pool.min', 5);

			$connection = Kiri::getDi()->get(PoolConnection::class);
			foreach ($databases as $database) {
				$connection->flush($database['cds'], $min);
			}

			$this->logger->warning("database tick clear.");
		});
	}


	/**
	 * @param OnWorkerExit $exit
	 * @return void
	 * @throws ConfigException
	 * @throws Exception
	 */
	public function exit(OnWorkerExit $exit): void
	{
		Timer::clearAll();
		$databases = Config::get('databases.connections', []);
		if (!empty($databases)) {
			$connection = Kiri::getDi()->get(PoolConnection::class);
			foreach ($databases as $database) {
				$connection->disconnect($database['cds']);
			}
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
			'connect_timeout' => $database['connect_timeout'] ?? 30,
			'read_timeout'    => $database['read_timeout'] ?? 10,
			'pool'            => $clientPool,
			'attributes'      => $database['attributes'] ?? [],
			'charset'         => $database['charset'] ?? 'utf8mb4',
			'slaveConfig'     => $database['slaveConfig']
		];
	}


}
