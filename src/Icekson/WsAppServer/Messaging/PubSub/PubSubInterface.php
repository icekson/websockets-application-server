<?php
/**
 * @author a.itsekson
 * @date 03.09.2015
 *
 */

namespace Icekson\WsAppServer\Messaging\PubSub;


use Icekson\Utils\ParamsBag;

interface PubSubInterface
{
    /**
     * @param $topic
     * @param $subscriptionId
     * @param CallbackInterface $callback
     * @return mixed
     */
    public function subscribe($topic, $subscriptionId, CallbackInterface $callback);

    /**
     * @param $topic
     * @param $subscriptionId
     * @return mixed
     */
    public function unsubscribe($topic, $subscriptionId);

    /**
     * @param $topic
     * @return mixed
     */
    public function unsubscribeAll($topic);

    /**
     * @param $topic
     * @param $publisherId
     * @param ParamsBag $context
     * @return mixed
     */
    public function publish($topic, $publisherId, ParamsBag $context);

    /**
     * @param $topic
     * @param $privateKey
     * @return mixed
     */
    public function register($topic, $privateKey);

    /**
     * @param $topic
     * @param $publisherId
     * @return mixed
     */
    public function unregister($topic, $publisherId);

    /**
     * @return bool
     */
    public function isEventRegistered($topic);
}