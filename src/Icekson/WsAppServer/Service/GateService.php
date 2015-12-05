<?php
/**
 * @author a.itsekson
 * @createdAt: 17.11.2015 15:50
 */

namespace Icekson\WsAppServer\Service;

use Icekson\WsAppServer\Application;
use Icekson\WsAppServer\Config\ConfigureInterface;
use Icekson\WsAppServer\Config\ServiceConfig;
use Icekson\WsAppServer\Messaging\Websocket\Handler;

class GateService extends AbstractService
{

    public function __construct(Application $app, ServiceConfig $config)
    {
        parent::__construct($config->getName(), $config, $app);
    }

    public function getRunCmd()
    {
        return sprintf("%s scripts/runner.php app:service --type=gate --name='%s'", $this->getConfiguration()->get("php_path"), $this->getName());
    }


    public function run()
    {
//        $loop = \React\EventLoop\Factory::create();
//
//
//        $loop->run();
    }



}