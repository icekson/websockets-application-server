<?php
/**
 *
 * @author: a.itsekson
 * @date: 31.10.2016 23:36
 */

namespace Icekson\WsAppServer\ConnectionPool\Selector;


use Icekson\WsAppServer\ConnectionPool\ConnectionWrapperInterface;

interface SelectorInterface
{
    /**
     * Perform logic to select a single ConnectionInterface instance from the array provided
     *
     * @param  ConnectionWrapperInterface[] $connections an array of ConnectionInterface instances to choose from
     *
     * @return ConnectionWrapperInterface
     */
    public function select($connections);
}