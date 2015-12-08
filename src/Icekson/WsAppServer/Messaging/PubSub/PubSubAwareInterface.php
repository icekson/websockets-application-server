<?php
/**
 * @author a.itsekson
 * @createdAt: 08.12.2015 19:06
 */

namespace Icekson\WsAppServer\Messaging\PubSub;


interface PubSubAwareInterface
{

    /**
     * @return PubSubInterface
     */
    public function getPubSub();

    /**
     * @param PubSubInterface $pubSub
     */
    public function setPubSub(PubSubInterface $pubSub);


}