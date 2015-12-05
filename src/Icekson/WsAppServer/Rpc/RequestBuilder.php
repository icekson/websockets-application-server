<?php
/**
 *
 * @author: a.itsekson
 * @date: 05.12.2015 13:35
 */

namespace Icekson\WsAppServer\Rpc;


use Icekson\Utils\ParamsBag;

class RequestBuilder
{

    /**
     * @var null|RequestInterface
     */
    private $request = null;

    public function __construct()
    {
        $this->request = new Request();
    }

    /**
     * @return RequestBuilder
     */
    public function createFromJSON($json)
    {
        $this->request = Request::parseFromJSON($json);
        return $this;
    }

    /**
     * @return RequestInterface|null
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param ParamsBag $params
     * @return $this
     */
    public function setParams(ParamsBag $params)
    {
        $this->request->setParams($params);
        return $this;
    }

    /**
     * @param $url
     * @return $this
     */
    public function setUrl($url)
    {
        $this->request->setUrl($url);
        return $this;
    }

    /**
     * @param $requestId
     * @return $this
     */
    public function setRequestId($requestId)
    {
        $this->request->setRequestId($requestId);
        return $this;
    }

    /**
     * @param $replyTo
     * @return $this
     */
    public function setReplyTo($replyTo)
    {
        $this->request->setReplyTo($replyTo);
        return $this;
    }
}