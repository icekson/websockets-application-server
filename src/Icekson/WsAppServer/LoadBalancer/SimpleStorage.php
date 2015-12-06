<?php
/**
 *
 * @author: a.itsekson
 * @date: 05.12.2015 22:59
 */

namespace Icekson\WsAppServer\LoadBalancer;


use Icekson\WsAppServer\Config\ConfigAdapter;
use Icekson\WsAppServer\Config\ConfigureInterface;
use Icekson\WsAppServer\Config\ServiceConfig;
use Predis\Client;

class SimpleStorage implements StorageInterface
{
    /**
     * @var \ArrayObject|null|ServiceConfig[]
     */
    private $services = null;

    private $counts = [];


    public function __construct(ConfigureInterface $config)
    {
        $this->services = new \ArrayObject();
    }

    /**
     * @param $serviceName
     * @return null
     * @throws StorageException
     */
    public function geCountOfConnections($serviceName)
    {
        $count = null;
        foreach ($this->services as $service) {
            if(isset($this->counts[$service->getName()])){
                $count = $this->counts[$service->getName()];
                break;
            }
        }
        if($count === null){
            throw new StorageException("there is noe registered service '{$serviceName}'");
        }
        return $count;
    }

    /**
     * @param $serviceName
     * @return $this
     */
    public function incrementCounter($serviceName)
    {
        if(!isset($this->counts[$serviceName])){
            $this->counts[$serviceName] = 0;
        }
        $this->counts[$serviceName]++;
        return $this;
    }

    public function decrementCounter($serviceName)
    {
        if(!isset($this->counts[$serviceName])){
            $this->counts[$serviceName] = 0;
        }
        if($this->counts[$serviceName] > 0) {
            $this->counts[$serviceName]--;
        }
        return $this;
    }

    /**
     * @param array $services
     * @return $this
     */
    public function persistAvailableConnectors(array $services)
    {
        $this->services = $services;
        return $this;
    }

    /**
     * @return \ArrayObject|\Icekson\WsAppServer\Config\ServiceConfig[]|null
     */
    public function retrieveAvailableConnectors()
    {
        return $this->services;
    }
}