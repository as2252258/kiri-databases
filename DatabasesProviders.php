<?php
declare(strict_types=1);

namespace Database;


use Exception;
use Kiri;
use Kiri\Abstracts\Config;
use Kiri\Abstracts\Providers;
use Kiri\Pool\Connection as PoolConnection;
use Kiri\Events\EventProvider;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Swoole\Timer;
use Kiri\Di\LocalService;
use Kiri\Di\Inject\Container;

/**
 * Class DatabasesProviders
 * @package Database
 */
class DatabasesProviders extends Providers
{

	/**
	 * @var EventProvider
	 */
	#[Container(EventProvider::class)]
	public EventProvider $provider;


	/**
	 * @param LocalService $application
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws Exception
	 */
	public function onImport(LocalService $application): void
	{
		$main = Kiri::getDi()->get(Kiri\Main::class);
		$main->command(BackupCommand::class);

		$databases = Config::get('databases.connections', []);
		if (empty($databases)) {
			return;
		}
		foreach ($databases as $key => $database) {
			$application->set($key, $this->_settings($database));
		}
	}


	public function start()
	{
		if (!Kiri\Di\Context::inCoroutine()) {
			return;
		}
		Timer::tick(60000, function () {
			$databases = Config::get('databases.connections', []);
			if (empty($databases)) {
				return;
			}

			$connection = Kiri::getDi()->get(PoolConnection::class);
			foreach ($databases as $database) {
				$connection->flush($database['cds'] . 'master', $database['pool']['min'] ?? 1);

				$slaveCds = ($database['slaveConfig']['cds'] ?? $database['cds']) . 'slave';

				$connection->flush($slaveCds, $database['pool']['min'] ?? 1);
			}
		});
	}


	/**
	 * @return void
	 * @throws Exception
	 */
	public function exit(): void
	{
		Timer::clearAll();
		$databases = Config::get('databases.connections', []);
		if (!empty($databases)) {
			$connection = Kiri::getDi()->get(PoolConnection::class);
			foreach ($databases as $database) {
				$connection->disconnect($database['cds'] . 'master');

				$slaveCds = ($database['slaveConfig']['cds'] ?? $database['cds']) . 'slave';

				$connection->disconnect($slaveCds);
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
