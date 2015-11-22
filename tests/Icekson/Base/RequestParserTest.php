<?php
/**
 * Created by PhpStorm.
 * User: Alexey
 * Date: 22.11.2015
 * Time: 14:42
 */

namespace IceksonTest\Base;


use Icekson\Base\RequestParser;

class RequestParserTest extends \PHPUnit_Framework_TestCase
{
    public function testParse()
    {
        $arr = ["aaa" =>"aaa", "bbb" =>"bbb"];
        $json = json_encode($arr);
        $parser = new RequestParser($json);
        $res = $parser->parse();

        $this->assertCount(count($arr), $res);
        $this->assertArrayHasKey("aaa", $res);
        $this->assertArrayHasKey("bbb", $res);
        $this->assertEquals($arr["aaa"], $res["aaa"]);
        $this->assertEquals($arr["bbb"], $res["bbb"]);
    }

}