<?php
/**
 * @author a.itsekson
 * @createdAt: 17.11.2015 15:20
 */

namespace Icekson\WsAppServer\Config;


use Icekson\Utils\IArrayExchange;

interface ConfigureInterface extends IArrayExchange
{
    public function get($key, $default = null);

}