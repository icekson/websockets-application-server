<?php
/**
 * @author a.itsekson
 * @createdAt: 17.11.2015 15:50
 */

namespace Icekson\WsAppServer\Service;

use Icekson\Utils\Logger;
use Icekson\WsAppServer\Config\ServiceConfig;
use Icekson\WsAppServer\Service\Support\PubSubListenerInterface;
use Messaging\Websocket\Handler;

class ConnectorService extends AbstractService implements PubSubListenerInterface
{

    public function __construct(ServiceConfig $config)
    {
        parent::__construct($config->getName(), $config);
    }

    public function getRunCmd()
    {
        return "php scripts/runner.php app:service --type=connector --name='{$this->getName()}'";
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

    public function onPubSubMessage($msg)
    {
        $this->getLogger()->debug("onPubSubMessage: " . $msg);
    }
}