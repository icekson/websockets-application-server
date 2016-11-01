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

class StaticConnectionPool extends AbstractConnectionPool
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

    /**
     * {@inheritdoc}
     */
    public function __construct($connections, $connectionPoolParams = [])
    {
        if(isset($connectionPoolParams['limitPerConnection'])){
            $limit = (int)$connectionPoolParams['limitPerConnection'];
        }else{
            $limit = 100;
        }
        $selector = new LimitUsageSelector($limit);
        parent::__construct($connections, $selector, $connectionPoolParams);
              
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
                $conn = $this->selector->select($this->connections);
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
    public function scheduleCheck()
    {

    }

}