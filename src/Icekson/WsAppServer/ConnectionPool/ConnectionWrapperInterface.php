<?php
/**
 *
 * @author: a.itsekson
 * @date: 31.10.2016 23:26
 */

namespace Icekson\WsAppServer\ConnectionPool;


interface ConnectionWrapperInterface
{
    public function __construct($connection);

    public function getConnection();

    /**
     * @return bool
     */
    public function isAlive();

    public function dispose();

    public function ping();
    
    
}