<?php
/**
 * @author a.itsekson
 * @createdAt: 27.11.2015 15:54
 */

namespace Icekson\WsAppServer\Service\Support;


use Symfony\Component\EventDispatcher\EventDispatcher;

class PubSubListener extends \Threaded implements PubSubListenerInterface
{
    protected $em = null;
    public function __construct(EventDispatcher $eventDispatcher)
    {
        $this->em = $eventDispatcher;
    }

    public function onPubSubMessage($msg)
    {
        $this->synchronized(function() use ($msg){
            $this->em->dispatch("pubsub.messagePublished", $msg);
        }, $this);
    }
}