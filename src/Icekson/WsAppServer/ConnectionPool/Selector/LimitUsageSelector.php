<?php
/**
 *
 * @author: a.itsekson
 * @date: 31.10.2016 23:40
 */

namespace Icekson\WsAppServer\ConnectionPool\Selector;


use Icekson\WsAppServer\ConnectionPool\ConnectionWrapperInterface;
use Icekson\WsAppServer\ConnectionPool\Exception\ConnectionsLimitExceededException;

class LimitUsageSelector implements SelectorInterface
{
    /**
     * @var int
     */
    private $current = 0;
    private $users = [];
    /**
     * @var SelectorInterface|null
     */
    private $roundSelector = null;
    private $limit = 100;

    public function __construct($limit)
    {
        $this->limit = $limit;
        $this->roundSelector = new RoundRobinSelector();
    }

    /**
     * @param \Icekson\WsAppServer\ConnectionPool\ConnectionWrapperInterface[] $connections
     * @throws ConnectionsLimitExceededException
     * @return ConnectionWrapperInterface
     */
    public function select($connections)
    {
        $result = null;
        $countConnections = count($connections);
        while($countConnections > 0){
            $connection = $this->roundSelector->select($connections);
            $count = 0;
            $hash = $this->hash($connection);
            if(isset($this->users[$hash])){
                $count = $this->users[$hash];
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
    }

    private function hash(ConnectionWrapperInterface $connection)
    {
        return spl_object_hash($connection);
    }
}