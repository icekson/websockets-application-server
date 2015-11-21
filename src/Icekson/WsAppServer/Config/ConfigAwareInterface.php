<?php
/**
 * Created by PhpStorm.
 * User: Alexey
 * Date: 21.11.2015
 * Time: 13:31
 */

namespace Icekson\WsAppServer\Config;


use Icekson\WsAppServer\Config\ConfigureInterface;

interface ConfigAwareInterface
{
    /**
     * @return ConfigureInterface
     */
    public function getConfiguration();

    public function setConfiguration(ConfigureInterface $config);

}