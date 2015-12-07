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
        $host = $config->get("host", "127.0.0.1");
        $port = $config->get("port", 6379);
        $this->redis = new Client("tcp://{$host}:{$port}", ['prefix' => 'ws-app.load-balancer']);
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
        return $this;
    }

    /**
     * @param $serviceName
     * @return $this
     * @throws StorageException
     */
    public function decrementCounter($serviceName)
    {
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
        return $this;
    }

    /**
     * @param ServiceConfig[] $services
     * @return $this
     */
    public function persistAvailableConnectors(array $services)
    {
        $info = $this->getServicesInfo();

        foreach ($services as $service) {
            $info[$service->getName()] = array_merge([
                'connections' => 0,
            ], $service->toArray());
        }
        $this->saveServicesInfo($info);

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