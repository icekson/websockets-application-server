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
use Icekson\WsAppServer\ConnectionPool\Selector\SelectorInterface;

class DynamicConnectionPool extends AbstractConnectionPool implements LimmitedPoolInterface
{
    private $step = 10;
    private $staticPools = [];
    /**
     * @var null|ConnectionsFactory
     */
    private $factory = null;

    /**
     * {@inheritdoc}
     */
    public function __construct($connections, ConnectionsFactory $factory, $connectionPoolParams = [])
    {
        $this->step = count($connections);
        $this->factory = $factory;
        $this->staticPools[] = new StaticConnectionPool($connections, $connectionPoolParams);

        parent::__construct($connections, null, $connectionPoolParams);

    }
    /**
     * @param bool $force
     *
     * @return ConnectionWrapperInterface
     * @throws NoConnectionAvailableException
     */
    public function nextConnection($force = false)
    {
        $total = count($this->staticPools);
        $result = null;
        $pools = $this->staticPools;
        while($total > 0){
            $pool = null;
            if(count($this->staticPools) > 0){
                /** @var ConnectionPoolInterface $pool */
                $pool = array_shift($pools);
            }
            if($pool === null){
                throw new NoConnectionAvailableException();
            }
            $connection = null;
            try {
                $connection = $pool->nextConnection();
            }catch (NoConnectionAvailableException $ex){

            }
            if($connection instanceof ConnectionWrapperInterface){
                $result = $connection;
                break;
            }
            $total--;
        }
        if($result === null){
            $connections = [];
            for($i = 0; $i < $this->step; $i++){
                $c = $this->factory->createConnection();
                $connections[] = new SimpleConnectionWrapper($c);
            }
            $pool = new StaticConnectionPool($connections, $this->connectionPoolParams);
            $this->staticPools[] = $pool;
            $result = $pool->nextConnection();
        }
        return $result;
    }

    public function releaseConnection(ConnectionWrapperInterface $connection)
    {
        foreach ($this->staticPools as $staticPool) {
            $staticPool->releaseConnection($connection);
        }
        $this->scheduleCheck();
    }

    public function scheduleCheck()
    {
        $pools = [];
        $count = count($this->staticPools);
        while($count > 1){
            /** @var ConnectionPoolInterface $pool */
            $pool = array_pop($this->staticPools);
            if(!$pool->isEmpty()){
                $pools[] = $pool;
            }else{
                $pool->dispose();
                $pool = null;
            }
            $count--;
        }
        if(count($pools) > 0){
            foreach ($pools as $pool) {
                $this->staticPools[] = $pool;
            }
        }

    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        $isEmpty = false;
        foreach ($this->staticPools as $staticPool) {
            if($staticPool->isEmpty()){
                $isEmpty = true;
                break;
            }
        }
        return $isEmpty;
    }

    public function getTotalSize()
    {
        $total = 0;
        /** @var LimmitedPoolInterface $staticPool */
        foreach ($this->staticPools as $staticPool) {
            $total += $staticPool->getTotalSize();
        }
        return $total;
    }

    public function getAvailableSize()
    {
        $available = 0;
        /** @var LimmitedPoolInterface $staticPool */
        foreach ($this->staticPools as $staticPool) {
            $available += $staticPool->getAvailableSize();
        }
        return $available;
    }

    public function isExceededLimit()
    {
        return false;
    }


}