<?php
/**
 *
 * @author: a.itsekson
 * @date: 05.12.2015 14:04
 */

namespace Icekson\WsAppServer\Rpc\Handler;


use Icekson\WsAppServer\Rpc\RequestInterface;

interface RequestHandlerInterface extends HandlerInterface
{
    public function onRequest(RequestInterface $request);
}