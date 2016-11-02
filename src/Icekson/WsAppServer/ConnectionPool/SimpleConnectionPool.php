<?php
/**
 *
 * @author: a.itsekson
 * @date: 31.10.2016 23:46
 */

namespace Icekson\WsAppServer\ConnectionPool;


use Icekson\WsAppServer\ConnectionPool\Exception\NoConnectionAvailableException;
use Icekson\WsAppServer\ConnectionPool\Selector\SelectorInterface;

class SimpleConnectionPool extends AbstractConnectionPool
{
    /**
     * {@inheritdoc}
     */
    public function __construct($connections, SelectorInterface $selector, $connectionPoolParams = [])
    {
        parent::__construct($connections, $selector, $connectionPoolParams);
    }
    /**
     * @param bool $force
     *
     * @return ConnectionWrapperInterface
     * @throws NoConnectionAvailableException
     */
    public function nextConnection($force = false)
    {
        return $this->selector->select($this->connections);
    }
    public function scheduleCheck()
    {
    }

    public function isEmpty()
    {
        return false;
    }


}