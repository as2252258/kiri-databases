<?php
declare(strict_types=1);

namespace Database;

use Co\Channel;
use Exception;
use Kiri\Di\LocalService;
use Swoole\Coroutine;
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
	
	public array $percentStatus = [];
	
	
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
				$tmp   = $data->createCommand('show tables from `' . $data->database . '`')->all();
				$table = [];
				foreach ($tmp as $value) {
					$table[] = current($value);
				}
			}

			$path = $input->getArgument('path');
			if (!str_starts_with($path, '/')) {
				$path = APP_PATH . $path;
			}
			
			$isData = $input->getOption('data');
			if (!is_dir($path)) {
				mkdir($path);
			}
			
			$waite = new Coroutine\WaitGroup();
			foreach ($table as $value) {
				$tableInfo = $data->createCommand('show create table `' . $data->database . '`.`' . $value . '`')->one();
				
				$tmp = rtrim($path, '/') . '/' . current($tableInfo) . '.sql';
				if (!file_exists($tmp)) {
					touch($tmp);
				}
				
				file_put_contents($tmp, '');

				$tableCreator = next($tableInfo);
				if (preg_match('/AUTO_INCREMENT=\d+\s/', $tableCreator)) {
					$tableCreator = preg_replace('/AUTO_INCREMENT=\d+\s/', 'AUTO_INCREMENT=1 ', $tableCreator);
				}
				
				file_put_contents($tmp, $tableCreator . ';' . PHP_EOL . PHP_EOL, FILE_APPEND);
				
				if ($isData == 1) {
					$waite->add(1);
					Coroutine::create(function () use ($waite, $input, $value, $tmp) {
						defer(function () use ($waite) {
							$waite->done();
						});
						$this->writeData( $input->getOption('database'), $value, $tmp);
					});
				}
			}
			
			$waite->wait();
			
			$output->write('dump data success');
		} catch (\Throwable $throwable) {
			$output->writeln(throwable($throwable));
		} finally {
			return 1;
		}
	}
	
	
	public bool $isEnd = false;
	
	
	/**
	 * @param string $dbname
	 * @param string $tableName
	 * @param string $path
	 * @return void
	 * @throws Exception
	 */
	public function writeData(string $dbname, string $tableName, string $path): void
	{
		$offset = 0;
		$size   = 1000;
		
		$channel = new Channel(200);
		for ($i = 0; $i < 200; $i++) {
			Coroutine::create(function () use ($channel, $path, $dbname, $tableName) {
				while ($channel->errCode != SWOOLE_CHANNEL_CLOSED) {
                    if (($data = $channel->pop()) === false) {
                        break;
                    }
					if (($value = json_decode($data, true)) === null) {
						continue;
					}
					
					$value = $this->toSql($dbname, $tableName, $value);
					
					file_put_contents($path, $value . PHP_EOL, FILE_APPEND);
				}
			});
		}
		
		$id = Coroutine::create(function () use ($channel) {
			$data = Coroutine::waitSignal(SIGTERM | SIGINT);
			if ($data) {
				$this->isEnd = true;
			}
		});
		
		/** @var Connection $database */
		$database = \Kiri::service()->get($dbname);
		
		$total = $database->createCommand("SELECT COUNT(*) as total FROM " . $tableName)->one()['total'];
		
		$startTime = time();
		
		$wait = new Coroutine\WaitGroup();
		while ($this->isEnd === false && $offset < $total) {
			$wait->add(1);
			Coroutine::create(function () use ($wait, $offset, $size, $total, $dbname, $tableName, $channel) {
				defer(function () use ($wait) {
					$wait->done();
				});
				/** @var Connection $database */
				$database = \Kiri::service()->get($dbname);
				
				$data = $database->createCommand("SELECT * FROM $tableName LIMIT $offset,$size")->all();
				if (is_bool($data) || count($data) < 1) {
					return;
				}
				$channel->push(json_encode($data));
				
				$this->percentStatus[$dbname . $tableName] = intval(round(($offset + $size) / $total, 2) * 100);
				
				$this->outputProgress(true);
			});
			$offset += $size;
		}
		
		$wait->wait();
		
		echo 'use time ' . (time() - $startTime) . 's' . PHP_EOL;
		
		$channel->close();
		
		Coroutine::cancel($id);
	}
	
	
	/**
	 * @param string $dbname
	 * @param string $value
	 * @param array $data
	 * @return string
	 */
	public function toSql(string $dbname, string $value, array $data): string
	{
		$strings = ['INSERT INTO ' . $dbname . '.' . $value];
		foreach ($data as $datum) {
			
			if (count($strings) == 1) {
				$keys      = array_keys($datum);
				$strings[] = '(' . implode(',', $keys) . ') VALUES';
			} else {
				$keys   = array_values($datum);
				$encode = [];
				foreach ($keys as $val) {
					if (is_string($val)) {
						$encode[] = '\'' . htmlentities($val) . '\'';
					} else {
						$encode[] = $val;
					}
				}
				$strings[] = '(' . implode(',', $encode) . '),';
			}
		}
		return rtrim(implode('', $strings), ',') . ';';
	}
	
	
	/**
	 * @param $key
	 * @param $percent
	 * @return string
	 * 组合成进度条
	 */
	public function buildLine($key, $percent): string
	{
		$repeatTimes = 100;
		if ($percent > 100) {
			$percent = 100;
		}
		if ($percent > 0) {
			$hasColor = str_repeat('■', $percent);
		} else {
			$hasColor = '';
		}
		
		if ($repeatTimes - $percent > 0) {
			$noColor = str_repeat(' ', $repeatTimes - $percent);
		} else {
			$noColor = '';
		}
		
		$buffer = "[{$hasColor}{$noColor}]";
		if ($percent !== 100) {
			$percentString = sprintf("[  %s %-6s]", $key, $percent . '%');
		} else {
			$percentString = sprintf("[  %s %-5s]", $key, 'OK');;
		}
		
		return $percentString . $buffer . "\r";
	}
	
	/**
	 * @param bool $clear
	 * @return void
	 * 输出进度条
	 */
	public function outputProgress(bool $clear = false): void
	{
		if ($clear) {
			$number = count($this->percentStatus);
			for ($i = 0; $i < $number; $i++) {
				system("tput cuu1");
				system("tput el");
			}
		}
		foreach ($this->percentStatus as $key => $value) {
			echo $this->buildLine($key, $value) . "\n";
		}
	}
	
	/**
	 * @param $k
	 * @param $value
	 * @return void
	 * 更新进度条值
	 */
	public function updateProgressValue($k, $value): void
	{
		$this->percentStatus[$k] = $value;
		if ($this->percentStatus[$k] >= 1000) {
			$this->percentStatus[$k] = 1000;
			$this->outputProgress(true);
			return;
		}
		
		$this->outputProgress(true);
		usleep(50000);
	}
	
}
