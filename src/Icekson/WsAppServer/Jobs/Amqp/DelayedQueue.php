<?php
/**
 * @author a.itsekson
 * @createdAt: 06.11.2015 15:21
 */

namespace Icekson\WsAppServer\Jobs\Amqp;


use Icekson\Utils\Logger;
use Icekson\Utils\ParamsBag;
use Icekson\WsAppServer\Jobs\DelayedQueueInterface;
use Icekson\WsAppServer\Jobs\JobInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Psr\Log\LoggerInterface;

class DelayedQueue implements DelayedQueueInterface
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
    private $targetQueueName = 'match-jobs';
    private $exchangeName = '';
    private $targetExchangeName = 'jobs';
    private $channelId = -1;

    /**
     * @var null|LoggerInterface
     */
    private $logger = null;

    /**
     * @param array $config
     * @param string $exchangeName
     * @param string $queue
     * @throws \Exception
     */
    public function __construct(array $config, $exchangeName = null, $queue = null)
    {
        $this->logger = Logger::createLogger(get_class($this), $config);
        if ($exchangeName === null) {
            $exchangeName = 'delayed-jobs';
        }
        if ($queue === null) {
            $queue = 'delayed-jobs-';
        }
        $this->queueName = $queue;
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

    private function initConnection($delay)
    {
        if (!$this->connection->isConnected()) {
            $this->connection->reconnect();
        }
        $this->channel = $this->connection->channel($this->channelId);
        $this->channel->exchange_declare($this->targetExchangeName, 'direct', false, true, false);
        $this->channel->queue_declare($this->targetQueueName, false, true, false, false);
        //$this->channel->queue_bind($this->targetQueueName, $this->targetExchangeName, '#');
        $this->channel->exchange_declare($this->exchangeName, 'direct', false, true, false);

        $this->channel->queue_declare($this->queueName . $delay, false, true, false, false, false, new AMQPTable(array(
            "x-message-ttl" => $delay * 1000,
            'x-dead-letter-exchange' => $this->targetExchangeName,
            'x-dead-letter-routing-key' => JobInterface::JOB_MATCH
        )));
        $this->channel->queue_bind($this->queueName . $delay, $this->exchangeName, "delayed-task-" . $delay);


    }

    public function __destruct()
    {
        try {
            if ($this->channel) {
                $this->channel->close();
            }
            if ($this->connection) {
                $this->connection->close();
            }

        } catch (\Exception $e) {

        }
    }

    /**
     * @param $delay
     * @param $jobClassName
     * @param array $params
     * @param string $routingKey
     * @return mixed|void
     */
    public function enqueueIn($delay, $jobClassName, $params = [], $routingKey = JobInterface::JOB_MATCH)
    {
        if (!is_int($delay)) {
            throw new \InvalidArgumentException("Invalid delay is given: $delay");
        }

        $this->initConnection($delay);
        $params = new ParamsBag($params);
        $body = json_encode((object)['task' => $jobClassName, 'params' => $params->toArray()]);
        $msg = new AMQPMessage($body, ['content_type' => 'application/json', 'delivery_mode' => 2]);
        $this->channel->basic_publish($msg, $this->exchangeName, 'delayed-task-' . $delay);


    }

}