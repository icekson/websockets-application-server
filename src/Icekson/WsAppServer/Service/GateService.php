<?php
/**
 * @author a.itsekson
 * @createdAt: 17.11.2015 15:50
 */

namespace Icekson\WsAppServer\Service;

use Icekson\WsAppServer\Application;
use Icekson\Config\ConfigureInterface;
use Icekson\WsAppServer\Config\ServiceConfig;
use Icekson\WsAppServer\Messaging\Websocket\GateHandler;
use Icekson\WsAppServer\Messaging\Websocket\Handler;

class GateService extends AbstractService
{

    public function __construct(Application $app, ServiceConfig $config)
    {
        parent::__construct($config->getName(), $config, $app);
    }

    public function getRunCmd()
    {
        return sprintf("%s scripts/runner.php app:service --type=gate --name='%s' --config-path='%s'", $this->getConfiguration()->get("php_path"), $this->getName(), $this->getConfigPath());
    }


    public function run()
    {
        $loop = $this->getLoop();
        $handler = new GateHandler($this->getConfiguration()->getName(), $loop, $this->getConfiguration());

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