<?php
/**
 *
 * @author: a.itsekson
 * @date: 05.12.2015 14:08
 */

namespace Icekson\WsAppServer\Rpc\Amqp;


use Bunny\Message;
use Icekson\WsAppServer\Rpc\Handler\RequestHandlerInterface;
use Icekson\WsAppServer\Rpc\RequestBuilder;
use React\EventLoop\LoopInterface;


class RequestConsumer extends Consumer
{
    /**
     * @var RequestHandlerInterface|null
     */
    private $handler = null;

    public function __construct(array $config, LoopInterface $loop, RequestHandlerInterface $listener, $serviceName)
    {
        $exchangeName = "rpc-request";
        $this->type = "direct";
        parent::__construct($config, $loop, $serviceName, $exchangeName);
        $this->queueName = $exchangeName;
        $this->handler = $listener;
    }

    public function processMsg(Message $msg)
    {
        $json = $msg->content;
        $requestId = $msg->getHeader('requestId', null);

        $builder = new RequestBuilder();
        $request = $builder->createFromJSON($json)
            ->setReplyTo($msg->getHeader("replyTo"))
            ->setRequestId($requestId)
            ->getRequest();

        $this->logger->debug("new rpc-request message consumed: {$requestId}");

        try {
            $this->handler->onRequest($request);
            $this->channel->ack($msg);
            $this->logger->debug("rpc request message processed");
        } catch (\Exception $ex) {
            $this->channel->nack($msg);
            throw $ex;
        }
    }
}