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
class ImplodeCommand extends Command
{

    public string $command = 'db:implode';


    public string $description = 'php kiri.php db:implode --database users /Users/admin/snowflake-bi/test.sql';


    private LocalService $service;

    public array $percentStatus = [];

    protected Channel $channel;


    /**
     *
     */
    protected function configure()
    {
        $this->service = \Kiri::getDi()->get(LocalService::class);
        $this->setName('db:implode')
             ->addArgument('path', InputArgument::REQUIRED, "save to path", null)
             ->addOption('database', 'db', InputArgument::OPTIONAL)
             ->setDescription('php kiri.php db:implode --database users /Users/admin/snowflake-bi/test.sql');
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

            $path = $input->getArgument('path');
            if (!str_starts_with($path, '/')) {
                $path = APP_PATH . $path;
            }

            $waite  = new Coroutine\WaitGroup();
            $stream = fopen($path, 'r');
            if ($stream) {
                while (($line = fgets($stream)) !== false) {
                    if (!str_starts_with(strtoupper($line), 'INSERT INTO')) {
                        continue;
                    }
                    $waite->add();
                    [$one, $two] = explode('VALUES', $line);
                    $foreach = explode(',', $two);

                    $array = [];
                    foreach ($foreach as $item) {
                        $values = explode('),(', trim($item, '()'));
                        $tmp = [];
                        foreach ($values as $date) {
                            if (is_string($date)) {
                                $tmp[] = '\'' . html_entity_decode(trim($date, '\'')) . '\'';
                            } else {
                                $tmp[] = $data;
                            }
                        }
                        $array[] = implode(',', $tmp);
                    }
                    $insert = $one . ' VALUES ('.implode('),(', $array).')';
                    Coroutine::create(function () use ($waite, $insert, $data) {
                        Coroutine\defer(fn() => $waite->done());
                        $data->createCommand($insert)->exec();
                    });
                }
                fclose($stream);
            }
            $waite->wait();

            $output->write('dump data success');
        } catch (\Throwable $throwable) {
            $output->writeln(throwable($throwable));
        } finally {
            return 1;
        }
    }

}
