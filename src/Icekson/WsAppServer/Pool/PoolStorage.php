<?php
/**
 * @author a.itsekson
 * @createdAt: 31.05.2016 16:05
 */

namespace Icekson\WsAppServer\Pool;


abstract class PoolStorage
{

    private $size = 10;

    private $countUses = 100;

    protected $connections = null;

    private $type = null;

    private $config = null;

    private $timeout = 30;

    public function __construct($type, $size, $config)
    {
        if (!class_exists($type)) {
            throw new \RuntimeException("Given class {$type} does not exist");
        }
        $this->config = $config;
        $this->type = $type;
        $this->connections = new \ArrayObject();
        $this->size = $size;
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @param int $size
     */
    public function setSize($size)
    {
        $this->size = $size;
    }

    /**
     * @return int
     */
    public function getCountUses()
    {
        return $this->countUses;
    }

    /**
     * @param int $countUses
     */
    public function setCountUses($countUses)
    {
        $this->countUses = $countUses;
    }

    /**
     * @return int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @param int $timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }




    public function get()
    {
        $instance = null;

        foreach ($this->connections as &$connectionData) {
            if ($connectionData['count'] < $this->getCountUses()) {
                $connectionData['count']++;
                $instance = $connectionData['instance'];
                break;
            }
        }
        if ($instance === null) {
            if($this->connections->count() >= $this->getSize()){
                $this->wait();
            }
            $instance = $this->createInstance();
            $this->connections->append([
                'instance' => $instance,
                'count' => 1
            ]);
        }
        return $instance;

    }

    private function wait()
    {
        $start = time();
        $exceeded = true;
        while($exceeded){
            foreach ($this->connections as $connection) {
                if($connection['count'] < $this->getCountUses()){
                    $exceeded = false;
                    break;
                }
            }
            if(time() - $start > $this->timeout){
                throw new TimeoutException("Timeout is exceeded. There is no available free connection");
            }
        }
    }

    /**
     * @param $instance
     */
    public function release($instance)
    {
        if(! $instance instanceof $this->type){
            throw new \InvalidArgumentException("Invalid pool instance is given, expected type is: {$this->type}, but '".get_class($instance)."' is given");
        }
        $toRemove = [];
        foreach ($this->connections as $index => &$connectionData) {
            if($connectionData['instance'] === $instance){
                $connectionData['count']--;
                if($connectionData['count'] == 0){
                    $toRemove[] = $index;
                }
            }
        }
        if(!empty($toRemove)){
            foreach ($toRemove as $index) {
                $instanceData = $this->connections[$index];
                $this->dispose($instanceData['instance']);
                unset($this->connections[$index]);
            }
        }
    }

    /**
     * @return null
     */
    protected function getType()
    {
        return $this->type;
    }

    /**
     * @return null
     */
    protected function getConfig()
    {
        return $this->config;
    }






    abstract protected function createInstance();

    abstract protected function dispose($instance);

}