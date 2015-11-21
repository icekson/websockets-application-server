<?php
/**
 * @author a.itsekson
 * @date 04.09.2015
 *
 */

namespace Icekson\WsAppServer\Messaging;


use CN\Service\RequestParser;
use Icekson\Utils\ParamsBag;

class Request
{
    /**
     * @var RequestParser
     */
    private $requestParser;

    /**
     * @var ParamsBag
     */
    private $params;

    public function __construct($input){
        $this->requestParser = new RequestParser($input);
        $res = $this->requestParser->parse();
        $this->params = new ParamsBag($res);
    }

    /**
     * @return Properties
     */
    public function params(){
        return $this->params;
    }
}