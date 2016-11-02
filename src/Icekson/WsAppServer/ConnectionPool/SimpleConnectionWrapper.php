<?php
/**
 *
 * @author: a.itsekson
 * @date: 31.10.2016 23:26
 */

namespace Icekson\WsAppServer\ConnectionPool;


class SimpleConnectionWrapper implements ConnectionWrapperInterface
{
    private $connection;

    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return bool
     */
    public function isAlive()
    {
        $res = true;
        if(method_exists($this->connection, 'isConnected')){
            $res = $this->connection->isConnected();
        }
        if(method_exists($this->connection, 'isClosed')){
            $res = !$this->connection->isClosed();
        }
        return $res;
    }

    public function dispose()
    {
        if(method_exists($this->connection, 'dispose')){
            $this->connection->dispose();
        }
        if(method_exists($this->connection, 'close')){
            $this->connection->close();
        }
    }
    
    
}