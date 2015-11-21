<?php
/**
 * @author a.itsekson
 * @createdAt: 17.11.2015 15:24
 */

namespace Icekson\WsAppServer\Config;


class ServiceConfig extends ConfigAdapter
{
    public function __construct($conf)
    {
        parent::__construct($conf);
    }

    /**
     * @return string
     */
    public function getName()
    {
        $name = $this->get('name', "app-service-" . uniqid());
        return $name;
    }

    /**
     * @return int
     */
    public function getHost()
    {
        $name = $this->get('host', "127.0.0.1");
        return $name;
    }

    /**
     * @return int
     */
    public function getPort()
    {
        $name = $this->get('port', 3000);
        return $name;
    }

    /**
     * @return string
     */
    public function getClass()
    {
        $name = $this->get('class', 'WsAppService\Service\ConnectorService');
        return $name;
    }

    /**
     * @return int
     */
    public function getInternalPort()
    {
        $name = $this->get('internal-port', $this->getPort()+1);
        return $name;
    }



}