<?php
/**
 *
 * @author: a.itsekson
 * @date: 31.10.2016 23:26
 */

namespace Icekson\WsAppServer\ConnectionPool;


interface ConnectionPoolInterface
{
    /**
     * @param bool $force
     *
     * @return ConnectionWrapperInterface
     */
    public function nextConnection($force = false);
    /**
     * @return void
     */
    public function scheduleCheck();

    /** ConnectionWrapperInterface[] */
    public function getConnections();

    public function dispose();

    public function releaseAll();

    public function releaseConnection(ConnectionWrapperInterface $connection);

    public function isEmpty();
}