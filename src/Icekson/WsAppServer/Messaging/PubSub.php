<?php
/**
 * @author a.itsekson
 * @date 03.09.2015
 *
 */

namespace Icekson\WsAppServer\Messaging;


use Icekson\WsAppServer\Messaging\PubSub\Exception\InvalidPublisherException;
use Icekson\WsAppServer\Messaging\PubSub\Exception\InvalidTopicException;
use Icekson\WsAppServer\Messaging\PubSub\CallbackInterface;
use Icekson\WsAppServer\Messaging\PubSub\Exception\RegistrationAccessException;
use Icekson\WsAppServer\Messaging\PubSub\Exception\TopicAlreadyRegisteredException;
use Icekson\WsAppServer\Messaging\PubSub\PubSubInterface;
use Icekson\WsAppServer\Messaging\PubSub\Topic;
use Icekson\WsAppServer\Messaging\PubSub\Utils\ParamsBag;

class PubSub implements PubSubInterface
{
    /**
     * @var null|\ArrayObject
     */
    private $subscriptions = null;

    private $ids = [];

    /**
     * @var null|\ArrayObject
     */
    private $events = null;

    private $privateKey = null;

    public function __construct($privateKey)
    {
        $this->subscriptions = new \ArrayObject();
        $this->events = new \ArrayObject();
        $this->privateKey = $privateKey;
    }

    public function subscribe($topic, $subscriptionId, CallbackInterface $callback)
    {
        if (!isset($this->events[$topic])) {
            throw new InvalidTopicException("Trying to subscribe to not existing topic");
        }

        if (!isset($this->subscriptions[$topic])) {
            $this->subscriptions[$topic] = new \SplObjectStorage();
        }
        $uid = $subscriptionId;
        $this->subscriptions[$topic]->attach($callback);
        $this->ids[spl_object_hash($callback)] = $uid;
//        var_dump(md5(serialize($callback)));exit;
        $callback->setSubscriptionId($uid);
        return $uid;
    }

    /**
     * @param $topic
     * @param $subscriptionId
     * @return mixed|void
     * @throws InvalidTopicException
     */
    public function unsubscribe($topic, $subscriptionId)
    {
        if (!isset($this->subscriptions[$topic])) {
            throw new InvalidTopicException("Topic is not exist");
        }
        $callback = null;
        /** @var CallbackInterface $c */
        foreach($this->subscriptions[$topic] as $c){
            if($c->getSubscriptionId() == $subscriptionId){
                $callback = $c;
                break;
            }
        }

        if($callback !== null) {
            $this->subscriptions[$topic]->detach($callback);
            unset($this->ids[spl_object_hash($callback)]);
        }
    }

    /**
     * @param $topic
     * @return mixed|void
     * @throws InvalidTopicException
     *
     */
    public function unsubscribeAll($topic)
    {
        if (!isset($this->subscriptions[$topic])) {
            throw new InvalidTopicException("Topic is not exist");
        }
        foreach($this->subscriptions[$topic] as $callback) {
            $this->subscriptions[$topic]->detach($callback);
            unset($this->ids[spl_object_hash($callback)]);
        }
    }


    /**
     * @param $topic
     * @param $publisherId
     * @param ParamsBag $context
     * @throws InvalidPublisherException
     * @throws InvalidTopicException
     */
    public function publish($topic, $publisherId, ParamsBag $context)
    {
        if (!isset($this->events[$topic])) {
            throw new InvalidTopicException("Try to publish of not existing topic");
        }

        if(!isset($this->subscriptions[$topic]) || empty($this->subscriptions[$topic])){
            return;
        }

        if($this->events[$topic] != $publisherId && $publisherId !== $this->privateKey){
            throw new InvalidPublisherException("Invalid publisher ID is given");
        }


        $t = new Topic($topic);
        /** @var CallbackInterface $subscriber */
        foreach ($this->subscriptions[$topic] as $subscriber) {
            $uid = isset($this->ids[spl_object_hash($subscriber)]) ? $this->ids[spl_object_hash($subscriber)] : null;
            $context->put('subscriptionId', $uid);
            $subscriber->notify($t, $context);
        }

    }

    /**
     * @return array
     */
    public function getSubscriptions()
    {
        $topics = [];
        foreach ($this->subscriptions as $topicName => $subscription) {
            $topics[] = new Topic($topicName);
        }
        return $topics;

    }

    /**
     * @return array
     */
    public function getRegisteredEvents()
    {
        $res = [];
        foreach($this->events as $name => $pubId){
            $res[] = $name;
        }
        return $res;
    }

    /**
     * @param $topic
     * @return array
     */
    public function getSubscribers($topic)
    {
        if(!isset($this->subscriptions[$topic])){
            return [];
        }
        $res = [];

        foreach($this->subscriptions[$topic] as $subscriber){
            $res[] = $subscriber;
        }
        return $res;
    }

    /**
     * @param $topic
     * @param $privateKey
     * @return mixed
     * @throws TopicAlreadyRegisteredException
     */
    public function register($topic, $privateKey)
    {
        if($privateKey !== $this->privateKey){
            throw new RegistrationAccessException("Invalid access key is given");
        }
        if(isset($this->events[$topic])){
            throw new TopicAlreadyRegisteredException("Topic that you try to register already is registered");
        }
        $id = $this->generateUid();
        $this->events[$topic] = $id;
        return $id;
    }

    /**
     * @param $topic
     * @return bool
     */
    public function isEventRegistered($topic){
        return isset($this->events[$topic]);
    }

    /**
     * @param $topic
     * @param $publisherId
     * @return mixed
     */
    public function unregister($topic, $publisherId)
    {
        if(!isset($this->events[$topic])){
            throw new InvalidTopicException("Topic isn't exist");
        }

        if($this->events[$topic] != $publisherId){
            throw new InvalidPublisherException("Invalid publisher ID is given");
        }

        unset($this->events[$topic]);
        $this->unsubscribeAll($topic);
    }


    private function generateUid()
    {
        return str_replace(".", "",uniqid("",mt_rand(0,9999999)));
    }

}