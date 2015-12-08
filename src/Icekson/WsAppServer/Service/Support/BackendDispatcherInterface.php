<?php
/**
 * @author a.itsekson
 * @createdAt: 08.12.2015 19:16
 */

namespace Icekson\WsAppServer\Service\Support;


use Api\Service\Response\Builder;
use Icekson\WsAppServer\Rpc\RequestInterface;

interface BackendDispatcherInterface
{

    public function dispatch(RequestInterface $request);

    /**
     * @param PostDispatchInterface $post
     * @return mixed
     */
    public function registerPostDispatcher(PostDispatchInterface $post);

}