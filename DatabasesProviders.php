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
		$this->provider->on(OnWorkerStart::class, [$this, 'check']);
		$this->provider->on(OnTaskerStart::class, [$this, 'check']);
		$this->provider->on(OnWorkerExit::class, [$this, 'exit']);
		foreach ($databases as $key => $database) {
			$application->set($key, $this->_settings($database));
		}
	}


	/**
	 * @param OnWorkerExit $exit
	 * @return void
	 */
	public function exit(OnWorkerExit $exit): void
	{
		$id = (int)Kiri\Context::getContext('db.loop.id');
		if (!empty($id)) {
			Timer::clear($id);
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
	 * @param OnTaskerStart|OnWorkerStart $start
	 * @return void
	 */
	public function check(OnTaskerStart|OnWorkerStart $start): void
	{
		$timerTick = Timer::tick(50 * 1000, static function () use ($start) {
			$databases = Config::get('databases.connections', []);
			$valid = 0;
			$count = 0;
			$logger = Kiri::getDi()->get(LoggerInterface::class);
			if (!empty($databases)) {
				$connection = Kiri::getDi()->get(PoolConnection::class);
				foreach ($databases as $database) {
					try {
						[$total, $success] = $connection->check($database['cds']);

						$count += $total;
						$valid += $success;

						if (isset($database['slaveConfig']) && isset($database['slaveConfig']['cds'])) {
							if ($database['slaveConfig']['cds'] != $database['cds']) {
								[$total, $success] = $connection->check($database['slaveConfig']['cds']);

								$count += $total;
								$valid += $success;
							}
						}
					} catch (\Throwable $throwable) {
						$logger->error($throwable->getMessage());
					}
				}
			}
			$logger->alert(sprintf('Worker %d db client has %d, valid %d', $start->workerId, $count, $valid));
		});
		Kiri\Context::setContext('db.loop.id', $timerTick);
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
