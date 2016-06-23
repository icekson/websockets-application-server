<?php
/**
 *
 * @author: a.itsekson
 * @date: 06.12.2015 13:45
 */

namespace Icekson\WsAppServer\LoadBalancer;


use Icekson\Config\ConfigAdapter;
use Icekson\Config\ConfigureInterface;
use Icekson\WsAppServer\Config\ServiceConfig;
use Icekson\WsAppServer\Exception\ServiceException;

class Balancer
{

    private static $instance = null;

    /**
     * @var null|StorageInterface
     */
    private $storage = null;

    /**
     * @param ConfigureInterface|null $conf
     * @return Balancer|null
     */
    public static function getInstance(ConfigureInterface $conf = null)
    {
        if($conf === null){
            $conf = new ConfigAdapter([]);
        }
        if(self::$instance === null){
            self::$instance = new self($conf);
        }
        return self::$instance;
    }

    private function __construct(ConfigureInterface $config)
    {
        $this->storage = new RedisStorage($config);

       if(!$this->storage->check()){
           $this->storage = new SimpleStorage($config);
       }

    }


    /**
     * @param ServiceConfig $service
     * @return $this
     */
    public function registerConnector(ServiceConfig $service)
    {
        $services = $this->storage->retrieveAvailableConnectors();
        $services[$service->getName()] = $service;
        $this->storage->persistAvailableConnectors($services);
        return $this;
    }

    public function attachConnection($serviceName)
    {
        $this->storage->incrementCounter($serviceName);
        return $this;
    }

    public function detachConnection($serviceName)
    {
        $this->storage->decrementCounter($serviceName);
        return $this;
    }

    /**
     * @param ServiceConfig $service
     * @return $this
     */
    public function unregisterService(ServiceConfig $service)
    {
        $services = $this->storage->retrieveAvailableConnectors();
        if(isset($services[$service->getName()])){
            unset($services[$service->getName()]);
        }
        $this->storage->persistAvailableConnectors($services);
        return $this;
    }

    /**
     * @return ServiceConfig
     * @throws ServiceException
     */
    public function getConnector()
    {
        $services = $this->storage->retrieveAvailableConnectors();

        usort($services, function($a, $b){
            if($a->get('connections') > $b->get('connections')){
                return 1;
            }else if($a->get('connections') < $b->get('connections')){
                return -1;
            }else{
                return 0;
            }
        });
        if(count($services) === 0){
            throw new ServiceException("there is no available connectors");
        }
        return array_shift($services);

    }

    /**
     * @return \Icekson\WsAppServer\Config\ServiceConfig[]
     */
    public function getAvalableConnectors()
    {
        return $this->storage->retrieveAvailableConnectors();
    }

    public function reset()
    {
        $this->storage->reset();
    }

}