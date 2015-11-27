<?php

/**
 * @author a.itsekson
 * @createdAt: 17.11.2015 15:18
 */

namespace Icekson\WsAppServer;


use Icekson\Utils\Registry;
use Icekson\WsAppServer\Config\ApplicationConfig;
use Icekson\WsAppServer\Config\ConfigAwareInterface;
use Icekson\WsAppServer\Config\ConfigureInterface;
use React\EventLoop\LibEventLoop;
use Icekson\Utils\Logger;

use Icekson\WsAppServer\Config\ServiceConfig;
use Icekson\WsAppServer\Exception\ServiceException;
use Icekson\WsAppServer\Service\ServiceInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

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

        foreach ($servicesConfig as $serviceConf) {
            try {

                /** @var ServiceConfig $conf */
                $conf = new ServiceConfig(array_merge($serviceConf, ['amqp' => $amqpConf]));
                $service = $this->initService($conf);
                $service->startAsProcess();
                $this->services[] = $service;

            }catch (ServiceException $ex){
                $this->logger->error("Init service error: " . $ex->getMessage());
            }
        }

        $loop = \React\EventLoop\Factory::create();
        $app = $this;

        $loop->addPeriodicTimer(5, function() use ($app, $loop){
            try {
                $this->logger->debug("Loop tick, count of services: " . count($app->services));
                if ($app->isStoped) {

                    $app->logger->info("Count of run services: " . count($this->services));

                    /** @var ServiceInterface $service */
                    foreach ($app->services as $service) {
                        $res = $service->stop();
                        if ($res) {
                            $app->logger->info(sprintf("Service pid:%s %s succssfully stoped.", $service->getPid(), $service->getName()));
                        } else {
                            $app->logger->warning(sprintf("Service pid:%s %s isn't run", $service->getPid(), $service->getName()));
                        }
                    }
                    $app->services = [];
                    $app->logger->info("All services are stoped. Application exit...");
                    $loop->stop();
                }
            }catch (\Exception $ex){
                $app->logger->error($ex->getMessage() . "\n" . $ex->getTraceAsString());
            }
        });
        $loop->run();
    }

    /**
     * @param $type
     * @param $name
     * @throws ServiceException
     */
    public function runService($name, $type)
    {
        $services = $this->getConfiguration()->get("services");
        $serviceConfig = isset($services[$name]) ? $services[$name] : [];

        if(empty($serviceConfig)){
            throw new ServiceException("Service with name '$name' is not found");
        }
        $service = $this->initService(new ServiceConfig(array_merge($serviceConfig, ["amqp" => $this->getConfiguration()->get("amqp", [])])));
        $service->start();

    }

    public function stop()
    {
        $this->logger->debug("Stop application signal is received");
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
        $class = "\\".trim($class, "\\");

        if(!class_exists($class)){
            throw new ServiceException("Invalid service class name set for service: {$class}");
        }
        $instance = new $class($this, $conf);

        if(!$instance instanceof ServiceInterface){
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
}