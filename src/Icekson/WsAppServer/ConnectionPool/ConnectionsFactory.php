<?php
/**
 *
 * @author: a.itsekson
 * @date: 01.11.2016 0:38
 */

namespace Icekson\WsAppServer\ConnectionPool;


interface ConnectionsFactory
{
    public function createConnection();
}