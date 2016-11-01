<?php
/**
 *
 * @author: a.itsekson
 * @date: 05.12.2015 14:01
 */

namespace Icekson\WsAppServer\Rpc\Amqp;


use Icekson\Utils\Logger;
use Icekson\WsAppServer\Rpc\RequestInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

use PhpAmqpLib\Wire\AMQPTable;
use Psr\Log\LoggerInterface;

class RequestQueue
{
    /**
     * @var null|AMQPStreamConnection
     */
    private $connection = null;

    /**
     * @var null| AMQPChannel
     */
    private $channel = null;

    private $queueName = '';
    private $exchangeName = '';

    /**
     * @var null|LoggerInterface
     */
    private $logger = null;

    private $channelId = -1;

    /**
     * RequestQueue constructor.
     * @param array $config
     * @param null $exchangeName
     */
    public function __construct(array $config, $exchangeName = null)
    {
        $this->logger = Logger::createLogger(get_class($this), $config);
        if($exchangeName === null){
            $exchangeName = 'rpc-request';
        }

        $this->exchangeName = $exchangeName;

        $host = $config['amqp']['host'];
        $port = $config['amqp']['port'];
        $user = $config['amqp']['user'];
        $password = $config['amqp']['password'];
        $vhost = $config['amqp']['vhost'];
        $this->connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);
        $this->channelId = mt_rand(1, 65535);
        // $this->initConnection();

    }

    private function initConnection()
    {
        if(!$this->connection->isConnected()){
            $this->connection->reconnect();
        }

        $this->channel = $this->connection->channel($this->channelId);
        $this->channel->exchange_declare($this->exchangeName, 'direct', false, true, false);
        //$this->channel->queue_declare($this->queueName, false, true, false, false, false);
    }

    public function __destruct()
    {
        try {
            if($this->channel){
                $this->channel->close();
            }
            if($this->connection) {
                $this->connection->close();
            }
        } catch (\Exception $e) {

        }
    }

    /**
     * @param RequestInterface $request
     */
    public function enqueue(RequestInterface $request)
    {
        $this->initConnection();
        $this->logger->info("enqueue request : {$request->getRequestId()}, replyTo: {$request->getReplyTo()}");
        $body = $request->serialize();
        $msg = new AMQPMessage($body, ['content_type' => 'application/json', 'delivery_mode' => 2, 'application_headers'=> new AMQPTable(['replyTo' => $request->getReplyTo(), 'requestId' => $request->getRequestId()])]);
        $this->channel->basic_publish($msg, $this->exchangeName);

    }
}