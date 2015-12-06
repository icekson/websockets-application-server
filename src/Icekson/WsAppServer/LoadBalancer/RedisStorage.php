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
        $host = $config->get("host","127.0.0.1");
        $port = $config->get("port",6379);
        $this->redis = new Client("tcp://{$host}:{$port}", ['prefix' => 'ws-app.load-balancer']);
    }

    /**
     * @param $serviceName
     * @return null
     * @throws StorageException
     */
    public function geCountOfConnections($serviceName)
    {
        $count = $this->redis->get("service.{$serviceName}.connections");
        if($count === null){
            throw new StorageException("there is no registered service '{$serviceName}'");
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
        $count = $this->redis->get("service.{$serviceName}.connections");
        if($count === null){
            throw new StorageException("there is no registered service '{$serviceName}'");
        }
        $this->redis->set("service.{$serviceName}.connections", ++$count);
        return $this;
    }

    /**
     * @param $serviceName
     * @return $this
     * @throws StorageException
     */
    public function decrementCounter($serviceName)
    {
        $count = $this->redis->get("service.{$serviceName}.connections");
        if($count === null){
            throw new StorageException("there is no registered service '{$serviceName}'");
        }
        if($count > 0){
            $count--;
        }
        $this->redis->set("service.{$serviceName}.connections", $count);

        return $this;
    }

    /**
     * @param ServiceConfig[] $services
     * @return $this
     */
    public function persistAvailableConnectors(array $services)
    {
        $tmp = serialize($services);
        $this->redis->set("services", $tmp);
        foreach ($services as $service) {
            if(!$this->redis->get("service.{$service->getName()}.connections")) {
                $this->redis->set("service.{$service->getName()}.connections", 0);
            }
        }
        return $this;
    }

    /**
     * @return ServiceConfig[]
     */
    public function retrieveAvailableConnectors()
    {
        $tmp = $this->redis->get("services");
        $services = @unserialize($tmp);
        if(empty($services)){
            $services = [];
        }
        return $services;
    }

    public function reset()
    {
        $this->redis->del([
            "services",
            "service.connector-server-1.connections",
            "service.connector-server-2.connections",
            "service.connector-server-3.connections",
        ]);
    }

}