<?php
/** 
 * @author a.itsekson
 * @created 01.11.2015 18:43 
 */

namespace Icekson\Server;


use Icekson\Server\Exception\ServerException;
use Noodlehaus\Config;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class App {
    /**
     * @var null|Config
     */
    private $config = null;
    private $logger = null;
    private $phpPath = "php";

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function start(Config $config)
    {
        $this->config = $config;
        $this->runConnectors();
        $this->runGates();
    }

    private function runGates()
    {
        $gatesParams = $this->config->get("server.gates", []);
        if(count($gatesParams > 0)){
            foreach($gatesParams as $gateConf){
                $gate = new GateService($gateConf['name'], $this->getLogger());
                $gate->start($gateConf['host'], $gateConf['port'], $this->config);
            }
        }else{
            throw new ServerException("There is no any gate server");
        }
    }

    private function runConnectors()
    {
        $config = $this->config->get("server.connectors", []);
        if(count($config > 0)){
            foreach($config as $conf){
                $cmd = "{$this->phpPath} scripts/app-runner.php app-server:connector --name={$conf['name']} --config-path='./config/server.json'";
                $this->getLogger()->debug("Run connector: " . $conf['name']);
                $this->getLogger()->debug($cmd);
                $proc = new Process($cmd);
                $proc->start();
                sleep(1);
                if ($proc->isRunning()) {
                    $this->getLogger()->debug("Connector successfully started: pid - " . $proc->getPid());
                } else {
                    $this->getLogger()->error($proc->getErrorOutput());
                }
            }
        }else{
            throw new ServerException("There is no any gate server");
        }
    }

    private function getLogger()
    {
        return $this->logger;
    }
} 