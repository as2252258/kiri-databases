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


	public string $description = './snowflake sw:gii make=model|controller|task|interceptor|limits|middleware name=xxxx';


	private LocalService $service;


	/**
	 *
	 */
	protected function configure()
	{
		$this->service = \Kiri::getDi()->get(LocalService::class);
		$this->setName('db:backup')
			->addOption('data', 'd', InputArgument::OPTIONAL)
			->addOption('path', 'p', InputArgument::REQUIRED)
			->addOption('table', 't', InputArgument::OPTIONAL)
			->addOption('database', 'db', InputArgument::OPTIONAL)
			->setDescription('php kiri.php sw:backup --database users --data 1');
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

			$tableInfo = $data->createCommand('show create DATABASE `' . $data->database . '`')->one();

			file_put_contents($input->getOption('path'), '');
			file_put_contents($input->getOption('path'), current($tableInfo) . PHP_EOL, FILE_APPEND);
			foreach ($table as $value) {
				$tableInfo = $data->createCommand('show create table `' . $data->database . '`.`' . $value . '`')->one();

				file_put_contents($input->getOption('path'), current($tableInfo) . PHP_EOL, FILE_APPEND);
			}
		} catch (\Throwable $throwable) {
			$output->writeln($throwable->getMessage());
		} finally {
			return 1;
		}
	}

}
