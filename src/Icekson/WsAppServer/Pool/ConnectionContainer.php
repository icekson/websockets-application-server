<?php
/**
 * @author a.itsekson
 * @createdAt: 31.05.2016 16:05
 */

namespace Icekson\WsAppServer\Pool;


interface ConnectionContainer
{
    public function getConnection();
}