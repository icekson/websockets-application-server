<?php
/**
 * @author a.itsekson
 * @createdAt: 31.05.2016 16:38
 */

namespace IceksonTest\WsAppServer\ConnectionPool;



use Icekson\WsAppServer\ConnectionPool\DynamicConnectionPool;
use Icekson\WsAppServer\ConnectionPool\SimpleConnectionPool;
use Icekson\WsAppServer\ConnectionPool\SimpleConnectionWrapper;
use Icekson\WsAppServer\ConnectionPool\StaticConnectionPool;

class DynamicConnectionPoolTest extends \PHPUnit_Framework_TestCase
{
    public function testStaticPoolBehavior()
    {
        $connections = [];
        $size = 100;
        for($i = 0; $i < $size; $i++){
            $connections[] =  new SimpleConnectionWrapper($this->getMockBuilder('\Icekson\WsAppServer\ConnectionPool\ConnectionInterface')
                ->getMock());
        }
        $factory = $this->getMockBuilder('\Icekson\WsAppServer\ConnectionPool\ConnectionsFactory')
            ->getMock();
        $pool = new DynamicConnectionPool($connections, $factory, ['limitPerConnection' => 2]);

        for($i = 1; $i <= $size*2; $i++){
            $pool->nextConnection();
            $this->assertEquals($size*2 - $i, $pool->getAvailableSize());
        }

        $this->assertEquals($size*2, $pool->getTotalSize());
//        $pool->nextConnection();


    }

    public function testIncreaseSizeOfPoolConnection()
    {
        $connections = [];
        $size = 100;
        for($i = 0; $i < $size; $i++){
            $connections[] =  new SimpleConnectionWrapper($this->getMockBuilder('\Icekson\WsAppServer\ConnectionPool\ConnectionInterface')
                ->getMock());
        }
        $factory = $this->getMockBuilder('\Icekson\WsAppServer\ConnectionPool\ConnectionsFactory')
            ->getMock();
        $factory->expects($this->exactly($size))->method('createConnection')->will($this->returnValue(new SimpleConnectionWrapper($this->getMockBuilder('\Icekson\WsAppServer\ConnectionPool\ConnectionInterface')
            ->getMock())));

        $pool = new DynamicConnectionPool($connections, $factory, ['limitPerConnection' => 2]);

        for($i = 1; $i <= $size*2; $i++){
            $pool->nextConnection();
            $this->assertEquals($size*2 - $i, $pool->getAvailableSize());
        }

        $this->assertEquals($size*2, $pool->getTotalSize());
        $connection = $pool->nextConnection();

        $this->assertEquals($size*2 - 1, $pool->getAvailableSize());
        $this->assertEquals($size*2*2, $pool->getTotalSize());

        $pool->releaseConnection($connection);

        $this->assertEquals($size*2, $pool->getTotalSize());


    }


}