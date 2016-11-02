<?php
/**
 * @author a.itsekson
 * @createdAt: 02.11.2016 14:58
 */

namespace Icekson\WsAppServer\ConnectionPool;


interface LimmitedPoolInterface
{

    public function getTotalSize();

    public function getAvailableSize();

    /**
     * @return boolean
     */
    public function isExceededLimit();

}