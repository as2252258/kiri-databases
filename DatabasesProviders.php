<?php
declare(strict_types=1);

namespace Database;


use Exception;
use Kiri;
use Kiri\Abstracts\Config;
use Kiri\Abstracts\Providers;
use Swoole\Timer;
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
	 * @throws \ReflectionException
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


	public function start(): void
	{
		if (!Kiri\Di\Context::inCoroutine()) {
			return;
		}
		Timer::tick(60000, function () {
			$databases = Config::get('databases.connections', []);
			if (empty($databases)) {
				return;
			}

			$connection = Kiri::getDi()->get(Kiri\Pool\Pool::class);
			foreach ($databases as $database) {
				$connection->flush($database['cds'], $database['pool']['min'] ?? 1);
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
			$connection = Kiri::getDi()->get(Kiri\Pool\Pool::class);
			foreach ($databases as $database) {
				$connection->clean($database['cds']);
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
