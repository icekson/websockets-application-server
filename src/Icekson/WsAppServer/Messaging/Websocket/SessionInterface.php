<?php
/**
 * @author a.itsekson
 * @date 04.09.2015
 *
 */

namespace Icekson\WsAppServer\Messaging\Websocket;


use Api\Service\IdentityInterface;
use Icekson\Utils\ParamsBag;
use Icekson\WsAppServer\Messaging\PubSub\PubSubInterface;
use Ratchet\ConnectionInterface;

interface SessionInterface
{
    /**
     * @return \SplObjectStorage
     */
    public function getConnectionsPool();


    /**
     * @return \ArrayObject
     */
    public function getUsers();


    /**
     * @return PubSubInterface
     */
    public function getPubSub();

    /**
     * @return IdentityInterface
     */
    public function getIdentity(ParamsBag $params);
}