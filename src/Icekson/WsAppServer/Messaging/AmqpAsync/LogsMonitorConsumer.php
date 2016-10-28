<?php
/**
 * User: a.itsekson
 * Date: 05.11.2015
 * Time: 13:37
 */

namespace Icekson\WsAppServer\Messaging\AmqpAsync;


use Bunny\Async\Client;
use Bunny\Channel;
use Bunny\Exception\BunnyException;
use Bunny\Message;
use Bunny\Protocol\MethodQueueDeclareOkFrame;
use Icekson\WsAppServer\Service\Support\PubSubListenerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Icekson\Utils\Logger;
use React\EventLoop\LoopInterface;
use React\Promise\Promise;

class LogsMonitorConsumer
{

    /**
     * @var null|Channel
     */
    private $channel = null;
    /**
     * @var null|Client
     */
    private $client = null;

    private $queueName = 'monitor-logs';
    private $exchangeName = 'monitor-log';

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
    public function __construct(array $config, LoopInterface $loop, PubSubListenerInterface $listener, $routingKey = "#",$queueName = "monitor-logs")
    {

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
        ]);

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

        if (!$this->client->isConnected()) {
            $promise = $this->client->connect();
        } else {
            $promise = \React\Promise\all([function () {
                return $this->client;
            }]);
        }

        $promise->then(function (Client $client) {
            return $client->channel();
        })->then(function (Channel $channel) use($callback){
            $this->channel = $channel;
            $channel->exchangeDeclare($this->exchangeName, 'topic', false, true, false);
            $channel->queueDeclare($this->queueName, false, true, false, true);
            $channel->queueBind($this->queueName, $this->exchangeName, $this->routingKey);
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
        $callback = function(Channel $channel){
            return $channel->consume([$this, 'proccessMsg'], $this->queueName, '', false, false, false, false);
        };
        $this->initConnection($callback);
    }

    public function proccessMsg(Message $msg)
    {
        $consumer = $this;
        $routingKey = "log." . $msg->routingKey;
        $routingKey = "log." . preg_replace("/[\/\\\]+/", ".", $msg->routingKey);
        $message = '{"event": "' . $routingKey . '", "event_data": ' . $msg->content . '}';
        $socket = null;
        try {
            $this->pubSubListener->onPubSubMessage($message);
            $consumer->channel->ack($msg);
        } catch (\Exception $ex) {
            $consumer->channel->ack($msg);
            throw $ex;
        }
    }

}