<?php
/**
 * Created by PhpStorm.
 * User: Denis
 * Date: 15.06.2018
 * Time: 0:22
 */
namespace dglushakov\trassir;
use PHPUnit\Framework\TestCase;

class TrassirServerTest extends TestCase
{

    public function testIpAfterCreation()
    {
        $server = new TrassirServer('10.10.10.10');
        $this->assertEquals('10.10.10.10',$server->getIp());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Server is offline
     */
    public function testAuthorizationWhenServerIsOffline()
    {
        $server = new TrassirServer('10.18.66.99');
        $this->assertEquals(false, $server->auth('login', 'user'));
    }
    public function testAuthorizationWithWrongUserAndPassword()
    {
        $server = new TrassirServer('10.18.250.33');
        $this->assertFalse($server->auth('wronguser', 'wringpassword'));
    }
}