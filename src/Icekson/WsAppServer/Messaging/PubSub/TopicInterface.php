<?php
/**
 * @author a.itsekson
 * @date 03.09.2015
 *
 */

namespace Icekson\WsAppServer\Messaging\PubSub;


use Icekson\WsAppServer\Messaging\PubSub\Utils\ParamsBag;

interface TopicInterface
{
    public function getName();
    public function setName($name);
}