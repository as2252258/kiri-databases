<?php
declare(strict_types=1);

namespace Database;

use Co\Channel;
use Exception;
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


    /**
     * @var string
     */
    public string $command = 'db:implode';


    /**
     * @var string
     */
    public string $description = 'php kiri.php db:implode --database users /Users/admin/snowflake-bi/test.sql';


    /**
     * @var Channel
     */
    protected Channel $channel;

    protected array $data;


    /**
     *
     */
    protected function configure(): void
    {
        $this->setName('db:implode')
             ->addArgument('path', InputArgument::REQUIRED, "save to path", null)
             ->addOption('database', 'db', InputArgument::OPTIONAL)
             ->setDescription('php kiri.php db:implode --database users /Users/admin/snowflake-bi/test.sql');
        $this->data = array_flip(get_html_translation_table(ENT_QUOTES | ENT_SUBSTITUTE));
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            /** @var Connection $data */
            $data = \Kiri::getDi()->get(DatabasesProviders::class)->get($input->getOption('database'));

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
                    $insert = str_replace('&#039;', "\'", str_replace('&quot;', '"', $line));

                    $insert = strtr($insert, $this->data);
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
