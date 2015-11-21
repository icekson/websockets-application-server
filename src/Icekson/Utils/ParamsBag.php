<?php

namespace Icekson\Utils;

class ParamsBag implements IArrayExchange {
	protected  $params = null;
	
	public function __construct(array $params = array()){
		$this->params = new \ArrayObject($params);
	}
	
	public function get($name, $default = null){
		if($this->params->offsetExists($name)){
			return $this->params->offsetGet($name);
		}
		return $default;
	}
	
	public function put($key, $value){
		$this->params[$key] = $value;
		return $this;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see IArrayExchange::toArray()
	 */
	public function toArray(){
	    return $this->params->getArrayCopy();
	}
	
	public function fromArray(array $data){
	    $this->params->exchangeArray($data);	    
	}
	
	
}