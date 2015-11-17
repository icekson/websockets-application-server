<?php
/** 
 * @author a.itsekson
 * @created 01.11.2015 18:52 
 */

namespace Icekson\Server;


use Noodlehaus\Config;
use Psr\Log\LoggerInterface;

interface ServiceInterface {

    public function start($host, $port, Config $config);
    /**
     * @return string
     */
    public function getName();

    /**
     * @return LoggerInterface
     */
    public function getLogger();
} 