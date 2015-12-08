<?php
/**
 * @author a.itsekson
 * @createdAt: 17.11.2015 15:24
 */

namespace Icekson\WsAppServer\Config;


class ApplicationConfig extends ConfigAdapter
{
    public function __construct($conf)
    {
        parent::__construct($conf);
    }

    /**
     * @return mixed|null
     */
    public function getServicesConfig()
    {
        $conf = new ConfigAdapter($this->get('ws-server', []));
        $services = $conf->get('services', []);
        return $services;
    }



}