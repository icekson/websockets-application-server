<?php
/**
 *
 * @author: a.itsekson
 * @date: 22.11.2015 15:38
 */

namespace IceksonTest\WsAppServer\Config;


use Icekson\Config\ConfigAdapter;

class ConfigAdapterTest extends \PHPUnit_Framework_TestCase
{
    public function testReadFromJsonFile()
    {
        $file = __DIR__ . "/../../../data/config.json";
        $conf = new ConfigAdapter($file);

        $arr = $conf->toArray();
        $this->assertTrue(is_array($arr));
        $this->assertArrayHasKey("key1", $arr);
        $key1 = $arr["key1"];
        $this->assertTrue(is_array($key1));
        $this->assertArrayHasKey("key2", $key1);
        $key2 = $key1["key2"];
        $this->assertTrue(is_string($key2));
    }


    public function testCreateFromArray()
    {
        $arr = ["aaa" => "bbb", "ccc" => 1];
        $conf = new ConfigAdapter($arr);

        $res = $conf->toArray();

        $this->assertEquals($arr, $res);

        $v = $conf->get("aaa");
        $this->assertEquals("bbb", $v);
    }
}