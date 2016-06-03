<?php
/**
 * @author a.itsekson
 * @createdAt: 31.05.2016 16:42
 */

namespace Icekson\WsAppServer\Pool;


class ConnectionsPool extends PoolStorage
{

    protected function createInstance()
    {
        $refl = new \ReflectionClass($this->getType());
        return $refl->newInstance();
            
    }

    protected function dispose($instance)
    {
        if(method_exists($instance, "close")){
            $instance->close();
        }else if(method_exists($instance, "dispose")){
            $instance->dispose();
        }
    }

    /**
     * @return \ArrayObject|null
     */
    public function getConnections()
    {
        return $this->connections;
    }
}