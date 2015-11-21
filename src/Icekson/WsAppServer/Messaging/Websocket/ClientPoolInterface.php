<?php
/**
 * @author a.itsekson
 * @date 08.09.2015
 *
 */

namespace Icekson\WsAppServer\Messaging\Websocket;


use Api\Service\Response\Builder;
use Icekson\WsAppServer\Messaging\PubSub\Utils\ParamsBag;
use Ratchet\ConnectionInterface;

interface ClientPoolInterface
{

    /**
     * @param ClientInterface $client
     * @return mixed
     */
    public function registerClient(ClientInterface $client);

    /**
     * @param ConnectionInterface $conn
     * @param ParamsBag $params
     * @param Builder $response
     * @return mixed
     */
    public function notify($type, ConnectionInterface $conn, ParamsBag $params, Builder &$response);
}