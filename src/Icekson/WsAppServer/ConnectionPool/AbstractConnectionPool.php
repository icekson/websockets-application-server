<?php
/**
 *
 * @author: a.itsekson
 * @date: 31.10.2016 23:35
 */

namespace Icekson\WsAppServer\ConnectionPool;


use Icekson\WsAppServer\ConnectionPool\Selector\SelectorInterface;

abstract class AbstractConnectionPool implements ConnectionPoolInterface
{
    /**
     * Array of connections
     *
     * @var ConnectionWrapperInterface[]
     */
    protected $connections;
    /**
     * Array of initial seed connections
     *
     * @var ConnectionWrapperInterface[]
     */
    protected $seedConnections;
    /**
     * Selector object, used to select a connection on each request
     *
     * @var SelectorInterface
     */
    protected $selector;
    /** @var array */
    protected $connectionPoolParams;

    protected $size = 100;


    public function __construct($connections, SelectorInterface $selector = null, $connectionPoolParams = [])
    {
        $paramList = array('connections', 'selector', 'connectionPoolParams');
        foreach ($paramList as $param) {
            if (isset($$param) === false) {
                throw new \InvalidArgumentException('`' . $param . '` parameter must not be null');
            }
        }

        $this->connections          = $connections;
        $this->seedConnections      = $connections;
        $this->selector             = $selector;
        $this->connectionPoolParams = $connectionPoolParams;

        if(isset($connectionPoolParams['size']) && is_numeric($connectionPoolParams['size'])){
            $this->size = (int)$connectionPoolParams['size'];
        }

    }
    /**
     * @return ConnectionWrapperInterface[]
     */
    public function getConnections()
    {
        return $this->connections;
    }

    public function release()
    {
        foreach ($this->connections as $index => $connection) {
           $this->dispose($connection);
        }
    }

    public function dispose(ConnectionWrapperInterface $connection)
    {
       $connection->dispose();
    }
    /**
     * @param bool $force
     *
     * @return ConnectionWrapperInterface
     */
    abstract public function nextConnection($force = false);
    abstract public function scheduleCheck();


}