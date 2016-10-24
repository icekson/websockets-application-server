<?php
/**
 *
 * @author: a.itsekson
 * @date: 05.12.2015 22:59
 */

namespace Icekson\WsAppServer\LoadBalancer;


use Icekson\Config\ConfigAdapter;
use Icekson\Config\ConfigureInterface;
use Icekson\Utils\Logger;
use Icekson\WsAppServer\Config\ServiceConfig;
use Predis\Client;
use Zend\Cache\Storage\Adapter\Memcache;

abstract class AbstractStorage implements StorageInterface
{
    /**
     * @var ConfigureInterface|null
     */
    protected $config = null;
    protected $logger = null;
    public function __construct(ConfigureInterface $config)
    {
        $this->config = $config;
        $this->logger = Logger::createLogger(get_class($this), $config->toArray());
    }

    /**
     * @param $serviceName
     * @return null
     * @throws StorageException
     */
    public function geCountOfConnections($serviceName)
    {
        $info = $this->getServicesInfo();
        if (!isset($info[$serviceName])) {
            throw new StorageException("there is no registered service '{$serviceName}'");
        } else {
            $serviceInfo = $info[$serviceName];
            $count = isset($serviceInfo['connections']) ? $serviceInfo['connections'] : 0;
        }
        return $count;
    }


    /**
     * @param $serviceName
     * @return $this
     * @throws StorageException
     */
    public function incrementCounter($serviceName)
    {
        $timeout = 10000;
        $count = 0;
        do{
            $res = $this->lock("services");
            $count++;
        }while(!$res && $count < $timeout);

        $info = $this->getServicesInfo();
        $serviceInfo = [];
        $count = 0;
        if (isset($info[$serviceName])) {
            $serviceInfo = $info[$serviceName];
            $count = isset($serviceInfo['connections']) ? $serviceInfo['connections'] : 0;
        }

        $count++;
        $serviceInfo['connections'] = $count;
        $info[$serviceName] = $serviceInfo;
        $this->saveServicesInfo($info);
        $this->unlock("services");
        return $this;
    }


    /**
     * @param $serviceName
     * @return $this
     * @throws StorageException
     */
    public function decrementCounter($serviceName)
    {
        $timeout = 10000;
        $count = 0;
        do{
            $res = $this->lock("services");
            $count++;
        }while(!$res && $count < $timeout);

        $info = $this->getServicesInfo();
        $serviceInfo = [];
        $count = 0;
        if (isset($info[$serviceName])) {
            $serviceInfo = $info[$serviceName];
            $count = isset($serviceInfo['connections']) ? $serviceInfo['connections'] : 0;
        }

        if($count > 0) {
            $count--;
        }
        $serviceInfo['connections'] = $count;
        $info[$serviceName] = $serviceInfo;
        $this->saveServicesInfo($info);
        $this->unlock("services");
        return $this;
    }

    /**
     * @param ServiceConfig[] $services
     * @return $this
     */
    public function persistAvailableConnectors(array $services)
    {
        $timeout = 10000;
        $count = 0;
        do{
            $res = $this->lock("services");
            $count++;
        }while(!$res && $count < $timeout);

        $info = $this->getServicesInfo();

        foreach ($services as $service) {
            $info[$service->getName()] = array_merge([
                'connections' => 0,
            ], $service->toArray());
        }
        $this->saveServicesInfo($info);

        $this->unlock("services");

        return $this;
    }

    /**
     * @return ServiceConfig[]
     */
    public function retrieveAvailableConnectors()
    {
        $info = $this->getServicesInfo();
        $res = [];
        foreach ($info as $item) {
            $res[] = new ServiceConfig($item);
        }
        return $res;
    }

    public function reset()
    {
        $info = [];
        $this->saveServicesInfo($info);
    }

    public abstract function check();

    protected abstract function getServicesInfo();

    protected abstract function saveServicesInfo($info);

    /**
     * @param $key
     * @return bool
     */
    protected abstract function lock($key);

    protected abstract function unlock($key);

}