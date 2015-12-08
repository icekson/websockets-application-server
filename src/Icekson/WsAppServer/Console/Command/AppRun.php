<?php
/**
 * @author a.itsekson
 * @created 01.11.2015 18:16
 */

namespace Icekson\WsAppServer\Console\Command;


use Icekson\WsAppServer\App;
use Icekson\WsAppServer\Application;
use Icekson\WsAppServer\Config\ApplicationConfig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AppRun extends BaseCommand
{

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

        $configPath = $input->getOption('config-path');
        if(empty($configPath)){
            $configPath = $this->getConfiguration();
        }else{
            $configPath = PATH_ROOT . $configPath;
        }
        $appConfig = new ApplicationConfig($configPath);
        $app = new Application($appConfig);
        $app->start();


        $executionTime = gmdate("H:i:s", time() - $start );
        $this->logger()->debug("Finish [" . gmdate("Y-m-d H:i:s") .']');
        $this->logger()->debug("Execution Time $executionTime [H:i:s]");


    }

} 