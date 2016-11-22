<?php

/**
 * @author a.itsekson
 * @createdAt: 17.11.2015 15:18
 */

namespace Icekson\WsAppServer;


use Api\Service\Annotation\Service;
use Icekson\WsAppServer\Service\BackendService;
use Icekson\Utils\Registry;
use Icekson\WsAppServer\Config\ApplicationConfig;
use Icekson\Config\ConfigAwareInterface;
use Icekson\Config\ConfigureInterface;
use Icekson\WsAppServer\LoadBalancer\Balancer;
use Icekson\WsAppServer\Service\JobsService;
use Icekson\Utils\Logger;

use Icekson\WsAppServer\Config\ServiceConfig;
use Icekson\WsAppServer\Exception\ServiceException;
use Icekson\WsAppServer\Service\ServiceInterface;
use React\EventLoop\LoopInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Process\Process;

class Application implements \SplObserver, ConfigAwareInterface
{

    /**
     * @var null|ApplicationConfig
     */
    private $config = null;

    /**
     * @var null|\Psr\Log\LoggerInterface
     */
    private $logger = null;

    /**
     * @var null|LoopInterface
     */
    private $loop = null;

    /**
     * @var array
     */
    private $services = [];

    private $isStoped = false;

    private $eventDispatcher = null;


    public function __construct(ApplicationConfig $config)
    {
        $this->config = $config;
        $this->logger = Logger::createLogger(get_class($this), $config->get('amqp', []));
        $this->eventDispatcher = new EventDispatcher();
        Registry::getInstance()->set('application', $this);
        $this->loop = \React\EventLoop\Factory::create();
    }

    /**
     * @return null|EventDispatcher
     */
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    public function start()
    {
        $this->logger->info("Start WsAppServer");

        $servicesConfig = $this->config->getServicesConfig();
//        var_export($servicesConfig);exit;
        $loop = $this->loop;
        $this->loop = $loop;
        ProcessStarter::getInstance($loop);
        Balancer::getInstance($this->getConfiguration())->reset();

        $serviceConfigs = $this->parseServicesConfig($servicesConfig);

        /** @var ServiceInterface $service */
        foreach ($serviceConfigs as $serviceConf) {
            $service = $this->initService($serviceConf);
            $service->startAsProcess();
            $this->services[$service->getPid()] = $service;

        }
        $loop->run();
    }

    /**
     * @param null|string $configPath
     */
    public function check($configPath = null)
    {
        if ($configPath === null) {
            $configPath = CONFIG_PATH;
        }
        $loop = $this->loop;
        ProcessStarter::getInstance($loop);
        $cmd = sprintf("%s scripts/runner.php app-server:start --config-path='%s'", $this->getConfiguration()->get("script_php_path", 'php'), $configPath);
        $res = ProcessStarter::getInstance()->checkProcessByCmd($cmd);
        if (!$res) {
            $needToRestart = false;
            $servicesConfig = $this->config->getServicesConfig();

            $services = $this->parseServicesConfig($servicesConfig);
            foreach ($services as $service) {
                $s = $this->initService($service);
                $_cmd = $s->getRunCmd();
                $r = ProcessStarter::getInstance()->checkProcessByCmd($_cmd);
                if (!$r) {
                    $needToRestart = true;
                    break;
                }
            }

            if ($needToRestart) {
                $this->_runCommand($cmd);
            }
        }
    }

    private function _runCommand($cmd, $wait = 30)
    {
        $this->logger->info("App server isn't started, try to start...");
        $process = new Process($cmd);
        $process->start();
        sleep($wait);
        if ($process->isRunning()) {
            $this->logger->debug("Server started: pid - " . $process->getPid());
        } else {
            $this->logger->warning($process->getErrorOutput());
        }
    }

    /**
     * @param $name
     * @param $type
     * @param null $routingKey
     * @throws ServiceException
     */
    public function runService($name, $type, $routingKey = null)
    {
        $services = $this->parseServicesConfig($this->getConfiguration()->getServicesConfig());
        $serviceConfig = null;
        // find needed service inside config
//        $tmp = [];
        foreach ($services as $key => $serviceConf) {
//            $tmp[] = $serviceConf->getName();
            if($serviceConf->getName() == $name){
                $serviceConfig = $serviceConf;
                break;
            }
        }
//        $this->logger->notice(var_export($tmp, true));exit;

        if (empty($serviceConfig)) {
            throw new ServiceException("Service with name '$name' is not found");
        }

        $service = $this->initService(new ServiceConfig(array_replace_recursive($this->getConfiguration()->toArray(), $serviceConfig->toArray())));
        if ($service instanceof JobsService) {
            $service->setRoutingKey($routingKey);
        }
        $service->start();

    }

    public function stop()
    {
        $this->logger->debug("Stop application");
        $loop = $this->loop;
        ProcessStarter::getInstance($loop);
        $phpCmd = $this->getConfiguration()->get('php_path', 'php');
        $proc = new Process(sprintf("kill -15 `ps -ef | grep '%s scripts/runner.php app-server:start' | grep -v grep | awk '{print $2}'`", $phpCmd));
        $proc->run();
        $proc = new Process(sprintf("kill -15 `ps -ef | grep '%s scripts/runner.php app:service' | grep -v grep | awk '{print $2}'`", $phpCmd));
        $proc->run();
        $this->isStoped = true;
    }

    /**
     * @param ServiceConfig $conf
     * @throws ServiceException
     * @return Service\ServiceInterface
     */
    private function initService(ServiceConfig $conf)
    {
        $class = $conf->getClass();
        $class = "\\" . trim($class, "\\");

        if (!class_exists($class)) {
            throw new ServiceException("Invalid service class name set for service: {$class}");
        }
        $instance = new $class($this, $conf);

        if (!$instance instanceof ServiceInterface) {
            throw new ServiceException("Provided service class doesn't implement ServiceInterface");
        }
        return $instance;
    }


    public function update(\SplSubject $subject)
    {

    }

    /**
     * @return ApplicationConfig|ConfigureInterface
     */
    public function getConfiguration()
    {
        return $this->config;
    }

    public function setConfiguration(ConfigureInterface $config)
    {
        $this->config = $config;
    }

    /**
     * @return null|LoopInterface
     */
    public function getLoop()
    {
        return $this->loop;
    }

    private function parseServicesConfig($servicesConfig)
    {
        $amqpConf = $this->getConfiguration()->get('amqp', []);
        $services = [];
        foreach ($servicesConfig as $serviceConf) {
            try {

                /** @var ServiceConfig $conf */
                $conf = new ServiceConfig(array_merge($serviceConf, ['amqp' => $amqpConf]));
                $service = $this->initService($conf);
                $instances = $service->getConfiguration()->get('servers', null);
                if($instances === null){
                    $instances = $service->getConfiguration()->get('workers', null);
                }
                $confAsArray = $conf->toArray();
                unset($confAsArray['workers']);
                unset($confAsArray['servers']);
                if($instances === null){
                    // $s = $this->initService($conf);
                    $name = isset($confAsArray['name']) ? $confAsArray['name']: null;
                    if($name !== null) {
                        $name = $name . (isset($confAsArray['routing_key']) ? ("-" . $confAsArray['routing_key']) : "");
                        $count = isset($confAsArray['count']) ? $confAsArray['count'] : null;
                        if($count !== null) {
                            for ($j = 1; $j <= $count; $j++) {
                                $conf = new ServiceConfig(array_replace_recursive($confAsArray, ["name" => $name . "-" . $j]));
                                $services[] = $conf;
                            }
                        }else{
                            $conf = new ServiceConfig(array_replace_recursive($confAsArray, ["name" => $name]));
                            $services[] = $conf;
                        }
                    }
                }else {
                    foreach ($instances as $i => $instanceConf) {
                        $name = isset($confAsArray['name']) ? $confAsArray['name']: $instanceConf['name'];
                        $name = $name . (isset($instanceConf['routing_key']) ? ("-" . $instanceConf['routing_key']): "");
                        $count = isset($instanceConf['count']) ? $instanceConf['count'] : null;
                        if($count !== null) {
                            for ($j = 1; $j <= $count; $j++) {
                                $conf = new ServiceConfig(array_replace_recursive($confAsArray, $instanceConf, ["name" => $name . "-" . ($i + 1) * $j]));
                                $services[] = $conf;
                            }
                        }else{
                            $conf = new ServiceConfig(array_replace_recursive($confAsArray, $instanceConf, ["name" => $name . "-" . ($i + 1)]));
                            $services[] = $conf;
                        }
                    }
                }

            } catch (ServiceException $ex) {
                $this->logger->error("Init service error: " . $ex->getMessage() . "\n" . $ex->getTraceAsString());
            }
        }

        return $services;
    }

}