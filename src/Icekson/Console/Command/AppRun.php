<?php
/**
 * @author a.itsekson
 * @created 01.11.2015 18:16
 */

namespace Icekson\Console\Command;


use Icekson\Server\App;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Noodlehaus\Config;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AppRun extends Command
{
    private $logger = null;

    protected function configure()
    {
        $this
            ->setName('app-server:start')
            ->setDescription('Start application server')
            ->addOption(
                'config-path',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to config',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $start = time();
        $this->logger()->debug("Start [" .  gmdate("Y-m-d H:i:s").']');
      //  $this->logger()->debug("arguments : " . var_export($input->getArguments(), true));
        $this->logger()->debug("options : ", $input->getOptions());


        $conf = new Config($input->getOption('config-path'));

        $app = new App($this->logger());
        $app->start($conf);



        $executionTime = gmdate("H:i:s", time() - $start );
        $this->logger()->debug("Finish [" . gmdate("Y-m-d H:i:s") .']');
        $this->logger()->debug("Execution Time $executionTime [H:i:s]");


    }

    /**
     * @return LoggerInterface
     */
    private function logger()
    {
        if($this->logger === null) {
            $logger = new Logger("AppRun");
            $logger->pushHandler(new StreamHandler(dirname(__DIR__) . "/../../../../logs/app-runner.log"));
            $logger->pushHandler(new StreamHandler("php://output"));
            $this->logger = $logger;
        }
        return $this->logger;

    }

} 