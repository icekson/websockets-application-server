<?php
/** 
 * @author a.itsekson
 * @created 01.11.2015 19:16 
 */

namespace Icekson\Server;


use Noodlehaus\Config;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class BackendService extends ServiceAbstract{

    public function start(Config $config)
    {
        // TODO: Implement start() method.
    }

    public function publishRpcResponse($requestId, $msg)
    {
//        $config = $this->config;
//        $host = $config->get("amq.host", "localhost");
//        $port = $config->get("amq.port", 5672);
//        $user = $config->get("amq.user", "guest");
//        $password = $config->get("amq.password", "guest");
//
//        $exchangeName = $config->get("amq.exchange", "rpc.direct");
//        $queueName = $config->get("amq.queue", "rpc.queue");
//
//        $this->amqpConnection = new AMQPStreamConnection($host, $port, $user, $password);
//        $this->amqpChannel = $this->amqpConnection->channel();
//        $this->amqpChannel->exchange_declare($exchangeName, 'direct', false, true, false);
//        $this->amqpChannel->queue_declare($queueName, false, true, false, false);
//        $this->amqpChannel->queue_bind($queueName, $exchangeName);
//        $msg = new AMQPMessage($msg, array('content_type' => 'application/json', 'delivery_mode' => 2));
//        $this->amqpChannel->basic_publish($msg, $exchangeName);
//        $this->amqpChannel->close();
//        $this->amqpConnection->close();
    }

    public function publishMessage($msg)
    {

    }
}