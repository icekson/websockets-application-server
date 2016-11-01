<?php
/**
 * @author a.itsekson
 * @createdAt: 31.05.2016 16:38
 */

namespace IceksonTest\WsAppServer\ConnectionPool;



use Icekson\WsAppServer\ConnectionPool\PoolStorage;
use Icekson\WsAppServer\ConnectionPool\ConnectionsPool;
use Icekson\WsAppServer\ConnectionPool\TimeoutException;

class PoolStorageTest extends \PHPUnit_Framework_TestCase
{
    public function testSimpleGetConnection()
    {
        $connection = $this->getMockBuilder('\Icekson\WsAppServer\Pool\ConnectionContainer')
            ->getMock();

        $pool = new ConnectionsPool(get_class($connection),20, []);

        $conn = $pool->get();

        $this->assertInstanceOf('\Icekson\WsAppServer\Pool\ConnectionContainer', $conn);

    }

    public function testCreateAndReleasePool()
    {
        $connection = $this->getMockBuilder('\Icekson\WsAppServer\Pool\ConnectionContainer')
            ->getMock();
        $pool = new ConnectionsPool(get_class($connection), 20, []);

        $instances = [];
        for($i = 0; $i < $pool->getCountUses(); $i++){
            $instance = $pool->get();
            $this->assertInstanceOf('\Icekson\WsAppServer\Pool\ConnectionContainer', $instance);
            $instances[] = $instance;
            if(count($instances) > 1){
                $this->assertTrue($instance === $instances[$i-1]);
            }
        }
        $instance = $pool->get();
        $this->assertFalse($instance === $instances[count($instances)-1]);

        $connections = $pool->getConnections();

        $this->assertCount(2, $connections);
        $this->assertTrue($pool->getCountUses() == $connections[0]['count']);
        $this->assertTrue(1 == $connections[1]['count']);

        $pool->release($instance);

        $connections = $pool->getConnections();
        $this->assertCount(1, $connections);

        $pool->release($instances[0]);
        $connections = $pool->getConnections();

        $this->assertCount(1, $connections);
        $this->assertTrue($pool->getCountUses() - 1 == $connections[0]['count']);

    }

    public function testOverflowPool()
    {
        $connection = $this->getMockBuilder('\Icekson\WsAppServer\Pool\ConnectionContainer')
            ->getMock();

        $poolSize = 20;
        $pool = new ConnectionsPool(get_class($connection), $poolSize, []);
//


        $pool->setTimeout(1);
        $instance = null;
        for ($i = 0; $i < $poolSize*100; $i++){
            $instance = $pool->get();
        }

        try{
            $pool->get();
            $this->setExpectedException(TimeoutException::class);
        }catch (TimeoutException $ex){

        }
        $pool->release($instance);
        $instance = $pool->get();

    }

}