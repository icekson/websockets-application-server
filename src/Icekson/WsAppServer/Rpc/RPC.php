<?php
/**
 *
 * @author: a.itsekson
 * @date: 05.12.2015 13:59
 */

namespace Icekson\WsAppServer\Rpc;


use Icekson\Utils\ParamsBag;
use Icekson\WsAppServer\Rpc\Amqp\Consumer;
use Icekson\WsAppServer\Rpc\Amqp\RequestConsumer;
use Icekson\WsAppServer\Rpc\Handler\HandlerInterface;

use Icekson\WsAppServer\Rpc\Amqp\RequestQueue;
use Icekson\WsAppServer\Rpc\Amqp\ResponseQueue;
use Icekson\WsAppServer\Rpc\Amqp\ResponseConsumer;
use React\EventLoop\LoopInterface;

class RPC
{

    const TYPE_REQUEST = "request";
    const TYPE_RESPONSE = "response";
    /**
     * @var null|RequestQueue
     */
    private $queue = null;

    /**
     * @var null|Consumer
     */
    private $consumer = null;

    /**
     * @var null|string
     */
    private $serviceName = null;

    public function __construct($type, LoopInterface $loop, HandlerInterface $respHandler, $serviceName, array $config)
    {
        $this->serviceName = $serviceName;
        if($type === self::TYPE_REQUEST){
            $exchangeName = "rpc-request";
            $queueName = "rpc-request";
            $this->queue = new RequestQueue($config, $exchangeName, $queueName);
            $this->consumer = new ResponseConsumer($config, $loop, $respHandler, $serviceName);
        }else{
            $exchangeName = "rpc-response";
            $queueName = "rpc-response.{$serviceName}";
            $this->queue = new ResponseQueue($config, $exchangeName, $queueName);
            $this->consumer = new RequestConsumer($config, $loop, $respHandler, $serviceName);
        }


        $this->consumer->consume();
    }

    /**
     * @param $requestId
     * @param $url
     * @param array $params
     */
    public function sendRequest($requestId, $url, array $params)
    {
        $builder = new RequestBuilder();
        $request = $builder->setParams(new ParamsBag($params))
            ->setUrl($url)
            ->setRequestId($requestId)
            ->setReplyTo($this->serviceName)
            ->getRequest();
        $this->queue->enqueue($request);
    }

    /**
     * @param $requestId
     * @param $url
     * @param array $params
     */
    public function sendResponse($requestId, $replyTo, $data)
    {
        $builder = new RequestBuilder();
        $resp = new Response();
        $resp->setData($data);
        $resp->setRequestId($requestId);
        $resp->setReplyTo($replyTo);
        $this->queue->enqueue($resp);
    }

}