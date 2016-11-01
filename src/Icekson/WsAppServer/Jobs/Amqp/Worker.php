<?php
/**
 *
 * @author: a.itsekson
 * @date: 06.12.2015 23:09
 */

namespace Icekson\WsAppServer\Jobs\Amqp;


use Icekson\Utils\Logger;
use Icekson\Config\ConfigAdapter;
use Icekson\Config\ConfigAwareInterface;
use Icekson\Config\ConfigureInterface;
use Icekson\WsAppServer\Jobs\JobInterface;
use Icekson\WsAppServer\Jobs\TaskChain;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

class Worker implements ConfigAwareInterface
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

    /**
     * @var ConfigureInterface
     */
    private $config = null;

    private $channelId = -1;

    /**
     * Worker constructor.
     * @param ConfigureInterface $config
     * @param null $queue
     * @param null $exchangeName
     */
    public function __construct(ConfigureInterface $config, $queue = null, $exchangeName = null)
    {
        $this->logger = Logger::createLogger(get_class($this), $config->toArray());
        $this->config = $config;
        $config = $config->toArray();
        if($exchangeName === null){
            $exchangeName = 'jobs';
        }
        if($queue === null){
            $queue = 'im-jobs';
        }
        $this->queueName = $queue;
        $this->exchangeName = $exchangeName;

        $host = $config['amqp']['host'];
        $port = $config['amqp']['port'];
        $user = $config['amqp']['user'];
        $password = $config['amqp']['password'];
        $vhost = $config['amqp']['vhost'];
        $this->channelId = mt_rand(1, 65535);
        $this->connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);


    }

    /**
     * @param string $routingKey
     */
    private function initConnection($routingKey = "#")
    {
        if(!$this->connection->isConnected()){
            $this->connection->reconnect();
        }
        $this->channel = $this->connection->channel($this->channelId);
        $this->channel->exchange_declare($this->exchangeName, 'direct', false, true, false);
        $this->channel->queue_declare($this->queueName, false, true, false, false);
        $this->channel->queue_bind($this->queueName, $this->exchangeName, $routingKey);
    }

    public function __destruct()
    {
        try {
            $this->channel->close();
            $this->connection->close();
        } catch (\Exception $e) {

        }
    }


    public function consume($routingKey)
    {
        $this->initConnection($routingKey);
        $this->channel->basic_consume($this->queueName, '', false, false, false, false, [$this, 'processMsg']);
        $this->logger->info("Jobs consume for routing-key: {$routingKey} started...");
        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }

    /**
     * @param AMQPMessage $msg
     */
    public function processMsg(AMQPMessage $msg)
    {
        try {
            $consumer = $this;
            $routingKey = $msg->delivery_info['routing_key'];
            $consumer->logger->info("new job processed: " . $msg->body, ['delivery_info' => $msg->delivery_info]);

            $body = json_decode($msg->body);
            $taskClass = $body->task;

            if (class_exists($taskClass)) {
                $this->logger->debug("Task class '$taskClass' is found: ", [(class_exists($taskClass))]);

                $task = new $taskClass();
                if($task instanceof JobInterface) {
                    if($task instanceof ConfigAwareInterface){
                        $task->setConfiguration($this->getConfiguration());
                    }
                    $params = (array)$body->params;
                    call_user_func([$task, 'perform'], $params);
                    $chain = isset($params['chain']) ? $params['chain'] : [];
                    if(count($chain) > 0){
                        $queue = new ChainQueue($this->config->toArray());
                        $chainTask = new TaskChain($queue);
                        $this->logger->debug("Chain of tasks: ", $chain);
                        $chainTask->fromArray($chain);
                        $chainTask->push();

                    }
                    $consumer->channel->basic_ack($msg->delivery_info['delivery_tag']);

                }else{
                    $this->logger->warning("Task is not instance of JobInterface");
                    $consumer->channel->basic_nack($msg->delivery_info['delivery_tag']);
                }
            }
        }catch(\Throwable $ex){
            $this->channel->basic_nack($msg->delivery_info['delivery_tag']);
            $this->logger->error($ex->getMessage() . "\n" . $ex->getTraceAsString());
        }

    }


    /**
     * @return ConfigureInterface|ConfigAdapter
     */
    public function getConfiguration()
    {
        return $this->config;
    }

    /**
     * @param ConfigureInterface $config
     */
    public function setConfiguration(ConfigureInterface $config)
    {
        $this->config = $config;
    }
}