<?php
/**
 * Created by PhpStorm.
 * User: Alexey
 * Date: 21.11.2015
 * Time: 21:36
 */

namespace Icekson\WsAppServer\Service\Support;


interface PubSubListenerInterface
{
    /**
     * @return mixed
     */
    public function onPubSubMessage($msg);
}