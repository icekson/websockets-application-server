<?php
/**
 *
 * @author: a.itsekson
 * @date: 22.11.2015 15:32
 */

namespace IceksonTest\Utils;


use Icekson\Utils\ParamsBag;

class ParamsBagTest extends \PHPUnit_Framework_TestCase
{

    public function testArrayExchange()
    {
        $arr = ["aaa" =>"aaa", "bbb" => "bbb"];
        $bag = new ParamsBag($arr);
        $res = $bag->toArray();

        $this->assertCount(count($arr), $res);
        $this->assertArrayHasKey("aaa", $res);
        $this->assertArrayHasKey("bbb", $res);
        $this->assertEquals($arr["aaa"], $res["aaa"]);
        $this->assertEquals($arr["bbb"], $res["bbb"]);
    }

    public function testPutGet()
    {
        $bag = new ParamsBag([]);
        $this->assertCount(0, $bag->toArray());

        $bag->put("aaa", "bbb");

        $this->assertCount(1, $bag->toArray());
        $this->assertEquals("bbb", $bag->get("aaa"));
    }

    public function testDefaultValue()
    {
        $bag = new ParamsBag([]);
        $val = $bag->get("notexist", "default");

        $this->assertEquals("default", $val);

        $val2 = $bag->get("notexist2", []);
        $this->assertEquals([], $val2);
    }

}