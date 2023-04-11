<?php

namespace Database;

use Exception;
use Kiri\Di\LocalService;
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

			if (!is_dir($path)) {
				mkdir($path);
			}

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
			}
		} catch (\Throwable $throwable) {
			$output->writeln($throwable->getMessage());
		} finally {
			return 1;
		}
	}

}
