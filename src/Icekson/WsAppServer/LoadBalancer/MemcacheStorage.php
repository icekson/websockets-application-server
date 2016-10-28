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
use Zend\Cache\Storage\Adapter\Memcache;

class MemcacheStorage extends AbstractStorage
{
    /**
     * @var null|Memcache
     */
    private $storage;


    public function __construct(ConfigureInterface $config)
    {
        parent::__construct($config);
        $redis = $config->get('memcache', ['host' => '127.0.0.1', 'port' => 11211, 'perfix' => 'ws-app.load-balancer']);
        $host = $redis["host"];
        $port = $redis["port"];
        try {
            $storage = new Memcache(['servers' => [
                [
                    "host" => $host,
                    "port" => $port
                ]
            ]]);
        }catch (\Throwable $ex){
            $this->logger->notice($ex->getMessage() . "\n" . $ex->getTraceAsString());
            $storage = null;
        }
        $this->storage = $storage;

    }


    protected function getServicesInfo()
    {
        if(!$this->check()){
            return [];
        }
        $res = false;
        $info = $this->storage->getItem("services.info", $res);
        if($res) {
            $info = @unserialize($info);
            if (empty($info)) {
                $info = [];
            }
        }else{
            $info = [];
        }
        return $info;
    }

    protected function saveServicesInfo($info)
    {
        if(!$this->check()){
            return [];
        }
        if(empty($info)){
            $info = [];
        }
        $d = @serialize($info);
        $this->storage->setItem("services.info", $d);
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
        $res = false;
        $isLocked = $this->storage->getItem("$key.lock", $res);
        if($res && $isLocked){
            return false;
        }
        $this->storage->setItem("$key.lock", true);
        return true;
    }

    protected function unlock($key)
    {
        if(!$this->check()){
            return;
        }
        $this->storage->setItem("$key.lock", false);
    }


    public function check()
    {
        $res = true;
        try{
            $this->storage->setItem('test',"test");
        }catch (\Throwable $ex){
            $this->logger->notice("check memcache storage failed: " . $ex->getMessage() . "\n" . $ex->getTraceAsString());
            $res = false;
        }
        return $res;
    }

}