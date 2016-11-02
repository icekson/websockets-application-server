<?php
/**
 * @author a.itsekson
 * @createdAt: 31.05.2016 16:38
 */

namespace IceksonTest\WsAppServer\ConnectionPool;



use Icekson\WsAppServer\ConnectionPool\PoolStorage;
use Icekson\WsAppServer\ConnectionPool\ConnectionsPool;
use Icekson\WsAppServer\ConnectionPool\Selector\RoundRobinSelector;
use Icekson\WsAppServer\ConnectionPool\SimpleConnectionPool;
use Icekson\WsAppServer\ConnectionPool\SimpleConnectionWrapper;
use Icekson\WsAppServer\ConnectionPool\StaticConnectionPool;
use Icekson\WsAppServer\ConnectionPool\TimeoutException;

class RoundRobinSelectorTest extends \PHPUnit_Framework_TestCase
{
    public function testSelectOneConnection()
    {
        $connection = $this->getMockBuilder('\Icekson\WsAppServer\ConnectionPool\ConnectionInterface')
            ->getMock();

        $connection2 = $this->getMockBuilder('\Icekson\WsAppServer\ConnectionPool\ConnectionInterface')
            ->getMock();

        $wrapper1 = new SimpleConnectionWrapper($connection);
        $wrapper2 = new SimpleConnectionWrapper($connection2);

        $selector = new RoundRobinSelector();

        $conn = $selector->select([$wrapper1, $wrapper2]);
        $this->assertInstanceOf('\Icekson\WsAppServer\ConnectionPool\ConnectionWrapperInterface', $conn);
        $this->assertInstanceOf('\Icekson\WsAppServer\ConnectionPool\ConnectionInterface', $conn->getConnection());
        $this->assertEquals($connection, $conn->getConnection());

        $conn = $selector->select([$wrapper1, $wrapper2]);
        $this->assertInstanceOf('\Icekson\WsAppServer\ConnectionPool\ConnectionWrapperInterface', $conn);
        $this->assertInstanceOf('\Icekson\WsAppServer\ConnectionPool\ConnectionInterface', $conn->getConnection());
        $this->assertEquals($connection2, $conn->getConnection());

        $conn = $selector->select([$wrapper1, $wrapper2]);
        $this->assertEquals($connection, $conn->getConnection());



    }
}