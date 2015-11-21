<?php

/**
 * @author a.itsekson
 * @createdAt: 17.11.2015 15:10
 */

namespace Icekson\WsAppServer\Service;


use Icekson\WsAppServer\Config\ConfigAwareInterface;
use Icekson\WsAppServer\Config\ConfigureInterface;
use Icekson\WsAppServer\Config\ServiceConfig;
use Icekson\WsAppServer\Exception\ServiceException;
use Icekson\Utils\Logger;
use Icekson\WsAppServer\ProcessStarter;

use Symfony\Component\Process\Process;

abstract class AbstractService  implements ServiceInterface, ConfigAwareInterface
{

    /**
     * @var string
     */
    protected $name = "";

    /**
     * @var bool
     */
    protected $isRun = false;

    /**
     * @var int
     */
    protected $pid = -1;


    /**
     * @var null|ServiceConfig
     */
    protected $config = null;

    /**
     * @var null|\Psr\Log\LoggerInterface
     */
    protected $logger = null;


    /**
     * AbtractService constructor.
     * @param $name
     * @param ServiceConfig $conf
     */
    public function __construct($name, ServiceConfig $conf)
    {
        $this->name = $name;
        $this->config = $conf;

    }

    public function start()
    {
        try{
            $this->isRun = true;
            $this->run();
        }catch (\Exception $ex){
            $this->getLogger()->error($ex->getMessage() . "\n" . $ex->getTraceAsString());
            $this->isRun = false;
        }
    }


    public function stop()
    {
        $this->isRun = false;
        ProcessStarter::getInstance()->stopProcess($this->pid);
    }


    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function isRun()
    {
        return $this->isRun;
    }

    /**
     * @return int
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    protected function getLogger()
    {
        return $this->logger = Logger::createLogger(get_class($this) . ":". $this->name, $this->getConfiguration()->toArray());
    }


    public function run(){
        throw new ServiceException("You should implement method run");
    }

    /**
     * @return ServiceConfig|ConfigureInterface
     */
    public function getConfiguration()
    {
        return $this->config;
    }

    public function setConfiguration(ConfigureInterface $config)
    {
        $this->config = $config;
    }

    public function startAsProcess()
    {
        $cmd = $this->getRunCmd();
        $this->pid = ProcessStarter::getInstance()->runNewProcess($cmd);
    }

    abstract public function getRunCmd();



}