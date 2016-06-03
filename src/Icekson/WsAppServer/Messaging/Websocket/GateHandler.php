<?php
/**
 * @author a.itsekson
 * @date 04.09.2015
 *
 */

namespace Icekson\WsAppServer\Messaging\Websocket;


use Api\Service\Exception\BadTokenException;
use Api\Service\Exception\NoTokenException;
use Api\Service\IdentityInterface;
use Api\Service\Response\Builder;
use Api\Service\Util\Properties;
use Icekson\Utils\Logger;
use Icekson\WsAppServer\Config\ApplicationConfig;
use Icekson\WsAppServer\Config\ConfigAdapter;
use Icekson\Config\ConfigAwareInterface;
use Icekson\Config\ConfigureInterface;
use Icekson\WsAppServer\Config\ServiceConfig;
use Icekson\WsAppServer\LoadBalancer\Balancer;
use Icekson\WsAppServer\Messaging\Amqp\PubSub as AMQPPubSub;


use Icekson\WsAppServer\Messaging\PubSub;
use Icekson\Base\Request;
use Icekson\WsAppServer\Rpc\Handler\ResponseHandlerInterface;
use Icekson\WsAppServer\Rpc\ResponseInterface;
use Icekson\WsAppServer\Rpc\RPC;
use Icekson\WsAppServer\Service\Support\PubSubListenerInterface;
use Psr\Log\LoggerInterface;
use Ratchet\ConnectionInterface;
use Api\Service\Response\JsonBuilder as JsonResponseBuilder;

use React\EventLoop\LoopInterface;
use Ratchet\MessageComponentInterface;
use Api\Service\IdentityFinderInterface;

class GateHandler implements MessageComponentInterface, ConfigAwareInterface
{

    const VERSION = '1.0.0';

    private $clients;

    /**
     * @var null|LoggerInterface
     */
    private $_logger = null;

    /**
     * @var Request
     */
    private $request = null;

    /**
     * @var PubSub|null
     */
    private $pubSub = null;


    /**
     * @var null|IdentityFinderInterface
     */
    private $identityFinder = null;
    /**
     * @var ConfigureInterface|null
     */
    private $config = null;

    private $name ="gate-service";


    public function getName()
    {
        return $this->name;
    }

    /**
     * Handler constructor.
     * @param $name
     * @param LoopInterface $loop
     * @param ConfigureInterface $config
     */
    public function __construct($name, LoopInterface $loop,ConfigureInterface $config)
    {
        $this->name = $name;
        $this->config = $config;
        $this->clients = new \SplObjectStorage;

        $this->pubSub = new AMQPPubSub($config->toArray(), $this->getName());

    }


    public function getVersion()
    {
        return self::VERSION;
    }

    /**
     * When a new connection is opened it will be passed to this method
     * @param  ConnectionInterface $conn The socket/connection that just connected to your application
     * @throws \Exception
     */
    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        $this->logger()->info("new connection $conn->resourceId ({$conn->remoteAddress}) : " . $this->clients->count() . " connected");

    }

    /**
     * This is called before or after a socket is closed (depends on how it's closed).  SendMessage to $conn will not result in an error if it has already been closed.
     * @param  ConnectionInterface $conn The socket/connection that is closing/closed
     * @throws \Exception
     */
    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        $this->logger()->info("close connection $conn->resourceId ({$conn->remoteAddress}):  " . $this->clients->count() . " connected");

    }

    /**
     * If there is an error with one of the sockets, or somewhere in the application where an Exception is thrown,
     * the Exception is sent back down the stack, handled by the Server and bubbled back up the application through this method
     * @param  ConnectionInterface $conn
     * @param  \Exception $e
     * @throws \Exception
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $this->logger()->error("Error: {$conn->resourceId} ({$conn->remoteAddress}) " . $e->getMessage() . "\n" . $e->getTraceAsString());
        $conn->close();
    }

    /**
     * Triggered when a client sends data through the socket
     * @param  \Ratchet\ConnectionInterface $from The socket/connection that sent the message to your application
     * @param  string $msg The message received
     * @throws \Exception
     */
    public function onMessage(ConnectionInterface $from, $msg)
    {

        $this->request = new Request($msg);
        $operation = $this->request->params()->get('action');

        $responseBuilder = new JsonResponseBuilder();
        $identity = null;

        try {
            $this->logger()->info("new incomming message ({$from->resourceId} ({$from->remoteAddress}:".($identity ? $identity->getId() : "empty")."))" . "; message: " . $msg);

            switch ($operation) {
                case 'init' :
                    $res = Balancer::getInstance($this->getConfiguration())->getConnector();
//                    $res = [];
//                    foreach (Balancer::getInstance()->getAvalableConnectors() as $avalableConnector) {
//                        $res[] = $avalableConnector->toArray();
//                    }
//                    $responseBuilder->setData($res->toArray());
                    $responseBuilder->setData(["host" => $res->getHost(), "port" => $res->getPort(), "connector" => $res->getName()]);

                    break;
                default: $responseBuilder->setError("Invalid request");

            }

            $from->send($responseBuilder->result());
            $from->close();
        }catch (\Exception $ex) {
            $responseBuilder->setError($ex->getMessage(), JsonResponseBuilder::ERROR_LEVEL_CRITICAL);
            $from->send($responseBuilder->result());
            $this->logger()->error("Error: " . $ex->getMessage() . "\n" . $ex->getTraceAsString());
            throw $ex;
        }

    }


    /**
     * @return LoggerInterface
     */
    private function logger()
    {
        if($this->_logger === null) {
           $this->_logger = Logger::createLogger( get_class($this). ":" .$this->getName() , $this->config->toArray());
        }
        return $this->_logger;
    }


    /**
     * @return mixed
     */
    public function getPubSub()
    {
        return $this->pubSub;
    }


    /**
     * @return \SplObjectStorage
     */
    public function getConnectionsPool()
    {
        return $this->clients;
    }

    /**
     * @return IdentityInterface
     */
    public function getIdentity(Properties $params)
    {
        if ($this->identityFinder !== null) {
            return $this->identityFinder->getIdentity($params);
        }
        return null;
    }

    /**
     * @param IdentityFinderInterface $finder
     */
    public function setIdentityFinder(IdentityFinderInterface $finder)
    {
        $this->identityFinder = $finder;
        if ($finder instanceof ConfigAwareInterface) {
            $finder->setConfiguration($this->getConfiguration());
        }
    }

    public function getConfiguration()
    {
        return $this->config;
    }

    public function setConfiguration(ConfigureInterface $config)
    {
        $this->config = $config;
    }

}