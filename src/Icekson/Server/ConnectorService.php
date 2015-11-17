<?php
/** 
 * @author a.itsekson
 * @created 01.11.2015 19:16 
 */

namespace Icekson\Server;


use Noodlehaus\Config;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Symfony\Component\Process\Process;

class ConnectorService extends ServiceAbstract implements MessageComponentInterface{


    /**
     * @var null|\SplObjectStorage
     */
    private $connections = null;
    private $consumerCmd = "php scripts/app-runner.php app-server:connector-consumer:start --type=%s --connector-name=%s --callback-host=%s --callback-port=%s";

    public function __construct($name, LoggerInterface $logger)
    {
        parent::__construct($name, $logger);
        $this->connections = new \SplObjectStorage();
    }

    public function start($host, $port, Config $config)
    {
        $this->config = $config;
       // $this->runConsumer(self::TYPE_CONSUMER_PUBSUB);
      //  $this->runConsumer(self::TYPE_CONSUMER_RPC);

        $connectorInfo = $this->getConnectorConfig($config);
        try{
            $this->getLogger()->debug("Starting connector instance: " . $this->getName() . ", {$host}:{$port}");

            $loop   = \React\EventLoop\Factory::create();

            // Listen for the web server to make a ZeroMQ push after an ajax request
//            $context = new \React\ZMQ\Context($loop);
//            $pull = $context->getSocket(\ZMQ::SOCKET_PULL);
//            $pull->bind('tcp://'.$connectorInfo['callback_host'].':' . $connectorInfo['callback_port']); // Binding to 127.0.0.1 means the only client that can connect is itself
//            $pull->on('message', array($this, 'onQueueCallback'));


           // $handler->setIdentityFinder(new UserIdentityFinder());
            $this->getLogger()->info("version : " . $this->getVersion());

            $webSock = new \React\Socket\Server($loop);
            $webSock->listen($port, $host); // Binding to 0.0.0.0 means remotes can connect
            $webServer = new \Ratchet\Server\IoServer(
                new \Ratchet\Http\HttpServer(
                    new \Ratchet\WebSocket\WsServer(
                        $this
                    )
                ),
                $webSock
            );
            $loop->run();

        }catch(\Exception $e){
            $this->getLogger()->error("Starting connector error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }

    /**
     * @param Config $config
     * @return null
     */
    public function getConnectorConfig(Config $config)
    {
        $connectors = $config->get("server.connectors", []);
        $connectorData = null;
        foreach ($connectors as $connectorInfo) {
            if($connectorInfo['name'] !== $this->getName()){
                continue;
            }
            $connectorData = $connectorInfo;
            break;
        }
        return $connectorData;
    }

    public function onQueueCallback($response)
    {
        $this->getLogger()->debug("callback msg:" . $response);
    }


    public function getVersion()
    {
        return "1.0.0";
    }

    public function runConsumer($type)
    {
        $config = $this->config;
        $connectorData = $this->getConnectorConfig($this->config);
        if($connectorData === null){
            throw new \InvalidArgumentException("Invalid config, there is no info about connector: {$this->getName()}");
        }

        $process = new Process(sprintf($this->consumerCmd, $type, $connectorData['name'], $connectorData['callback_host'], $connectorData['callback_port']));
        $process->start();
        sleep(1);
        if ($process->isRunning()) {
            $this->getLogger()->info("Consumer {$type} for connector {$this->getName()} started: pid - " . $process->getPid());
        } else {
            $this->getLogger()->error($process->getErrorOutput());
        }

    }

    function onOpen(ConnectionInterface $conn)
    {
        $this->getLogger()->debug("New connection on connector " . $this->getName() . ", connectionId: {$conn->resource}" );
        $this->connections->attach($conn);
    }

    function onClose(ConnectionInterface $conn)
    {
        $this->connections->detach($conn);
        $this->getLogger()->debug("Disconnection on connector " . $this->getName() . ", connectionId: {$conn->resource}" );
    }

    function onError(ConnectionInterface $conn, \Exception $e)
    {
        $this->getLogger()->error("Connector[{$this->getName()}]::onError: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    }

    function onMessage(ConnectionInterface $from, $msg)
    {
        $this->getLogger()->debug("Connector[{$this->getName()}]::onMessage: " . $msg);
    }
}