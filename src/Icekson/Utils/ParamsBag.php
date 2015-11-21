<?php

namespace Icekson\Utils;

class ParamsBag implements IArrayExchange, \ArrayAccess {
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

	public function has($key)
	{
		return $this->params->offsetExists($key);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Whether a offset exists
	 * @link http://php.net/manual/en/arrayaccess.offsetexists.php
	 * @param mixed $offset <p>
	 * An offset to check for.
	 * </p>
	 * @return boolean true on success or false on failure.
	 * </p>
	 * <p>
	 * The return value will be casted to boolean if non-boolean was returned.
	 */
	public function offsetExists($offset)
	{
		return $this->params->offsetExists($offset);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to retrieve
	 * @link http://php.net/manual/en/arrayaccess.offsetget.php
	 * @param mixed $offset <p>
	 * The offset to retrieve.
	 * </p>
	 * @return mixed Can return all value types.
	 */
	public function offsetGet($offset)
	{
		return $this->params->offsetGet($offset);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to set
	 * @link http://php.net/manual/en/arrayaccess.offsetset.php
	 * @param mixed $offset <p>
	 * The offset to assign the value to.
	 * </p>
	 * @param mixed $value <p>
	 * The value to set.
	 * </p>
	 * @return void
	 */
	public function offsetSet($offset, $value)
	{
		$this->params->offsetSet($offset, $value);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to unset
	 * @link http://php.net/manual/en/arrayaccess.offsetunset.php
	 * @param mixed $offset <p>
	 * The offset to unset.
	 * </p>
	 * @return void
	 */
	public function offsetUnset($offset)
	{
		$this->params->offsetUnset($offset);
	}
	
	
}