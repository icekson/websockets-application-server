<?php
/**
 *
 * @author: a.itsekson
 * @date: 31.10.2016 23:42
 */

namespace Icekson\WsAppServer\ConnectionPool\Selector;


use Icekson\WsAppServer\ConnectionPool\ConnectionWrapperInterface;

class RandomSelector implements SelectorInterface
{
    /**
     * Select a random connection from the provided array
     *
     * @param  ConnectionWrapperInterface[] $connections an array of ConnectionWrapperInterface instances to choose from
     *
     * @return ConnectionWrapperInterface
     */
    public function select($connections)
    {
        return $connections[array_rand($connections)];
    }
}