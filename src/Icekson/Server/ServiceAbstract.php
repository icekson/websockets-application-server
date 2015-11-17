<?php
/** 
 * @author a.itsekson
 * @created 01.11.2015 18:56 
 */

namespace Icekson\Server;


use Noodlehaus\Config;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

abstract class ServiceAbstract implements ServiceInterface{
    const TYPE_CONSUMER_RPC = "rpc";
    const TYPE_CONSUMER_PUBSUB = "pubsub";

    protected $name = "empty-name";

    /**
     * @var null|AMQPStreamConnection
     */
    protected $amqpConnection = null;

    /**
     * @var null|AMQPChannel
     */
    protected $amqpChannel = null;
    /**
     * @var Config
     */
    protected $config = null;

    /**
     * @var null|LoggerInterface
     */
    protected $logger = null;

    public function __construct($name, LoggerInterface $logger)
    {
        $this->name = $name;
        $this->logger = $logger;
    }

    abstract public function start($host, $port, Config $config);


    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    public function getLogger()
    {
        return $this->logger;
    }

    public function getVersion()
    {
        return "1.0.0";
    }
} 