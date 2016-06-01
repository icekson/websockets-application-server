<?php
/**
 *
 * @author: a.itsekson
 * @date: 05.12.2015 12:06
 */

namespace Icekson\WsAppServer\Messaging\Amqp;




use Icekson\WsAppServer\Messaging\PubSub\CallbackInterface;
use Icekson\WsAppServer\Messaging\PubSub\PubSubInterface;
use Icekson\Utils\ParamsBag;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Icekson\Utils\Logger;

class PubSub implements PubSubInterface
{
    /**
     * @var null|AMQPStreamConnection
     */
    private $connection = null;

    /**
     * @var null| AMQPChannel
     */
    private $channel = null;

    private $serviceName = 'main-service';
    private $exchangeName = 'pubsub';

    private $subscriptions = [];
    /**
     * @var null|LoggerInterface
     */
    private $logger = null;

    /**
     * @param array $config
     * @param string $exchangeName
     * @param string $serviceName
     */
    public function __construct(array $config, $serviceName = 'websockets-service', $exchangeName = 'pubsub')
    {
        $this->logger = Logger::createLogger(get_class($this), $config);
        $this->serviceName = $serviceName;
        $this->exchangeName = $exchangeName;

        $host = $config['amqp']['host'];
        $port = $config['amqp']['port'];
        $user = $config['amqp']['user'];
        $password = $config['amqp']['password'];
        $vhost = $config['amqp']['vhost'];
        $this->connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);
        $this->initConnection();

    }

    private function initConnection()
    {
        if(!$this->connection->isConnected()){
            $this->connection->reconnect();
        }
        $this->channel = $this->connection->channel();
        $this->channel->exchange_declare($this->exchangeName, 'topic', false, true, false);
        if(!preg_match("/backend-server/", $this->serviceName)) {
            $this->channel->queue_declare($this->exchangeName . "." . $this->serviceName, false, true, false, false);
        }
    }

    public function __destruct()
    {
        try {
            $this->connection->close();
            $this->channel->close();
        } catch (\Exception $e) {

        }
    }

    /**
     * @param $topic
     * @param $subscriptionId
     * @param CallbackInterface $callback
     * @return mixed
     */
    public function subscribe($topic, $subscriptionId, CallbackInterface $callback)
    {
        $this->logger->info("Subscribe: topic - " . $topic . "; subscriptionId - $subscriptionId");
        $this->subscriptions[$topic] = $subscriptionId;
        if(preg_match("/\w+\.\*/i", $topic) || $subscriptionId === null){
            $routingKey = $topic;
        }else{
            $routingKey = $topic . "." . $subscriptionId;
        }
        $this->initConnection();
        $this->channel->queue_bind($this->exchangeName.".".$this->serviceName, $this->exchangeName, $routingKey);
    }

    /**
     * @param $topic
     * @param $subscriptionId
     * @return mixed
     */
    public function unsubscribe($topic, $subscriptionId)
    {
        $this->logger->info("Unsubscribe: topic - " . $topic . "; subscriptionId - $subscriptionId");
        if(preg_match("/\w+\.\*/i", $topic) || $subscriptionId === null){
            $routingKey = $topic;
        }else{
            $routingKey = $topic . "." . $subscriptionId;
        }
        $this->initConnection();
        $this->channel->queue_unbind($this->exchangeName.".".$this->serviceName, $this->exchangeName, $routingKey);
        if(isset($this->subscriptions[$topic])) {
            unset($this->subscriptions[$topic]);
        }
    }

    /**
     * @param $topic
     * @return mixed
     */
    public function unsubscribeAll($topic)
    {
        $this->logger->info("Unsubscribe all");
        foreach ($this->subscriptions as $topic => $subscriptionId) {
            $this->unsubscribe($topic, $subscriptionId);
        }
        $this->subscriptions = [];
    }

    /**
     * @param $topic
     * @param $publisherId
     * @param ParamsBag $context
     * @return mixed
     */
    public function publish($topic, $publisherId, ParamsBag $context)
    {
        $this->initConnection();
        $this->logger->info("Publish: topic - " . $topic . "; publisherId - $publisherId");
        $msg = new AMQPMessage(json_encode($context->toArray()), ['content_type' => 'application/json', 'delivery_mode' => 2]);
        $this->channel->basic_publish($msg, $this->exchangeName, $topic);
    }

    /**
     * @param $topic
     * @param $privateKey
     * @return mixed
     */
    public function register($topic, $privateKey)
    {
        // TODO: Implement register() method.
    }

    /**
     * @param $topic
     * @param $publisherId
     * @return mixed
     */
    public function unregister($topic, $publisherId)
    {
        // TODO: Implement unregister() method.
    }

    /**
     * @return bool
     */
    public function isEventRegistered($topic)
    {
        return true;
    }
}