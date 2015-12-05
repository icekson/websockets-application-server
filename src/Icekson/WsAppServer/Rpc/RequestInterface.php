<?php
/**
 *
 * @author: a.itsekson
 * @date: 05.12.2015 13:34
 */

namespace Icekson\WsAppServer\Rpc;


use Icekson\Utils\ParamsBag;

interface RequestInterface extends \Serializable
{

    public function setUrl($url);

    public function setRequestId($id);

    public function getRpcServiceName();

    public function getRpcServiceActionName();

    public function setParams(ParamsBag $params);

    public function getParams();

    public function setReplyTo($replyTo);

    public function getReplyTo();

    public function getRequestId();

    public static function parseFromJSON($json);

}