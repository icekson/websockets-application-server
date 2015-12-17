<?php

namespace Icekson\Base;

class RequestParser
{

    const STATUS_ERROR = false;
    const STATUS_SUCCESS = true;

    private $data = "";

    private $status = self::STATUS_SUCCESS;

    private $messages = array();

    public function __construct($input = null){
        $requestData = $input === null ? file_get_contents("php://input") : $input;
        $this->data = $requestData;
    }

    /**
     * @return \SimpleXMLElement | array
     */
    public function parse(){
        $res = null;
        $res = $this->parseJSON($this->data);
        return (array)$res;
    }


    /**
     *
     * @return array $json
     */
    private function parseJSON($json){
        // $json = str_replace(array("\n","\r"),"",$json);
        //  $json = preg_replace('/([{,]+)(\s*)([^"]+?)\s*:/','$1"$3":',$json);
        //  $json = preg_replace('/(,)\s*}$/','}',$json);

        $res = json_decode($json,true);


        $error = json_last_error();
        if(!empty($error)){
            $this->status = self::STATUS_ERROR;
            $this->messages[] = $error;
        }
        return $res;

    }
    public function hasErrors()
    {
        return $this->status == self::STATUS_ERROR;
    }

    public function getErrors(){
        return $this->messages;
    }


}