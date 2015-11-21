<?php
/**
 * @author a.itsekson
 * @date 03.09.2015
 *
 */

namespace Icekson\WsAppServer\Messaging\PubSub;


use Icekson\WsAppServer\Messaging\PubSub\Utils\ParamsBag;
use SplSubject;
use SuperClosure\SerializableClosure;

class Subscriber implements CallbackInterface
{
    private $callable = null;

    private $subscriptionId = null;

    /**
     * @param callable $func
     */
    public function __construct(callable $func)
    {
        $this->callable = $func;
    }

    public function notify(TopicInterface $topic, ParamsBag $params)
    {
        call_user_func($this->callable, $topic, $params);
    }

    public function setSubscriptionId($id)
    {
        $this->subscriptionId = $id;
    }

    public function getSubscriptionId()
    {
        return $this->subscriptionId;
    }
}