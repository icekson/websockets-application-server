<?php
/**
 * Created by PhpStorm.
 * User: Alexey
 * Date: 21.11.2015
 * Time: 21:34
 */

namespace Icekson\WsAppServer\Service\Support;


use Icekson\WsAppServer\Config\ConfigureInterface;
use Icekson\WsAppServer\Messaging\AMQPPubSubConsumer;
use Icekson\WsAppServer\Service\ServiceInterface;

class PubSubConsumer extends \Thread
{
    /**
     * @var PubSubListenerInterface|null
     */
    private $service = null;

    /**
     * @var ConfigureInterface|null
     */
    private $config = null;

    public function __construct(PubSubListenerInterface $service, ConfigureInterface $config)
    {
        $this->service = $service;
        $this->config = $config;
    }

    public function run()
    {
        $name = "app-service";
        if($this->service instanceof ServiceInterface){
            $name = $this->service->getName();
        }
        $consumer = new AMQPPubSubConsumer($this->config->toArray(), $this->service, $name);
        $consumer->consume();
    }

}