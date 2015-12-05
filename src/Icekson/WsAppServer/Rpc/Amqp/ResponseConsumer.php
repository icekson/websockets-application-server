<?php
/**
 *
 * @author: a.itsekson
 * @date: 05.12.2015 14:08
 */

namespace Icekson\WsAppServer\Rpc\Amqp;


use Bunny\Message;
use Icekson\WsAppServer\Rpc\Handler\ResponseHandlerInterface;
use Icekson\WsAppServer\Rpc\Response;
use React\EventLoop\LoopInterface;

class ResponseConsumer extends Consumer
{
    /**
     * @var ResponseHandlerInterface|null
     */
    private $handler = null;

    public function __construct(array $config, LoopInterface $loop, ResponseHandlerInterface $listener, $serviceName)
    {
        $exchangeName = "rpc-response";
        parent::__construct($config, $loop, $serviceName, $exchangeName);
        $this->type = "direct";
        $this->queueName = $exchangeName . "." . $serviceName;
        $this->routingKey = $serviceName;
        $this->handler = $listener;
    }

    public function processMsg(Message $msg)
    {

        $json = $msg->content;
        $this->logger->debug("new rpc-response message consumed: {$json}");
        $resp = Response::parseFromJSON($json);
        $requestId = $resp->getRequestId();

        try {
            $this->handler->onResponse($resp);
            $this->channel->ack($msg);
            $this->logger->debug("rpc response message processed for requestId: {$requestId}");
        } catch (\Exception $ex) {
            $this->channel->nack($msg);
            throw $ex;
        }
    }


}