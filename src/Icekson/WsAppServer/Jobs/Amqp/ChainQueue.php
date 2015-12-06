<?php
/**
 * @author a.itsekson
 * @createdAt: 06.11.2015 15:21
 */

namespace Icekson\WsAppServer\Jobs\Amqp;


use Icekson\Utils\Logger;
use Icekson\WsAppServer\Jobs\DelayedQueueInterface;
use Icekson\WsAppServer\Jobs\JobInterface;
use Icekson\WsAppServer\Jobs\QueueInterface;
use Icekson\WsAppServer\Jobs\TaskChain;
use Psr\Log\LoggerInterface;

class ChainQueue implements QueueInterface, DelayedQueueInterface
{

     /**
     * @var null|LoggerInterface
     */
    private $logger = null;

    private $config = null;

    private $taskChain = null;

    /**
     * AMQPChainQueue constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->logger = Logger::createLogger(get_class($this), $config);
        $this->taskChain = new TaskChain($this);
        $this->config = $config;
    }

    /**
     * @param $jobClassName
     * @param array $params
     * @param string $routingKey
     * @return TaskChain
     */
    public function enqueue($jobClassName, $params = [], $routingKey = JobInterface::JOB_MATCH)
    {
        return $this->taskChain->enqueue($jobClassName, $params, $routingKey);
    }

    /**
     * @param $in
     * @param $jobClassName
     * @param array $params
     * @param string $routingKey
     * @return TaskChain
     */
    public function enqueueIn($in, $jobClassName, $params = [], $routingKey = JobInterface::JOB_MATCH)
    {
        return $this->taskChain->enqueueIn($in, $jobClassName, $params, $routingKey);
    }

    /**
     * @param TaskChain $chain
     */
    public function push(TaskChain $chain)
    {
        $firstTask = $chain->popTask();
        if($firstTask === null){
            $this->logger->debug("chain is empty");
            return;
        }
        $firstTask = (array)$firstTask;
        $this->logger->debug("first task of chain is: ", $firstTask);

        $params = (array)$firstTask['params'];
        $params['chain'] = $chain->toArray();
        $queue = null;

        $this->logger->debug("push task with chain", $firstTask);


        if($firstTask['isDelayed']){
            $queue = new DelayedQueue($this->config);
            $queue->enqueueIn($firstTask['delay'], $firstTask['task'], $params, $firstTask['routingKey']);
        }else{
            $queue = new Queue($this->config);
            $queue->enqueue($firstTask['task'], $params, $firstTask['routingKey']);
        }

    }

}