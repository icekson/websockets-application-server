<?php
/**
 *
 * @author: a.itsekson
 * @date: 05.12.2015 14:04
 */

namespace Icekson\WsAppServer\Rpc\Handler;


use Icekson\WsAppServer\Rpc\ResponseInterface;

interface ResponseHandlerInterface extends HandlerInterface
{
    public function onResponse(ResponseInterface $resp);

    public function sendRequest($requestId, $url, array $params);
}