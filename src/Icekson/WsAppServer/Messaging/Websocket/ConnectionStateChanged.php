<?php
/**
 * @author a.itsekson
 * @createdAt: 01.06.2016 18:11
 */

namespace Icekson\WsAppServer\Messaging\Websocket;


interface ConnectionStateChanged
{
    public function onConnected($data);

    public function onDisconnected($data);
}