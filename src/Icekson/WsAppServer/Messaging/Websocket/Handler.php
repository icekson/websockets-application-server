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
use Icekson\WsAppServer\Config\ConfigAwareInterface;
use Icekson\WsAppServer\Config\ConfigureInterface;
use Icekson\WsAppServer\Messaging\AMQPPubSub;


use Icekson\WsAppServer\Messaging\PubSub;
use Icekson\Base\Request;
use Psr\Log\LoggerInterface;
use Ratchet\ConnectionInterface;
use Api\Service\Response\JsonBuilder as JsonResponseBuilder;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Ratchet\MessageComponentInterface;
use Api\Service\IdentityFinderInterface;

class Handler implements MessageComponentInterface,ConfigAwareInterface
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
     * @var null|\ArrayObject
     */
    private $internalClients = null;

    /**
     * @var null|IdentityFinderInterface
     */
    private $identityFinder = null;
    /**
     * @var ConfigureInterface|null
     */
    private $config = null;

    private $subscriptions = [];
    private $name ="websockets-service";

    public function getName()
    {
        return $this->name;
    }

    public function __construct($name, ConfigureInterface $config)
    {
        $this->name = $name;
        $this->config = $config;
        $this->clients = new \SplObjectStorage;
        $this->users = new \ArrayObject();

        $this->pubSub = new AMQPPubSub($config->toArray(), $this->getName());
        $this->internalClients = new \ArrayObject();

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
        if (isset($this->users[$conn->resourceId])) {
            unset($this->users[$conn->resourceId]);
        }
        $this->logger()->info("close connection $conn->resourceId ({$conn->remoteAddress}):  " . $this->clients->count() . " connected");

        if(isset($this->subscriptions[$conn->resourceId])){
            $this->logger()->debug("found subscriptions unnesesary subscribtions: " . json_encode($this->subscriptions[$conn->resourceId]) . " unsubscribe");
            foreach($this->subscriptions[$conn->resourceId] as $event => $subscription){
                $this->pubSub->unsubscribe($event, $subscription);
            }
        }

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
        $pubSubPrivateKey = $this->request->params()->get('privateKey');
        $subscriptionId = $this->request->params()->get('subscriptionId');
        $publisherId = $this->request->params()->get('publisherId');
        $event = $this->request->params()->get('event');
        $service = preg_replace("/([\w_-]+)\/.*/i", "$1", $url);
        $action = preg_replace("/.*\/([\w_-]+)/i", "$1", $url);

        $responseBuilder = new JsonResponseBuilder();
        $handler = $this;
        $identity = null;

        try {

//            $identity = $this->getIdentity(new Properties($this->request->params()->toArray()));
//            if (empty($identity) || $identity->getId() === null) {
//                throw new BadTokenException("Bad token is given");
//            }
//            $this->users[$from->resourceId] = $identity;
//            foreach ($this->internalClients as $client) {
//                $client->init($this, $identity);
//            }

            $this->logger()->info("new incomming message ({$from->resourceId} ({$from->remoteAddress}:".($identity ? $identity->getId() : "empty")."))" . "; message: " . $msg);

            switch ($operation) {
                case 'init' :

                    break;
                case 'subscribe' :
                    if (empty($event)) {
                        throw new \InvalidArgumentException("event name parameter is empty");
                    }

                    if(isset($this->subscriptions[$from->resourceId]) && isset($this->subscriptions[$from->resourceId][$event])){
                        throw new PubSub\Exception\PubSubException("You have already subscribed to event ". $event);
                    }
                    $this->subscriptions[$from->resourceId][$event] = $subscriptionId;
                    $this->logger()->info("Subscribe: " . $this->request->params()->toArray()['event'] . " - " . $subscriptionId);
                    $this->notify('subscribe',$from, new PubSub\Utils\ParamsBag($this->request->params()->toArray()), $responseBuilder);
                    $id = $identity->getId();
                    $this->pubSub->subscribe($event, $id, new PubSub\Subscriber(function(){}));
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
                    // TODO: remve publish action from here
                    if (empty($event)) {
                        throw new \InvalidArgumentException("event name parameter is empty");
                    }
                    $params = $this->request->params()->get('params', array());
                    $this->logger()->info("Publish: event - $event; publisherId - $publisherId");
                    $this->logger()->debug("Publish data: " . json_encode($params));
                    $this->notify('publish',$from, new PubSub\Utils\ParamsBag($this->request->params()->toArray()), $responseBuilder);
                    $this->pubSub->publish($event, $publisherId, new PubSub\Utils\ParamsBag($params));
                    break;

                case 'rpc' :
                    $params = $this->request->params()->get('params', array());
                    $params['token'] = $this->request->params()->get('token');
                    $this->logger()->info("RPC: $service/$action");
                    $this->logger()->debug("rpc params", $params);
                    //TODO: add rpc via amqp


                    break;
                default:
                    $responseBuilder->setError("Invalid request");
            }
            $this->notify('processed',$from, new PubSub\Utils\ParamsBag($this->request->params()->toArray()), $responseBuilder);

            $from->send($responseBuilder->result());

        } catch (PubSub\Exception\PubSubException $ex) {
            $resp = $responseBuilder;
            $resp->setError($ex->getMessage(), JsonResponseBuilder::ERROR_LEVEL_WARNING);
            $this->logger()->warning('exception:' . $ex->getMessage());
            $from->send($responseBuilder->result());
        } catch (NoTokenException $ex) {
            $responseBuilder->setStatusCode(Builder::STATUS_CODE_BAD_TOKEN);
            $responseBuilder->setError($ex->getMessage());
            $this->logger()->warning('exception:' . $ex->getMessage());
            $from->send($responseBuilder->result());
        } catch (BadTokenException $ex) {
            $responseBuilder->setStatusCode(Builder::STATUS_CODE_BAD_TOKEN);
            $responseBuilder->setError($ex->getMessage());
            $this->logger()->warning('exception:' . $ex->getMessage());
            $from->send($responseBuilder->result());
        } catch (\InvalidArgumentException $ex){
            $responseBuilder->setStatusCode(Builder::STATUS_CODE_ERROR);
            $responseBuilder->setError($ex->getMessage());
            $this->logger()->warning('exception:' . $ex->getMessage());
            $from->send($responseBuilder->result());
        }catch (\Exception $ex) {

            $responseBuilder->setError($ex->getMessage(), JsonResponseBuilder::ERROR_LEVEL_CRITICAL);
            $from->send($responseBuilder->result());
            $this->logger()->error("Error: " . $ex->getMessage() . "\n" . $ex->getTraceAsString());
            throw $ex;
        }

    }

    public function onPubsubEventPublished($msg)
    {
       // $msg = base64_decode($msg);
        $this->logger()->info("onPubSubEventPublished: " . $msg);

        $message = @json_decode($msg);
        if(empty($message) || !isset($message->event) || !isset($message->event_data)){
            $this->logger()->error("Invalid message received: " . $msg);
            return;
        }

        $eventName = $message->event;
        $data = $message->event_data;

        $tmp = explode('.', $eventName);
        $userId = $tmp[count($tmp)-1];
        $conns = $this->findConnectionsByUserId($userId);
        if(empty($conns)){
            $this->logger()->warning("onPubSubEventPublished: No connection is found for user: " . $userId);
        }
        if(count($conns) > 0){
            $this->logger()->info("onPubSubEventPublished: ".count($conns)." connections for user: " . $userId);
            $topic = implode('.',array_slice($tmp, 0, count($tmp)-1));
            foreach ($conns as $conn) {
                if(isset($this->subscriptions[$conn->resourceId][$topic])) {
                    $this->logger()->debug("onPubSubEventPublished: Send $topic event message to user: " . $userId . " in connection resourceId: " . $conn->resourceId);
                    $resp = new JsonResponseBuilder();
                    $resp->addCustomElement('event', $topic);
                    $resp->addCustomElement('subscriptionId', $this->subscriptions[$conn->resourceId][$topic]);
                    $resp->setData($data);
                    $conn->send($resp->result());
                    $resp = null;
                }else{
                    $this->logger()->warning("onPubSubEventPublished: no subscriptions for event $eventName for user $userId and  connection resourceId: " . $conn->resourceId);
                }
            }

        }
    }

    /**
     * @param $id
     * @return ConnectionInterface[]
     */
    private function findConnectionsByUserId($id)
    {
        $res = [];
        foreach($this->clients as $conn){
            if(isset($this->users[$conn->resourceId]) && $this->users[$conn->resourceId]->getId() == $id){
                $res[] = $conn;
            }
        }
        return $res;
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
     * @param $type
     * @param ConnectionInterface $conn
     * @param PubSub\Utils\ParamsBag $params
     * @param Builder $response
     */
    public function notify($type, ConnectionInterface $conn, PubSub\Utils\ParamsBag $params, Builder &$response)
    {

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