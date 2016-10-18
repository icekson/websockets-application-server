<?php
/**
 *
 * @author: a.itsekson
 * @date: 05.12.2015 14:02
 */

namespace Icekson\WsAppServer\Rpc\Amqp;


use Bunny\Async\Client;
use Bunny\Channel;
use Bunny\Message;
use Icekson\Utils\Logger;
use Icekson\WsAppServer\Rpc\Handler\HandlerInterface;
use Icekson\WsAppServer\Rpc\RequestInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

abstract class Consumer
{
    protected $type = "fanout";
    protected $routingKey = "";
    /**
     * @var null|Channel
     */
    protected $channel = null;
    /**
     * @var null|Client
     */
    protected $client = null;

    private $serviceName = 'main-service';
    private $exchangeName = 'rpc';
    protected $queueName = "rpc";
    /**
     * @var null|LoggerInterface
     */
    protected $logger = null;

    /**
     * @var null|LoopInterface
     */
    protected $loop = null;

    /**
     * Consumer constructor.
     * @param array $config
     * @param LoopInterface $loop
     * @param HandlerInterface $listener
     * @param string $serviceName
     * @param string $exchangeName
     */
    public function __construct(array $config, LoopInterface $loop, $serviceName = 'backend-service', $exchangeName = 'rpc')
    {
        $this->logger = Logger::createLogger(get_class($this), $config);
        $this->serviceName = $serviceName;
        $this->exchangeName = $exchangeName;

        $this->loop = $loop;

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
            $queueName = $this->queueName;
            $this->channel = $channel;
            $channel->exchangeDeclare($this->exchangeName, $this->type, false, true, false);
            $channel->queueDeclare($queueName, false, true, false, false);
            if($this->type == "direct"){
                $channel->queueBind($queueName, $this->exchangeName, $this->routingKey);
            }else{
                $channel->queueBind($queueName, $this->exchangeName);
            }

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
        $this->logger->debug("start rpc consumer");
        $callback = function(Channel $channel){
            return $channel->consume([$this, 'processMsg'], $this->queueName, '', false, false, false, false);
        };
        $this->initConnection($callback);
    }

    abstract public function processMsg(Message $msg);
}