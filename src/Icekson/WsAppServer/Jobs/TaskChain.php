<?php
/**
 * @author a.itsekson
 * @createdAt: 20.11.2015 10:56
 */

namespace Icekson\WsAppServer\Jobs;


use Icekson\Utils\IArrayExchange;
use Icekson\WsAppServer\Jobs\Amqp\ChainQueue;
use Traversable;

class TaskChain implements QueueInterface, DelayedQueueInterface, PushableInterface, \IteratorAggregate, IArrayExchange
{

    /**
     * @var ChainQueue|null
     */
    private $queue = null;
    /**
     * @var \ArrayObject|null
     */
    private $chain = null;

    /**
     * TaskChain constructor.
     * @param AMQPChainQueue $queue
     */
    public function __construct(AMQPChainQueue $queue)
    {
        $this->queue = $queue;
        $this->chain = new \ArrayObject();
    }


    /**
     * @param $in
     * @param $jobClassName
     * @param array $params
     * @param string $routingKey
     * @return $this
     */
    public function enqueueIn($in, $jobClassName, $params = [], $routingKey = JobInterface::JOB_MATCH)
    {
        if(empty($routingKey)){
            $routingKey = JobInterface::JOB_MATCH;
        }
        $this->chain->append(["task" => $jobClassName, "params" => $params, "routingKey" => $routingKey, "isDelayed" => true, "delay" => $in]);
        return $this;
    }

    /**
     * @param $jobClassName
     * @param array $params
     * @param string $routingKey
     * @return $this
     */
    public function enqueue($jobClassName, $params = [], $routingKey = JobInterface::JOB_MATCH)
    {
        if(empty($routingKey)){
            $routingKey = JobInterface::JOB_MATCH;
        }
        $this->chain->append(["task" => $jobClassName, "params" => $params, "routingKey" => $routingKey, "isDelayed" => false, "delay" => 0]);
        return $this;
    }


    public function push()
    {
        $this->queue->push($this);
    }


    /**
     * Retrieve an external iterator
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return Traversable An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     * @since 5.0.0
     */
    public function getIterator()
    {
        return $this->chain->getIterator();
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->chain->getArrayCopy();
    }

    public function fromArray(array $data)
    {
        $this->chain->exchangeArray($data);
        return $this;
    }


    /**
     * @return mixed|null
     */
    public function popTask()
    {
        if($this->chain->count() > 0) {
            $c = $this->chain->getArrayCopy();
            $r = array_shift($c);
            $this->chain->exchangeArray($c);
            return $r;
        }
        return null;
    }

}