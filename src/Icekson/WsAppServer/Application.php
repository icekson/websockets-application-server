<?php

/**
 * @author a.itsekson
 * @createdAt: 17.11.2015 15:18
 */

namespace Icekson\WsAppServer;


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

        $amqpConf = $this->config->get('amqp', []);
        $servicesConfig = $this->config->getServicesConfig();
//        var_export($servicesConfig);exit;
        $loop = $this->loop;
        $this->loop = $loop;
        ProcessStarter::getInstance($loop);
        Balancer::getInstance($this->getConfiguration())->reset();
        foreach ($servicesConfig as $serviceConf) {
            try {

                /** @var ServiceConfig $conf */
                $conf = new ServiceConfig(array_merge($serviceConf, ['amqp' => $amqpConf]));
                $service = $this->initService($conf);
                if ($service instanceof BackendService) {
                    $instances = $service->getConfiguration()->get('count', 1);
                    $conf = $conf->toArray();
                    for ($i = 1; $i <= $instances; $i++) {
                        $conf['name'] = preg_replace("/(\d+)$/", $i, $conf['name']);
                        $s = $this->initService(new ServiceConfig($conf));
                        $s->startAsProcess();
                        $this->services[$s->getPid()] = $s;
                    }
                } else {
                    $service->startAsProcess();
                    $this->services[$service->getPid()] = $service;
                }


            } catch (ServiceException $ex) {
                $this->logger->error("Init service error: " . $ex->getMessage());
            }
        }


//        $loop->addPeriodicTimer(5, function() use ($app, $loop){
//            try {
//                $this->logger->debug("Loop tick, count of services: " . count($app->services));
//                if ($app->isStoped) {
//
//                    $app->logger->info("Count of run services: " . count($this->services));
//
//                    /** @var ServiceInterface $service */
//                    foreach ($app->services as $service) {
//                        $res = $service->stop();
//                        if ($res) {
//                            $app->logger->info(sprintf("Service pid:%s %s succssfully stoped.", $service->getPid(), $service->getName()));
//                        } else {
//                            $app->logger->warning(sprintf("Service pid:%s %s isn't run", $service->getPid(), $service->getName()));
//                        }
//                    }
//                    $app->services = [];
//                    $app->logger->info("All services are stoped. Application exit...");
//                    $loop->stop();
//                }
//            }catch (\Exception $ex){
//                $app->logger->error($ex->getMessage() . "\n" . $ex->getTraceAsString());
//            }
//        });
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
        $cmd = sprintf("%s scripts/runner.php app-server:start --config-path='%s'", $this->getConfiguration()->get("script_php_path"), $configPath);
        $res = ProcessStarter::getInstance()->checkProcessByCmd($cmd);
        if (!$res) {
            $needToRestart = false;
            $amqpConf = $this->config->get('amqp', []);
            $servicesConfig = $this->config->getServicesConfig();
            foreach ($servicesConfig as $serviceConf) {

                /** @var ServiceConfig $conf */
                $conf = new ServiceConfig(array_merge($serviceConf, ['amqp' => $amqpConf]));
                $service = $this->initService($conf);
                $_cmd = $service->getRunCmd();
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
        $services = $this->getConfiguration()->getServicesConfig();
        if ($type == 'backend') {
            $key = preg_replace("/(-\d+$)/", "", $name);
            $serviceConfig = isset($services[$key]) ? $services[$key] : [];
            if (!empty($serviceConfig)) {
                $serviceConfig['name'] = $name;
            }
        } else {
            $serviceConfig = isset($services[$name]) ? $services[$name] : [];
        }


        if (empty($serviceConfig)) {
            throw new ServiceException("Service with name '$name' is not found");
        }


        $service = $this->initService(new ServiceConfig(array_replace_recursive($this->getConfiguration()->toArray(), $serviceConfig)));
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

        $amqpConf = $this->config->get('amqp', []);
        $servicesConfig = $this->config->getServicesConfig();
        foreach ($servicesConfig as $serviceConf) {

            /** @var ServiceConfig $conf */
            $conf = new ServiceConfig(array_merge($serviceConf, ['amqp' => $amqpConf]));
            $service = $this->initService($conf);

            $pid = null;
            ProcessStarter::getInstance()->stopProccessByCmd($service->getRunCmd());
        }
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

}