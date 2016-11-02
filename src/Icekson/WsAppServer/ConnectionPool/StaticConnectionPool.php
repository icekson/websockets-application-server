<?php
/**
 *
 * @author: a.itsekson
 * @date: 31.10.2016 23:51
 */

namespace Icekson\WsAppServer\ConnectionPool;


use Icekson\WsAppServer\ConnectionPool\Exception\ConnectionsLimitExceededException;
use Icekson\WsAppServer\ConnectionPool\Exception\NoConnectionAvailableException;
use Icekson\WsAppServer\ConnectionPool\Selector\LimitUsageSelector;
use Icekson\WsAppServer\ConnectionPool\Selector\RoundRobinSelector;
use Icekson\WsAppServer\ConnectionPool\Selector\SelectorInterface;

class StaticConnectionPool extends AbstractConnectionPool implements LimmitedPoolInterface
{
    /**
     * @var int
     */
    private $pingTimeout    = 60;
    /**
     * @var int
     */
    private $maxPingTimeout = 3600;
    
    private $timeout = 30;

    private $limit = 10;

    private $users = [];

    /**
     * {@inheritdoc}
     */
    public function __construct($connections, $connectionPoolParams = [])
    {
        if(isset($connectionPoolParams['limitPerConnection'])){
            $limit = (int)$connectionPoolParams['limitPerConnection'];
        }else{
            $limit = 10;
        }
        $this->limit = $limit;
        $selector = new RoundRobinSelector();
        parent::__construct($connections, $selector, $connectionPoolParams);
        $this->size = count($connections);
              
        $this->scheduleCheck();
    }
    /**
     * @param bool $force
     *
     * @return ConnectionWrapperInterface
     * @throws NoConnectionAvailableException
     */
    public function nextConnection($force = false)
    {
        $total = count($this->connections);
        $result = null;
        while($total > 0){
            try {
                $conn = $this->select($this->connections);
                if($conn->isAlive()){
                    $result = $conn;
                    break;
                }
            }catch(ConnectionsLimitExceededException $ex){
            }
            $total--;
        }
        if($result === null) {
            throw new NoConnectionAvailableException("No alive connections found in your cluster");
        }
        return $result;
    }

    /**
     * @param \Icekson\WsAppServer\ConnectionPool\ConnectionWrapperInterface[] $connections
     * @throws ConnectionsLimitExceededException
     * @return ConnectionWrapperInterface
     */
    private function select($connections)
    {
        $result = null;
        $countConnections = count($connections);
        while($countConnections > 0){
            $connection = $this->selector->select($connections);
            $count = 0;
            $hash = $this->hash($connection);
            if(isset($this->users[$hash])){
                $count = $this->users[$hash];
            }else{
                $this->users[$hash] = 0;
            }
            if($count < $this->limit){
                $this->users[$hash] = $this->users[$hash] += 1;
                $result = $connection;
                break;
            }
            $countConnections--;
        }
        if($result === null){
            throw new ConnectionsLimitExceededException("Amount usages {$this->limit} per a connection limit exceeded");
        }
        return $result;
    }
    public function scheduleCheck()
    {

    }

    public function releaseAll()
    {
        $this->users = [];
        $this->dispose();
    }

    public function releaseConnection(ConnectionWrapperInterface $connection)
    {
        $hash = $this->hash($connection);
        $count = 0;
        if(isset($this->users[$hash])) {
            $count = $this->users[$hash];
            $count--;
        }
        if($count < 0){
            $count = 0;
        }
        $this->users[$hash] = $count;
    }

    private function hash(ConnectionWrapperInterface $connection)
    {
        return spl_object_hash($connection);
    }

    public function isEmpty()
    {
        $count = 0;
        foreach ($this->users as $hash => $c){
            if($c > 0){
                $count += $c;
            }
        }
        return $count == 0;
    }

    public function getTotalSize()
    {
        return $this->size*$this->limit;
    }

    public function getAvailableSize()
    {
        $count = 0;
        foreach ($this->users as $c) {
            $count = $count + $c;
        }
        return $this->getTotalSize() - $count;
    }

    /**
     * @return bool
     */
    public function isExceededLimit()
    {
        $count = 0;
        foreach ($this->users as $c) {
            if($c == $this->limit){
                $count++;
            }
        }
        return $count === $this->size;
    }


}