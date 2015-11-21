<?php
/**
 * @author a.itsekson
 * @date 03.09.2015
 *
 */

namespace Icekson\WsAppServer\Messaging\PubSub;


use Icekson\WsAppServer\Messaging\PubSub\Utils\ParamsBag;

interface CallbackInterface
{

    /**
     * @param TopicInterface $topic
     * @param ParamsBag $params
     * @return mixed
     */
    public function notify(TopicInterface $topic, ParamsBag $params);

    public function setSubscriptionId($id);

    public function getSubscriptionId();

}