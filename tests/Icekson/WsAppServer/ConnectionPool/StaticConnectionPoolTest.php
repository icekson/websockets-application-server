<?php
/**
 * @author a.itsekson
 * @createdAt: 31.05.2016 16:38
 */

namespace IceksonTest\WsAppServer\ConnectionPool;




use Icekson\WsAppServer\ConnectionPool\SimpleConnectionPool;
use Icekson\WsAppServer\ConnectionPool\SimpleConnectionWrapper;
use Icekson\WsAppServer\ConnectionPool\StaticConnectionPool;


class StaticConnectionPoolTest extends \PHPUnit_Framework_TestCase
{
    public function testSimpleGetConnection()
    {
        $connection = $this->getMockBuilder('\Icekson\WsAppServer\ConnectionPool\ConnectionInterface')
            ->getMock();

        $connection2 = $this->getMockBuilder('\Icekson\WsAppServer\ConnectionPool\ConnectionInterface')
            ->getMock();

        $wrapper1 = new SimpleConnectionWrapper($connection);
        $wrapper2 = new SimpleConnectionWrapper($connection2);

        $this->assertFalse($wrapper1 === $wrapper2);

        $pool = new StaticConnectionPool([$wrapper1, $wrapper2], ['limitPerConnection' => 10]);

        $conn = $pool->nextConnection();
        $this->assertInstanceOf('\Icekson\WsAppServer\ConnectionPool\ConnectionWrapperInterface', $conn);
        $this->assertInstanceOf('\Icekson\WsAppServer\ConnectionPool\ConnectionInterface', $conn->getConnection());

        $conn2 = $pool->nextConnection();
        $this->assertInstanceOf('\Icekson\WsAppServer\ConnectionPool\ConnectionWrapperInterface', $conn2);
        $this->assertInstanceOf('\Icekson\WsAppServer\ConnectionPool\ConnectionInterface', $conn2->getConnection());

        $this->assertFalse($conn === $conn2);

        $this->assertEquals($connection, $conn->getConnection());
        $this->assertEquals($connection2, $conn2->getConnection());

    }

    public function testExceedLimit()
    {
        $connections = [];
        $size = 100;
        for($i = 0; $i < $size; $i++){
            $connections[] =  new SimpleConnectionWrapper($this->getMockBuilder('\Icekson\WsAppServer\ConnectionPool\ConnectionInterface')
                ->getMock());
        }
        $pool = new StaticConnectionPool($connections, ['limitPerConnection' => 2]);
        $this->assertTrue($pool->isEmpty(), "pool has not used yet");
        for($i = 1; $i <= $size*2; $i++){
            $this->assertFalse($pool->isExceededLimit(), "limit is exceeded");
            $pool->nextConnection();
            $this->assertEquals($size*2 - $i, $pool->getAvailableSize());
        }
        $this->assertFalse($pool->isEmpty(), "pool already used by someone");
        $this->assertEquals(0, $pool->getAvailableSize());
        $this->assertTrue($pool->isExceededLimit(), "limit is not exceeded");

    }

    public function testReleaseConnection()
    {
        $connections = [];
        $size = 10;
        for($i = 0; $i < $size; $i++){
            $connection =  $this->getMockBuilder('\Icekson\WsAppServer\ConnectionPool\ConnectionInterface')
                ->getMock();
            //$connection->expects($this->once())->method("close");
            $connections[] = new SimpleConnectionWrapper($connection);
        }
        $pool = new StaticConnectionPool($connections, ['limitPerConnection' => 2]);
        $conn = $pool->nextConnection();
        $this->assertEquals(($size * 2 - 1), $pool->getAvailableSize());
        $pool->releaseConnection($conn);
        $this->assertEquals($size * 2, $pool->getAvailableSize());
    }

}