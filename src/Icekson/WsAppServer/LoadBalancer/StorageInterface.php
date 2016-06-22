<?php
/**
 *
 * @author: a.itsekson
 * @date: 06.12.2015 13:47
 */

namespace Icekson\WsAppServer\LoadBalancer;


interface StorageInterface
{
    public function geCountOfConnections($serviceName);

    public function incrementCounter($serviceName);

    public function decrementCounter($serviceName);

    public function persistAvailableConnectors(array $services);

    public function retrieveAvailableConnectors();

    public function reset();

}