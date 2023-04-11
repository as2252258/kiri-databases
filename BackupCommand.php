<?php

namespace Database;

use Co\Channel;
use Exception;
use Kiri\Di\LocalService;
use Swoole\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


/**
 *
 */
class BackupCommand extends Command
{

	public string $command = 'db:backup';


	public string $description = 'php kiri.php db:backup --database users --table u_user --data 1 /Users/admin/snowflake-bi/test.sql';


	private LocalService $service;


	/**
	 *
	 */
	protected function configure()
	{
		$this->service = \Kiri::getDi()->get(LocalService::class);
		$this->setName('db:backup')
			->addOption('data', 'd', InputArgument::OPTIONAL)
			->addArgument('path', InputArgument::REQUIRED, "save to path", null)
			->addOption('table', 't', InputArgument::OPTIONAL)
			->addOption('database', 'db', InputArgument::OPTIONAL)
			->setDescription('php kiri.php db:backup --database users --table u_user --data 1 /Users/admin/snowflake-bi/test.sql');
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 * @throws Exception
	 */
	public function execute(InputInterface $input, OutputInterface $output): int
	{
		try {
			/** @var Connection $data */
			$data = $this->service->get($input->getOption('database'));

			$table = $input->getOption('table');
			if ($table !== null) {
				$table = explode(',', $table);
			} else {
				$tmp = $data->createCommand('show tables from `' . $data->database . '`')->all();
				$table = [];
				foreach ($tmp as $value) {
					$table[] = current($value);
				}
			}

			$databaseInfo = $data->createCommand('show create DATABASE `' . $data->database . '`')->one();

			$database = next($databaseInfo);

			$path = $input->getArgument('path');

			$data = $input->getOption('data');

			if (!is_dir($path)) {
				mkdir($path);
			}


			$processes = [];
			foreach ($table as $value) {
				$tableInfo = $data->createCommand('show create table `' . $data->database . '`.`' . $value . '`')->one();

				$tmp = rtrim($path, '/') . '/' . current($tableInfo) . '.sql';
				if (!file_exists($tmp)) {
					touch($tmp);
				}

				file_put_contents($tmp, '');
				file_put_contents($tmp, $database . ';' . PHP_EOL, FILE_APPEND);

				$tableCreator = next($tableInfo);
				if (preg_match('/AUTO_INCREMENT=\d+\s/', $tableCreator)) {
					$tableCreator = preg_replace('/AUTO_INCREMENT=\d+\s/', 'AUTO_INCREMENT=1 ', $tableCreator);
				}

				file_put_contents($tmp, $tableCreator . ';' . PHP_EOL . PHP_EOL, FILE_APPEND);

				if ($data == 1) {
					$process = new Process(fn(Process $process) => $this->writeData($process, $database, $value), false,
						2, true);
					$process->start();

					$processes[] = $process;
				}
			}
			foreach ($processes as $process) {
				Process::wait();
			}
		} catch (\Throwable $throwable) {
			$output->writeln($throwable->getMessage());
		} finally {
			return 1;
		}
	}


	/**
	 * @param Process $process
	 * @param string $dbname
	 * @param string $value
	 * @return void
	 * @throws Exception
	 */
	public function writeData(Process $process, string $dbname, string $tableName, string $path): void
	{
		$offset = 0;
		$size = 1000;

		$channel = new Channel(200);
		for ($i = 0; $i < $channel->length(); $i++) {
			go(function () use ($channel, $path, $dbname, $tableName) {
				while ($channel->errCode != SWOOLE_CHANNEL_CLOSED) {
					$value = $channel->pop();

					$value = $this->toSql($dbname, $tableName, $value);

					file_put_contents($path, $value . PHP_EOL, FILE_APPEND);
				}
			});
		}

		/** @var Connection $database */
		$database = \Kiri::service()->get($dbname);
		while (true) {
			$data = $database->createCommand("SELECT * FROM $tableName LIMIT $offset,$size")->all();
			$channel->push($data);
			if (count($data) < $size) {
				break;
			}
			$offset += $size;
		}

		$channel->close();

		$process->exit(0);
	}


	public function toSql(string $dbname, string $value, array $data): string
	{
		$strings = ['INSERT INTO ' . $dbname . '.' . $value];
		foreach ($data as $datum) {

			if (count($strings) == 1) {
				$keys = array_keys($datum);
				$strings[] = '(' . implode(',', $keys) . ') VALUES';
			} else {
				$keys = array_values($datum);
				$strings[] = '(' . implode(',', $keys) . '),';
			}
		}
		return implode('', $strings);
	}

}
