<?php

/**
 * @author a.itsekson
 */


namespace Icekson\Utils;

class Registry
{

    private static $instance = null;



    private $registry = array();


    private function __construct(){}

    /**
     *
     * @return Registry
     */
    public static function getInstance(){
        if(self::$instance === null){
            self::$instance = new self();
        }
        return self::$instance;
    }
    /**
     * Gets a registry key
     *
     * @param mixed $name The name of the key you want to fetch
     * @return mixed Returns the key you want to fetch, or the whole registry
     */
    public function get($name = false)
    {
        if ($name)
        {
            if ($this->exists($name))
                return $this->registry[$name];

            return null;
        }

        return $this->registry;
    }

    /**
     * Sets a registry key
     *
     * @param string $name The name of the registry key
     * @param string $registry The value of the registry
     * @param bool $overwrite Option for allowing of overwrite
     * @return bool Returns true/false if the key is set
     */
    public function set($name, $registry, $overwrite = true)
    {
        if ( ! $this->exists($name) or $overwrite)
            $this->registry[$name] = $registry;

        return true;
    }

    /**
     * Checks to see if a given key exists
     *
     * @param string $name The name of the registry key
     * @return bool Returns true/false if the key exists
     */
    public function exists($name)
    {
        $this->sanitize($name);

        return isset($this->registry[$name]);
    }

    /**
     * Removes a key from the registry
     *
     * @param string $name The name of the registry key
     * @return bool Returns true/false if the remove was successful
     */
    public function remove($name)
    {
        if ($this->exists($name))
        {
            unset($this->registry[$name]);
            return true;
        }

        return false;
    }

    /**
     * Counts the keys in the registry
     *
     * @return int Returns the amount of keys in the registry
     */
    public static function count()
    {
        return count(self::getInstance()->registry);
    }

    /**
     * Sanitizes all input
     *
     * @param string $name The name of the registry key
     * @return string Returns the sanitized string
     */
    private static function sanitize(&$name)
    {
        $name = strtolower($name);
    }

}
