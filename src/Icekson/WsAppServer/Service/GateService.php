<?php
/**
 * @author a.itsekson
 * @createdAt: 17.11.2015 15:50
 */

namespace Icekson\WsAppServer\Service;

use Icekson\WsAppServer\Config\ConfigureInterface;
use Icekson\WsAppServer\Config\ServiceConfig;
use Icekson\WsAppServer\Messaging\Websocket\Handler;

class GateService extends AbstractService
{

    public function __construct(ServiceConfig $config)
    {
        parent::__construct($config->getName(), $config);
    }

    public function getRunCmd()
    {
        return "php scripts/runner.php app:service --type=gate --name='{$this->getName()}'";
    }


    public function run()
    {
        $loop = \React\EventLoop\Factory::create();
        $handler = new Handler($this->getConfiguration()->getName(), $this->getConfiguration());

        $webSock = new \React\Socket\Server($loop);
        $webSock->listen($this->getConfiguration()->getPort(), '0.0.0.0');
        $webServer = new \Ratchet\Server\IoServer(
            new \Ratchet\Http\HttpServer(
                new \Ratchet\WebSocket\WsServer(
                    $handler
                )
            ),
            $webSock
        );
        $loop->run();
    }



}