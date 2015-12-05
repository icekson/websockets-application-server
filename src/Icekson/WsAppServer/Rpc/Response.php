<?php
/**
 *
 * @author: a.itsekson
 * @date: 05.12.2015 13:36
 */

namespace Icekson\WsAppServer\Rpc;


class Response implements ResponseInterface
{

    private $data = null;

    private $requestId = null;

    private $replyTo = "";


    /**
     * @return string
     */
    public function serialize()
    {
        $res = [];
        $res['requestId'] = $this->requestId;
        $res['replyTo'] = $this->replyTo;
        $res['data'] = $this->data;
        return json_encode($res);
    }

    /**
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        $r = new \Icekson\Base\Request($serialized);
        $data = $r->params();
        $this->setReplyTo($data->get('replyTo', ""));
        $this->setRequestId($data->get('requestId'));
        $this->setData($data->get('data'));

    }

    /**
     * @param $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * @return null
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return null|string
     */
    public function getRequestId()
    {
        return $this->requestId;
    }

    /**
     * @return string
     */
    public function getReplyTo()
    {
        return $this->replyTo;
    }

    /**
     * @param $json
     * @return ResponseInterface
     */
    public static function parseFromJSON($json)
    {
        $resp = new self();
        $resp->unserialize($json);
        return $resp;
    }

    /**
     * @param $replyTo
     */
    public function setReplyTo($replyTo)
    {
        $this->replyTo = $replyTo;
    }

    /**
     * @param $id
     */
    public function setRequestId($id)
    {
        $this->requestId = $id;
    }
}