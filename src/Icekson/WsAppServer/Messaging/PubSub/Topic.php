<?php
/**
 * @author a.itsekson
 * @date 03.09.2015
 *
 */

namespace Icekson\WsAppServer\Messaging\PubSub;


class Topic implements TopicInterface
{
    const EVENT_TYPE_ADDRESS = "address_event";
    const EVENT_TYPE_MULTICAST = "multicast_event";

    private $name = "";

    public function __construct($name)
    {
        $this->name = $name;
    }
    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

}