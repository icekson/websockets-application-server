<?php
/**
 * @author a.itsekson
 * @createdAt: 06.11.2015 15:21
 */

namespace Icekson\WsAppServer\Jobs\Amqp;


use Icekson\Utils\Logger;
use Icekson\Utils\ParamsBag;

use Icekson\WsAppServer\Jobs\JobInterface;
use Icekson\WsAppServer\Jobs\QueueInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

class Queue implements QueueInterface
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

    private $channelName = "";

    /**
     * @param array $config
     * @param string $exchangeName
     * @param string $queue
     * @throws \Exception
     */
    public function __construct(array $config, $exchangeName = null, $queue = null)
    {
        $this->logger = Logger::createLogger(get_class($this), $config);
        if($exchangeName === null){
            $exchangeName = 'jobs';
        }
        if($queue === null){
            $queue = 'match-jobs';
        }
        $this->queueName = $queue;
        $this->exchangeName = $exchangeName;

        $host = $config['amqp']['host'];
        $port = $config['amqp']['port'];
        $user = $config['amqp']['user'];
        $password = $config['amqp']['password'];
        $vhost = $config['amqp']['vhost'];
        $this->connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);
        $this->channelName = uniqid();
       // $this->initConnection();

    }

    private function initConnection()
    {
        if(!$this->connection->isConnected()){
            $this->connection->reconnect();
        }

        $this->channel = $this->connection->channel();
        $this->channel->exchange_declare($this->exchangeName, 'direct', false, true, false);
        $this->channel->queue_declare($this->queueName, false, true, false, false);
    }

    public function __destruct()
    {
        try {
            if($this->connection) {
                $this->connection->close();
            }
            if($this->channel){
                $this->channel->close();
            }
        } catch (\Exception $e) {

        }
    }

    /**
     * @param $jobClassName
     * @param array $params
     * @param string $routingKey
     */
    public function enqueue($jobClassName, $params = [], $routingKey = JobInterface::JOB_MATCH)
    {
        if($routingKey === null){
            $routingKey = 'task.' . str_replace("\\", '.', $jobClassName);
        }
        $this->initConnection();
        $this->logger->info('enqueue task for routing-key: ' . $routingKey . ', task: ' . $jobClassName);
        $params = new ParamsBag($params);
        $body = json_encode((object)['task' => $jobClassName, 'params' => $params->toArray()]);
        $msg = new AMQPMessage($body, ['content_type' => 'application/json', 'delivery_mode' => 2]);
        $this->channel->basic_publish($msg, $this->exchangeName, $routingKey);

    }

}