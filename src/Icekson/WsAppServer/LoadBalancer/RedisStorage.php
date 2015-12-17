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

class RedisStorage implements StorageInterface
{
    private $redis;


    public function __construct(ConfigureInterface $config)
    {
        $redis = $config->get('redis', ['host' => '127.0.0.1', 'port' => 6379, 'perfix' => 'ws-app.load-balancer']);
        $host = $redis["host"];
        $port = $redis["port"];
        $this->redis = new Client("tcp://{$host}:{$port}", ['prefix' => $redis['prefix']]);
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

    private function getServicesInfo()
    {
        $info = $this->redis->get("services.info");
        $info = @unserialize($info);
        if (empty($info)) {
            $info = [];
        }
        return $info;
    }

    private function saveServicesInfo($info)
    {
        if(empty($info)){
            $info = [];
        }
        $d = @serialize($info);
        $this->redis->set("services.info", $d);
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
     * @param $key
     * @return bool
     */
    private function lock($key)
    {
        $isLocked = $this->redis->get("$key.lock");
        if($isLocked){
            return false;
        }
        $this->redis->set("$key.lock", true);
        return true;
    }

    private function unlock($key)
    {
        $this->redis->set("$key.lock", false);
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

}