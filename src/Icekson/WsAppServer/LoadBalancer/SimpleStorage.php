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

class SimpleStorage extends AbstractStorage
{
    private $connectors = [];

    public function __construct(ConfigureInterface $config)
    {
        parent::__construct($config);
    }


    protected function getServicesInfo()
    {
        return $this->connectors;
    }

    protected function saveServicesInfo($info)
    {
        $this->connectors = $info;
    }


    /**
     * @param $key
     * @return bool
     */
    protected function lock($key)
    {
        return true;
    }

    protected function unlock($key)
    {

    }


    public function check()
    {
        return true;
    }

}