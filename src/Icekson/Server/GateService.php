<?php
/** 
 * @author a.itsekson
 * @created 01.11.2015 18:54 
 */

namespace Icekson\Server;


use Noodlehaus\Config;
use Psr\Log\LoggerInterface;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

class GateService extends ServiceAbstract implements MessageComponentInterface {

    /**
     * @var null|\SplObjectStorage
     */
    private $connections = null;
    public function __construct($name, LoggerInterface $logger)
    {
        parent::__construct($name, $logger);
        $this->connections = new \SplObjectStorage();
    }
    public function start($host, $port, Config $config)
    {
        $this->config = $config;

        $gate = $this->getGateConfig($config);
        try{
            $this->getLogger()->debug("Starting gate instance: " . $this->getName() . ", {$host}:{$port}");
            $loop   = \React\EventLoop\Factory::create();
            $this->getLogger()->info("Gate version : " . $this->getVersion());

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
            $this->getLogger()->error("Starting gate error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }

    /**
     * @param Config $config
     * @return null
     */
    public function getGateConfig(Config $config)
    {
        $gates = $config->get("server.gates", []);
        return $gates[0];
    }


    public function onOpen(ConnectionInterface $conn)
    {
        $this->getLogger()->debug("New connection on gate " . $this->getName() . ", connectionId: {$conn->resource}" );
        $this->connections->attach($conn);
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->connections->detach($conn);
        $this->getLogger()->debug("Disconnection on gate " . $this->getName() . ", connectionId: {$conn->resource}" );
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $this->getLogger()->error("Gate[{$this->getName()}]::onError: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $this->getLogger()->debug("Gate[{$this->getName()}]::onMessage: " . $msg);
    }
}