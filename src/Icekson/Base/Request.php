<?php
/**
 * @author a.itsekson
 * @date 04.09.2015
 *
 */

namespace Icekson\Base;

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
     * @return ParamsBag
     */
    public function params(){
        return $this->params;
    }
}