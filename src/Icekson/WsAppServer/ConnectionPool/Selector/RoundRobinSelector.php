<?php
/**
 *
 * @author: a.itsekson
 * @date: 31.10.2016 23:40
 */

namespace Icekson\WsAppServer\ConnectionPool\Selector;


use Icekson\WsAppServer\ConnectionPool\ConnectionWrapperInterface;

class RoundRobinSelector implements SelectorInterface
{
    /**
     * @var int
     */
    private $current = 0;
    /**
     * Select the next connection in the sequence
     *
     * @param  ConnectionWrapperInterface[] $connections an array of ConnectionWrapperInterface instances to choose from
     *
     * @return ConnectionWrapperInterface
     */
    public function select($connections)
    {
        $this->current += 1;
        return $connections[$this->current % count($connections)];
    }
}