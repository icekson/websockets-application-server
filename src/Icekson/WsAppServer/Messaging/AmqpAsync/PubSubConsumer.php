<?php
/**
 * User: a.itsekson
 * Date: 05.11.2015
 * Time: 13:37
 */

namespace Icekson\WsAppServer\Messaging\AmqpAsync;


use Bunny\Async\Client;
use Bunny\Channel;
use Bunny\Message;
use Icekson\WsAppServer\Service\Support\PubSubListenerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Icekson\Utils\Logger;
use React\EventLoop\LoopInterface;
use React\Promise\Promise;

class PubSubConsumer
{

    /**
     * @var null|Channel
     */
    private $channel = null;
    /**
     * @var null|Client
     */
    private $client = null;

    private $serviceName = 'main-service';
    private $exchangeName = 'pubsub';
    /**
     * @var null|LoggerInterface
     */
    private $logger = null;
    /**
     * @var PubSubListenerInterface|null
     */
    private $pubSubListener = null;

    /**
     * @var null|LoopInterface
     */
    private $loop = null;
    private $routingKey = "#";
    /**
     * PubSubConsumer constructor.
     * @param array $config
     * @param LoopInterface $loop
     * @param PubSubListenerInterface $listener
     * @param string $serviceName
     * @param string $exchangeName
     * @params string $routingKey
     */
    public function __construct(array $config, LoopInterface $loop, PubSubListenerInterface $listener, $serviceName = 'main-service', $exchangeName = 'pubsub', $routingKey = "#")
    {
        $this->logger = Logger::createLogger(get_class($this), $config);
        $this->serviceName = $serviceName;
        $this->exchangeName = $exchangeName;
        $this->pubSubListener = $listener;
        $this->loop = $loop;
        $this->routingKey = $routingKey;

        $host = $config['amqp']['host'];
        $port = $config['amqp']['port'];
        $user = $config['amqp']['user'];
        $password = $config['amqp']['password'];
        $vhost = $config['amqp']['vhost'];

        $this->client = new Client($loop, [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'password' => $password,
            'vhost' => $vhost
        ], Logger::createLogger("AmqpClient"));

        //$this->initConnection();

    }

    public function dispose()
    {
        try {
            $this->channel->close()->then(function(){
                $this->client->disconnect();
            });
        } catch (\Throwable $e) {

        }
    }

    /**
     * @param callable $callback
     */
    private function initConnection(callable $callback)
    {
        $this->logger->debug("init connection");
        if (!$this->client->isConnected()) {
            $this->logger->debug("amqp connection is closed, connect...");
            $promise = $this->client->connect();
        } else {
            $promise = \React\Promise\all([function () {
                return $this->client;
            }]);
        }



        $promise->then(function (Client $client) {
            $this->logger->debug("connection established");
            return $client->channel();
        })->then(function (Channel $channel) use($callback){
            $this->logger->debug("init channel");
            $this->channel = $channel;
            $channel->exchangeDeclare($this->exchangeName, 'topic', false, true, false);
            $channel->queueDeclare($this->exchangeName . "." . $this->serviceName, false, true, false, false);
            $channel->queueBind($this->exchangeName . "." . $this->serviceName, $this->exchangeName, $this->routingKey);
            $callback($channel);
        });
    }

    public function __destruct()
    {
        try {
            $this->channel->close()->then(function(){
                $this->client->disconnect();
            });
        } catch (\Throwable $e) {

        }
    }

    /**
     *
     */
    public function consume()
    {
        $this->logger->debug("start pubsub consumer");
        $callback = function(Channel $channel){
            return $channel->consume([$this, 'proccessMsg'], $this->exchangeName . "." . $this->serviceName, '', false, false, false, false);
        };
        $this->initConnection($callback);
    }

    public function proccessMsg(Message $msg)
    {
        $this->logger->debug("new pubsub message consumed");
        $consumer = $this;
        $routingKey = $msg->routingKey;
        $message = '{"event": "' . $routingKey . '", "event_data": ' . $msg->content . '}';
        $socket = null;
        try {
            $this->pubSubListener->onPubSubMessage($message);
            $consumer->channel->ack($msg);
            $this->logger->debug("pubsub message processed");
        } catch (\Throwable $ex) {
            $consumer->channel->nack($msg);
            throw $ex;
        }
    }

}