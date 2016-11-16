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
use Api\Service\UserIdentity;
use Api\Service\Util\Properties;
use Icekson\Utils\Logger;
use Icekson\Config\ConfigAwareInterface;
use Icekson\Config\ConfigureInterface;
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

use Ratchet\WebSocket\Version\RFC6455\Frame;
use React\EventLoop\LoopInterface;
use Ratchet\MessageComponentInterface;
use Api\Service\IdentityFinderInterface;

class ConnectorHandler implements MessageComponentInterface, ConfigAwareInterface, ResponseHandlerInterface, PubSubListenerInterface
{

    const VERSION = '1.1.0';

    private $clients;

    private $users;

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

    private $subscriptions = [];
    private $name = "websockets-service";

    /**
     * @var RPC|null
     */
    private $rpc = null;

    private $waitingRpcResponses = [];

    private $waitingRpcRequests = [];

    private $waitingEventSubscriptions = [];

    private $waitingEventNotifications = [];

    private $waitingRequestsLifetime = 20;

    /**
     * @var null|\ArrayObject
     */
    private $rpcQueue = null;


    /**
     * @var null|LoopInterface
     */
    private $loop = null;

    public function getName()
    {
        return $this->name;
    }

    /**
     * @var null|ConnectionStateChanged[]|\SplObjectStorage
     */
    private $connectionStateCallbacks = [];

    /**
     * Handler constructor.
     * @param $name
     * @param LoopInterface $loop
     * @param ConfigureInterface $config
     */
    public function __construct($name, LoopInterface $loop, ConfigureInterface $config)
    {
        $this->name = $name;
        $this->config = $config;
        $this->clients = new \SplObjectStorage;
        $this->users = new \ArrayObject();

        $this->loop = $loop;
        $this->pubSub = new AMQPPubSub($config->toArray(), $this->getName());
        $this->rpcQueue = new \ArrayObject();

        $this->rpc = new RPC(RPC::TYPE_REQUEST, $loop, $this, $config->get("name"), $this->getConfiguration()->toArray());

        $this->connectionStateCallbacks = new \SplObjectStorage();
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
        Balancer::getInstance($this->getConfiguration())->attachConnection($this->getName());
        $this->logger()->info("new connection $conn->resourceId ({$conn->remoteAddress}) : " . $this->clients->count() . " connected");

    }

    /**
     * This is called before or after a socket is closed (depends on how it's closed).  SendMessage to $conn will not result in an error if it has already been closed.
     * @param  ConnectionInterface $conn The socket/connection that is closing/closed
     * @throws \Exception
     */
    public function onClose(ConnectionInterface $conn)
    {
        $this->onDisconnected($conn);
        if(isset($this->rpcQueue[$conn->resourceId])){
            try {
                $this->waitingRpcRequests[$this->users[$conn->resourceId]->getId()] = [
                    'requests' => $this->rpcQueue[$conn->resourceId],
                    'timestamp' => time()
                ];
            }catch(\Throwable $ex){}
        }

        if (isset($this->subscriptions[$conn->resourceId])) {
            $this->logger()->debug("found subscriptions unnesesary subscribtions: " . json_encode($this->subscriptions[$conn->resourceId]) . " unsubscribe");
            $identity = $this->users[$conn->resourceId];
            if($identity !== null) {
                $userId = $identity->getId();
                $this->waitingEventSubscriptions[$userId] = $this->subscriptions[$conn->resourceId];
                foreach ($this->subscriptions[$conn->resourceId] as $event => $subscription) {
                    $this->pubSub->unsubscribe($event, $subscription);
                }
                $this->loop->addTimer($this->waitingRequestsLifetime, function () use ($conn, $userId) {
                    if (isset($this->waitingEventSubscriptions[$userId])) {
                        unset($this->waitingEventSubscriptions[$userId]);
                    }
                });
            }
        }

        $this->clients->detach($conn);
        if (isset($this->users[$conn->resourceId])) {
            unset($this->users[$conn->resourceId]);
        }
        Balancer::getInstance($this->getConfiguration())->detachConnection($this->getName());
        $this->logger()->info("close connection $conn->resourceId ({$conn->remoteAddress}):  " . $this->clients->count() . " connected");

    }


    /**
     * Used for publish online status of user
     * @param ConnectionInterface $connection
     */
    public function onConnected(ConnectionInterface $connection)
    {
        foreach ($this->connectionStateCallbacks as $connectionStateCallback) {
            $user = isset($this->users[$connection->resourceId]) ? $this->users[$connection->resourceId] : null;
            $connectionStateCallback->onConnected($user);
        }
    }

    /**
     * Used for publish offline status of user
     * @param ConnectionInterface $connection
     */
    public function onDisconnected(ConnectionInterface $connection)
    {
        foreach ($this->connectionStateCallbacks as $connectionStateCallback) {
            $user = isset($this->users[$connection->resourceId]) ? $this->users[$connection->resourceId] : null;
            $connectionStateCallback->onDisconnected($user);
        }
    }

    /**
     * @param ConnectionStateChanged $callback
     * @return $this
     */
    public function registerConnectionStateCallback(ConnectionStateChanged $callback)
    {
        $this->connectionStateCallbacks->attach($callback);
        return $this;
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
        //$this->getServiceLocator()->get('EventManager')->trigger('error', $this, array('exception' => $e));
    }

    /**
     * Triggered when a client sends data through the socket
     * @param  \Ratchet\ConnectionInterface $from The socket/connection that sent the message to your application
     * @param  string $msg The message received
     * @throws \Exception
     */
    public function onMessage(ConnectionInterface $from, $msg)
    {

        if ($msg === '--heartbeat--') {
            $from->send(json_encode(array('data' => "I am here")));
            return;
        }

        $this->request = new Request($msg);
        $request = $this->request->params()->toArray();
        $operation = $this->request->params()->get('action');

        $url = $this->request->params()->get('url');
        $requestId = $this->request->params()->get('requestId');

        $subscriptionId = $this->request->params()->get('subscriptionId');
        $publisherId = $this->request->params()->get('publisherId');
        $event = $this->request->params()->get('event');
        $service = preg_replace("/([\w_-]+)\/.*/i", "$1", $url);
        $action = preg_replace("/.*\/([\w_-]+)/i", "$1", $url);

        $responseBuilder = new JsonResponseBuilder();
        $identity = null;

        try {

            $identity = $this->getIdentity(new Properties($this->request->params()->toArray()));
            if (empty($identity)) {
                throw new BadTokenException("Bad token is given");
            }
            $this->setIdentity($from, $identity);

            $this->logger()->info("new incomming message ({$from->resourceId} ({$from->remoteAddress}:" . ($identity ? $identity->getId() : "empty") . "))" . "; message: " . $msg);

            switch ($operation) {
                case 'subscribe' :
                    if (empty($event)) {
                        throw new \InvalidArgumentException("event name parameter is empty");
                    }
                    if (isset($this->subscriptions[$from->resourceId]) && isset($this->subscriptions[$from->resourceId][$event])) {
                        throw new PubSub\Exception\PubSubException("You have already subscribed to event " . $event);
                    }
                    $this->subscriptions[$from->resourceId][$event] = $subscriptionId;
                    $this->logger()->info("Subscribe: " . $this->request->params()->toArray()['event'] . " - " . $subscriptionId);
                    if ($identity) {
                        $id = $identity->getId();
                    } else {
                        $id = -1;
                    }
                    $this->pubSub->subscribe($event, $id, new PubSub\Subscriber(function () {}));
                    $this->findAndProcessWaitingEventNotifications($identity);
                    $responseBuilder->addCustomElement("subscriptionId", $subscriptionId);
                    $responseBuilder->addCustomElement("subscibed_event", $event);

                    break;
                case 'unsubscribe' :
                    if (empty($event)) {
                        throw new \InvalidArgumentException("event name parameter is empty");
                    }

                    $id = $identity->getId();
                    $this->pubSub->unsubscribe($event, $id);
                    break;
                case 'publish' :
                    if($this->getConfiguration()->get('is_debug')) {
                        // TODO: remve publish action from here
                        if (empty($event)) {
                            throw new \InvalidArgumentException("event name parameter is empty");
                        }
                        $params = $this->request->params()->get('params', array());
                        $this->logger()->info("Publish: event - $event; publisherId - $publisherId");
                        $this->logger()->debug("Publish data: " . json_encode($params));

                        $this->pubSub->publish($event, $publisherId, new PubSub\Utils\ParamsBag($params));
                    }
                    break;

                case 'rpc' :
                    $params = $this->request->params()->get('params', array());
                    $params['token'] = $this->request->params()->get('token');
                    $this->logger()->info("RPC: $service/$action");
                    $this->logger()->debug("rpc params", $params);
                    $this->rpcQueue[$from->resourceId][] = [
                        'user' => $identity,
                        'requestId' => $requestId
                    ];
                    $this->sendRequest($requestId, "$service/$action", $params);
                    return;
                default:
                    $responseBuilder->setError("Invalid request");
            }

            $from->send($responseBuilder->result());

        } catch (PubSub\Exception\PubSubException $ex) {
            $resp = $responseBuilder;
            $resp->setError($ex->getMessage(), JsonResponseBuilder::ERROR_LEVEL_WARNING);
            $this->logger()->warning('exception:' . $ex->getMessage());
            $from->send($responseBuilder->result());
        } catch (NoTokenException $ex) {
            $responseBuilder->setStatusCode(Builder::STATUS_CODE_BAD_TOKEN);
            $responseBuilder->setError($ex->getMessage());
            $this->logger()->warning($ex->getMessage());
            $from->send($responseBuilder->result());
        } catch (BadTokenException $ex) {
            $responseBuilder->setStatusCode(Builder::STATUS_CODE_BAD_TOKEN);
            $responseBuilder->setError($ex->getMessage());
            $this->logger()->warning("Bad token is given: " . $this->request->params()->get('token'));
            $from->send($responseBuilder->result());
        } catch (\InvalidArgumentException $ex) {
            $responseBuilder->setStatusCode(Builder::STATUS_CODE_ERROR);
            $responseBuilder->setError($ex->getMessage());
            $this->logger()->warning('exception:' . $ex->getMessage());
            $from->send($responseBuilder->result());
        } catch (\Throwable $ex) {

            $responseBuilder->setError($ex->getMessage(), JsonResponseBuilder::ERROR_LEVEL_CRITICAL);
            $from->send($responseBuilder->result());
            $this->logger()->error("Error: " . $ex->getMessage() . "\n" . $ex->getTraceAsString());
            throw $ex;
        }

    }

    private function setIdentity(ConnectionInterface $connection, IdentityInterface $identity)
    {
        $isNewOne = false;
        if ((!isset($this->users[$connection->resourceId]) || (isset($this->users[$connection->resourceId]) && $this->users[$connection->resourceId]->getId() === null)) && $identity->getId() !== null) {
            $isNewOne = true;
        }
        if($identity->getId() !== null && $identity->getId() > 0) {
            $this->users[$connection->resourceId] = $identity;
        }
        if ($isNewOne) {
            $this->onConnected($connection);
            $this->findAndProcessWaitingRequests($identity, $connection);
        }
    }

    /**
     * @param IdentityInterface $identity
     * @param ConnectionInterface $conn
     */
    private function findAndProcessWaitingRequests(IdentityInterface $identity, ConnectionInterface $conn)
    {
        if(isset($this->waitingRpcResponses[$identity->getId()])){
            foreach ($this->waitingRpcResponses[$identity->getId()] as $waitingRpcRequest) {
                /** @var ResponseInterface $resp */
                $resp = $waitingRpcRequest['response'];
                $conn->send($resp->serializeToClientFormat());
                $this->logger()->debug("waiting RPC response is send to client: " . $conn->resourceId . ", userId: ".$identity->getId());
            }
            unset($this->waitingRpcResponses[$identity->getId()]);
        }

        if(isset($this->waitingRpcRequests[$identity->getId()])){
            if(time() <= ($this->waitingRpcRequests[$identity->getId()]['timestamp'] + $this->waitingRequestsLifetime)){
                $this->rpcQueue[$conn->resourceId] = $this->waitingRpcRequests[$identity->getId()]['requests'];
            }
            unset($this->waitingRpcRequests[$identity->getId()]);
        }
    }

    private function findAndProcessWaitingEventNotifications(IdentityInterface $identity)
    {
        if(isset($this->waitingEventNotifications[$identity->getId()]) && is_array($this->waitingEventNotifications[$identity->getId()]))
        {
            $this->logger()->debug("process waiting event notifications (".count($this->waitingEventNotifications[$identity->getId()]).") for user {$identity->getId()}");
            foreach ($this->waitingEventNotifications[$identity->getId()] as $msg) {
                $this->onPubSubMessage($msg);
            }
            unset($this->waitingEventNotifications[$identity->getId()]);
        }
    }


    /**
     * @param $id
     * @return ConnectionInterface[]
     */
    private function findConnectionsByUserId($id)
    {
        $res = [];
        foreach ($this->clients as $conn) {
            if (isset($this->users[$conn->resourceId]) && $this->users[$conn->resourceId]->getId() == $id) {
                $res[] = $conn;
            }
        }
        return $res;
    }

    /**
     * @param $id
     * @return array
     */
    private function findConnectionsByRequestId($id)
    {
        $res = [];

        foreach ($this->rpcQueue as $connectionId =>  $requestData) {
            foreach ($requestData as $data) {
                if(isset($data['requestId']) && $data['requestId'] == $id){
                    $connection = null;
                    foreach ($this->clients as $conn) {
                        if($conn->resourceId == $connectionId){
                            $connection = $conn;
                            break;
                        }
                    }
                    $res[] = [
                        'connection' => $connection,
                        'user' => $data['user']
                    ];
                    break;
                }
            }
        }
        return $res;
    }


    /**
     * @return LoggerInterface
     */
    private function logger()
    {
        if ($this->_logger === null) {
            $this->_logger = Logger::createLogger(get_class($this) . ":" . $this->getName(), $this->config->toArray());
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
     * @return \ArrayObject
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     * @return \SplObjectStorage
     */
    public function getConnectionsPool()
    {
        return $this->clients;
    }

    /**
     *
     */
    public function pingConnections()
    {
        $this->logger()->debug("ping connections, " . count($this->getConnectionsPool()) . " active connections");

        /** @var ConnectionInterface $conn */
        foreach ($this->getConnectionsPool() as $conn) {
            $msg = new Frame(0, true, Frame::OP_PING);
            $conn->send($msg);
        }
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

    /**
     * @desc RPC Response handler
     * @param ResponseInterface $resp
     */
    public function onResponse(ResponseInterface $resp)
    {
        $this->logger()->info("on rpc response: " . $resp->serialize());
        $consData = $this->findConnectionsByRequestId($resp->getRequestId());
        $this->logger()->debug("on rpc response: found connections: " . count($consData));
        foreach ($consData as $data) {
            $conn = $data['connection'];
            $user = $data['user'];
            if($conn !== null) {
                $conn->send($resp->serializeToClientFormat());
                $this->logger()->debug("on rpc response: send resp to client: " . $conn->resourceId);
                if (isset($this->rpcQueue[$conn->resourceId])) {
                    $index = array_search($resp->getRequestId(), $this->rpcQueue[$conn->resourceId]);
                    if ($index > -1) {
                        unset($this->rpcQueue[$conn->resourceId][$index]);
                    }
                }

            }else{
                // there is no connection available for such response, so we need to wait for new connection from
                // particular user for sending response to him
                if(!isset($this->waitingRpcResponses[$user->getId()])){
                    $this->waitingRpcResponses[$user->getId()] = [];
                }
                $this->waitingRpcResponses[$user->getId()][$resp->getRequestId()] = [
                    'requestId' => $resp->getRequestId(),
                    'response' => $resp
                ];
                $this->loop->addTimer($this->waitingRequestsLifetime, function() use ($user, $resp){
                    if(isset($this->waitingRpcResponses[$user->getId()]) && isset($this->waitingRpcResponses[$user->getId()][$resp->getRequestId()])){
                        $this->logger()->debug("timer is exceeded for response with requestId: " . $resp->getRequestId());
                        unset($this->waitingRpcResponses[$user->getId()][$resp->getRequestId()]);
                    }
                });
            }
        }
    }

    /**
     * @param $requestId
     * @param $url
     * @param array $params
     */
    public function sendRequest($requestId, $url, array $params)
    {
        $this->rpc->sendRequest($requestId, $url, $params);
    }

    /**
     * @desc PubSub Handler
     * @param $msg
     * @return void
     */
    public function onPubSubMessage($msg)
    {
        $this->logger()->debug("onPubSubMessage: " . $msg);

        $message = @json_decode($msg);
        if (empty($message) || !isset($message->event) || !isset($message->event_data)) {
            $this->logger()->error("Invalid message received: " . $msg);
            return;
        }

        $eventName = $message->event;
        $data = $message->event_data;

        $userId = null;

        $eventType = PubSub\Topic::EVENT_TYPE_ADDRESS;
        if (preg_match("/\w+\.\*$/i", $eventName)) {
            $eventType = PubSub\Topic::EVENT_TYPE_MULTICAST;
        }

        $tmp = explode('.', $eventName);
        if ($eventType == PubSub\Topic::EVENT_TYPE_MULTICAST) {
            $conns = $this->getConnectionsPool();
            $topic = implode('.', array_slice($tmp, 0, count($tmp) - 1));
        } else {
            $userId = $tmp[count($tmp) - 1];
            $conns = $this->findConnectionsByUserId($userId);
            $topic = implode('.', array_slice($tmp, 0, count($tmp) - 1));
        }
        if (empty($conns)) {
            $this->logger()->debug("onPubSubMessage: No connection is found for user: " . ($userId ? $userId : "multicast"));
        }
        if (count($conns) > 0) {
            if (!empty($userId)) {
                $this->logger()->debug("onPubSubMessage: " . count($conns) . " connections for user: " . $userId);
            } else {
                $this->logger()->debug("onPubSubMessage: " . count($conns) . " connections all connections (multicast)");
            }
            foreach ($conns as $conn) {
                $subscription = null;
                $userId = isset($this->users[$conn->resourceId]) ? $this->users[$conn->resourceId]->getId() : "";
                $isFound = false;
                if (isset($this->subscriptions[$conn->resourceId])) {
                    foreach ($this->subscriptions[$conn->resourceId] as $subscribedEvent => $subscr) {
                        $subscription = $subscr;
                        if (preg_match("/\w+\.\*$/", $subscribedEvent)) {
                            $tmp = explode('.', $subscribedEvent);
                            $pattern = implode('.', array_slice($tmp, 0, count($tmp) - 1));
                            if(preg_match("/$pattern\.[\w\._-]+/", $topic)){
                                $isFound = true;
                                break;
                            }
                        }else{
                            if ($subscribedEvent == $topic) {
                                $isFound = true;
                                break;
                            }
                        }
                    }
                }

                if ($isFound) {
                    $this->logger()->info("onPubSubMessage: Send $eventName event message to user: " . $userId . " in connection resourceId: " . $conn->resourceId);
                    $resp = new JsonResponseBuilder();
                    $resp->addCustomElement('event', preg_replace("/(.*)\.(\*|\d+)/", "$1", $eventName));
                    $resp->addCustomElement('subscriptionId', $subscription);
                    $resp->setData($data);
                    $conn->send($resp->result());
                    $resp = null;
                } else {
                    $this->logger()->debug("onPubSubMessage: no subscriptions for event $eventName for user $userId and  connection resourceId: " . $conn->resourceId);
                }
            }

        }else {
            if ($userId) {
                if (isset($this->waitingEventNotifications[$userId]) && !is_array($this->waitingEventNotifications[$userId])) {
                    $this->waitingEventNotifications[$userId] = [];
                }
                $key = md5($msg);
                $this->waitingEventNotifications[$userId][$key] = $msg;
                $this->loop->addTimer($this->waitingRequestsLifetime, function () use ($userId, $key) {
                    if (isset($this->waitingEventNotifications[$userId][$key])) {
                        unset($this->waitingEventNotifications[$userId][$key]);
                    }
                });
            }
        }
    }
}