<?php
/**
 *
 * @author: a.itsekson
 * @date: 05.12.2015 13:34
 */

namespace Icekson\WsAppServer\Rpc;


interface ResponseInterface extends \Serializable
{

    public function setData($data);

    public function getData();

    public function getRequestId();

    public function getReplyTo();

    public function setReplyTo($replyTo);

    public function setRequestId($id);

    public static function parseFromJSON($json);

}