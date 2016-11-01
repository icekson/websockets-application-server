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

class DynamicConnectionPool extends AbstractConnectionPool
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

        parent::__construct($connections, null, $connectionPoolParams);
        $this->staticPools[] = new StaticConnectionPool($connections, $connectionPoolParams);
              
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
        $total = count($this->staticPools);
        $result = null;
        while($total > 0){
            $pool = null;
            if(count($this->staticPools) > 0){
                /** @var ConnectionPoolInterface $pool */
                $pool = array_shift($this->staticPools);
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
                $connections[] = $this->factory->createConnection();
            }
            $pool = new StaticConnectionPool($connections, $this->connectionPoolParams);
            $this->staticPools[] = $pool;
            $result = $pool->nextConnection();
        }
        return $result;
    }

    public function scheduleCheck()
    {

    }

}