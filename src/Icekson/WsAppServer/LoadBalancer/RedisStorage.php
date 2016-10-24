<?php
/**
 *
 * @author: a.itsekson
 * @date: 05.12.2015 22:59
 */

namespace Icekson\WsAppServer\LoadBalancer;


use Icekson\Config\ConfigAdapter;
use Icekson\Config\ConfigureInterface;
use Icekson\WsAppServer\Config\ServiceConfig;
use Predis\Client;

class RedisStorage extends AbstractStorage
{
    private $redis;


    public function __construct(ConfigureInterface $config)
    {
        parent::__construct($config);
        $redis = $config->get('redis', ['host' => '127.0.0.1', 'port' => 6379, 'perfix' => 'ws-app.load-balancer']);
        $host = $redis["host"];
        $port = $redis["port"];
        $this->redis = new Client("tcp://{$host}:{$port}", ['prefix' => $redis['prefix']]);
    }


    protected function getServicesInfo()
    {
        if(!$this->check()){
            return [];
        }
        $info = $this->redis->get("services.info");
        $info = @unserialize($info);
        if (empty($info)) {
            $info = [];
        }
        return $info;
    }

    protected function saveServicesInfo($info)
    {
        if(!$this->check()){
            return;
        }
        if(empty($info)){
            $info = [];
        }
        $d = @serialize($info);
        $this->redis->set("services.info", $d);
    }


    /**
     * @param $key
     * @return bool
     */
    protected function lock($key)
    {
        if(!$this->check()){
            return true;
        }
        $isLocked = $this->redis->get("$key.lock");
        if($isLocked){
            return false;
        }
        $this->redis->set("$key.lock", true);
        return true;
    }

    protected function unlock($key)
    {
        if(!$this->check()){
            return;
        }
        $this->redis->set("$key.lock", false);
    }


    public function check()
    {
        $res = true;
        try{
            $this->redis->set('test',"test");
        }catch (\Throwable $ex){
            $this->logger->error("check redis storage failed: " . $ex->getMessage() . "\n" . $ex->getTraceAsString());
            $res = false;
        }
        return $res;
    }

}