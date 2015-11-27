<?php

/**
 * @author a.itsekson
 * @createdAt: 27.11.2015 16:31
 */


namespace IceksonTest\WsAppServer\Service;


use Icekson\WsAppServer\Application;
use Icekson\WsAppServer\Config\ServiceConfig;
use Icekson\WsAppServer\Service\Support\PubSubConsumer;
use Icekson\WsAppServer\Service\Support\PubSubListener;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ConnectorServiceTest extends \PHPUnit_Framework_TestCase
{
    public function testPubSubConsumer()
    {
        $amqpConf = [
            "host" => "195.184.70.228",
            "port" => 5672,
            "user" => "cricket_dev",
            "password" => "9iweh44lCM0yWWt2m1h0",
            "vhost" => "/cricket_dev"
        ];
        $em = new EventDispatcher();
        $conf = new ServiceConfig(['amqp' =>$amqpConf]);
        $callback = $this->getMockBuilder('\Icekson\WsAppServer\Messaging\Websocket\Handler')
            ->setConstructorArgs(["test-service", $conf])
            ->getMock();
        $callback->expects($this->atLeast($this->once()))->method("onPubsubEventPublished");
        $em->addListener('pubsub.messagePublished', [$callback, 'onPubsubEventPublished']);
        $pubsubConsumer = new PubSubConsumer(new PubSubListener($em), $conf);
        $pubsubConsumer->start();
    }

}