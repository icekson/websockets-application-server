<?php
/**
 * @author a.itsekson
 * @createdAt: 08.12.2015 19:14
 */

namespace Icekson\WsAppServer\Service\Support;


use Api\Service\Response\Builder;
use Icekson\WsAppServer\Rpc\RequestInterface;

interface PostDispatchInterface
{
    /**
     * @param RequestInterface $request
     * @param Builder $response
     * @return mixed
     */
    public function onDispatched(RequestInterface $request, Builder $response);
}