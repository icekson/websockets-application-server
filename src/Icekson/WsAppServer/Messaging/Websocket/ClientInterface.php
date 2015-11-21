<?php
/**
 * @author a.itsekson
 * @date 08.09.2015
 *
 */

namespace Icekson\WsAppServer\Messaging\Websocket;


use Api\Service\IdentityInterface;
use Api\Service\Response\Builder;
use Icekson\WsAppServer\Messaging\PubSub\PubSubInterface;
use Icekson\WsAppServer\Messaging\PubSub\Utils\ParamsBag;
use Ratchet\ConnectionInterface;


interface ClientInterface
{

    public function register(ClientPoolInterface $pool);
    /**
     * @param SessionInterface $connection
     * @return mixed
     */
    public function init(SessionInterface $connection, IdentityInterface $identity = null);

    /**
     * @param ParamsBag $params
     * @param Builder $response
     * @return mixed
     */
    public function onRequestProcessed(ConnectionInterface $connection, ParamsBag $params, Builder &$response);

    /**
     * @param ConnectionInterface $connection
     * @param ParamsBag $params
     * @param Builder $response
     * @return mixed
     */
    public function onPublish(ConnectionInterface $connection, ParamsBag $params, Builder &$response);

    /**
     * @param ConnectionInterface $connection
     * @param ParamsBag $params
     * @param Builder $response
     * @return mixed
     */
    public function onSubscribe(ConnectionInterface $connection, ParamsBag $params, Builder &$response);

}