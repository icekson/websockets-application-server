<?php
/**
 * @author a.itsekson
 * @createdAt: 17.11.2015 15:50
 */

namespace Icekson\WsAppServer\Service;

use Icekson\WsAppServer\Application;
use Icekson\WsAppServer\Config\ServiceConfig;
use Icekson\WsAppServer\Service\Support\PubSubConsumer;
use Icekson\WsAppServer\Service\Support\PubSubListener;
use Icekson\WsAppServer\Service\Support\PubSubListenerInterface;
use Icekson\WsAppServer\Messaging\Websocket\Handler;

class ConnectorService extends AbstractService
{

    private $pubSubConsumer = null;

    public function __construct(Application $app, ServiceConfig $config)
    {
        parent::__construct($config->getName(), $config, $app);
    }

    public function getRunCmd()
    {
        return sprintf("%s scripts/runner.php app:service --type=connector --name='%s'", $this->getConfiguration()->get("php_path"), $this->getName());
    }

    public function run()
    {
        $loop = \React\EventLoop\Factory::create();
        $handler = new Handler($this->getConfiguration()->getName(), $this->getConfiguration());

        $em = $this->getApplication()->getEventDispatcher();
        $em->addListener('pubsub.messagePublished', [$this, 'onPubSubMessage']);
        $pubsubConsumer = new PubSubConsumer(new PubSubListener($em), $this->getConfiguration());
        $pubsubConsumer->start();

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