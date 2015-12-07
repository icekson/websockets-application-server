<?php
/**
 * @author a.itsekson
 * @createdAt: 17.11.2015 15:50
 */

namespace Icekson\WsAppServer\Service;

use Api\Service\IdentityFinderInterface;
use Icekson\Base\Auth\EmptyIdentityFinder;
use Icekson\WsAppServer\Application;
use Icekson\WsAppServer\Config\ServiceConfig;

use Icekson\WsAppServer\LoadBalancer\Balancer;
use Icekson\WsAppServer\Messaging\AmqpAsync\PubSubConsumer;
use Icekson\WsAppServer\Rpc\RPC;
use Icekson\WsAppServer\Messaging\Websocket\ConnectorHandler;

class ConnectorService extends AbstractService
{

    private $pubsubConsumer = null;
    /**
     * @var null|RPC
     */
    private $rpc = null;

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
        $identityFinderClass = $this->getConfiguration()->get("identity_finder_class", "Icekson\\Base\\Auth\\EmptyIdentityFinder");
        $loop = $this->getLoop();
        $handler = new ConnectorHandler($this->getConfiguration()->getName(), $loop, $this->getConfiguration());
        $iFinder = new $identityFinderClass();
        if(!$iFinder instanceof IdentityFinderInterface){
            $iFinder = new EmptyIdentityFinder();
        }
        $handler->setIdentityFinder($iFinder);
        $this->pubsubConsumer = new PubSubConsumer($this->getConfiguration()->toArray(), $loop, $handler, $this->getName());
        $this->pubsubConsumer->consume();

        $loop->addPeriodicTimer(5*60, function() use($handler) {
            $handler->pingConnections();
        });

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
        Balancer::getInstance()->registerConnector($this->getConfiguration());
        $loop->run();
    }


}