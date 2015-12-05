<?php
/**
 *
 * @author: a.itsekson
 * @date: 05.12.2015 13:35
 */

namespace Icekson\WsAppServer\Rpc;


use Icekson\Utils\ParamsBag;

class Request implements RequestInterface
{

    private $url = "";
    /**
     * @var null|ParamsBag
     */
    private $params = null;
    private $requestId = null;
    private $replyTo = "";

    public function serialize()
    {
        $res = [];
        $res['requestId'] = $this->requestId;
        $res['replyTo'] = $this->replyTo;
        $res['url'] = $this->url;
        $res['params'] = $this->params->toArray();
        return json_encode($res);
    }

    public function unserialize($serialized)
    {
        $r = new \Icekson\Base\Request($serialized);
        $data = $r->params();
        $this->setUrl($data->get('url'));
        $this->setRequestId($data->get('requestId'));
        $this->setReplyTo($data->get('replyTo', ""));
        $this->setParams(new ParamsBag($data->get('params',[])));
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function setRequestId($id)
    {
        $this->requestId = $id;
    }

    public function getRpcServiceName()
    {
        $parts = explode("/", $this->url);
        $res = $this->url;
        if(!empty($parts)){
            $res = $parts[0];
        }
        return $res;
    }

    public function getRpcServiceActionName()
    {
        $parts = explode("/", $this->url);
        $res = "";
        if(!empty($parts) && count($parts) > 1){
            $res = $parts[1];
        }
        return $res;
    }

    public function setParams(ParamsBag $params)
    {
        $this->params = $params;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function setReplyTo($replyTo)
    {
        $this->replyTo = $replyTo;
    }

    public function getReplyTo()
    {
        return $this->replyTo;
    }

    public function getRequestId()
    {
        return $this->requestId;
    }

    /**
     * @param $json
     * @return RequestInterface
     */
    public static function parseFromJSON($json)
    {
        $request = new Request();
        $request->unserialize($json);
        return $request;

    }


}