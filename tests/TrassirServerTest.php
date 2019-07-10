<?php
require_once './src/TrassirServer.php';

class TrassirServerTest extends \PHPUnit\Framework\TestCase
{
    public function testCanCraeteInstanceWithIpOnly(){
        $nvr = new \Dglushakov\Trassir\TrassirServer('127.0.0.1');
        $this->assertInstanceOf(\Dglushakov\Trassir\TrassirServer::class, $nvr);
        $this->assertEquals('127.0.0.1', $nvr->getIp());
    }

    public function testExceptionIfNotValidIp(){
        $this->expectException(InvalidArgumentException::class);
        $nvr = new \Dglushakov\Trassir\TrassirServer('adress' );
    }

    public function testCanCraeteInstanceWithAllfields(){
        $nvr = new \Dglushakov\Trassir\TrassirServer('127.0.0.1', 'user', 'pass', 'sdkPass');
        $this->assertInstanceOf(\Dglushakov\Trassir\TrassirServer::class, $nvr);
        $this->assertEquals('user', $nvr->getUserName());
        $this->assertEquals('pass', $nvr->getPassword());
        $this->assertEquals('sdkPass', $nvr->getSdkPassword());

    }

    public function testCanSetUserName(){
        $nvr = new \Dglushakov\Trassir\TrassirServer('127.0.0.1');
        $nvr->setUserName('user');

        $this->assertEquals('user', $nvr->getUserName());
    }

    public function testCanSetPassword(){
        $nvr = new \Dglushakov\Trassir\TrassirServer('127.0.0.1');
        $nvr->setPassword('pass');

        $this->assertEquals('pass', $nvr->getPassword());
    }

    public function testCanSetSdkPassword(){
        $nvr = new \Dglushakov\Trassir\TrassirServer('127.0.0.1');
        $nvr->setSdkPassword('sdkpass');

        $this->assertEquals('sdkpass', $nvr->getSdkPassword());
    }

    public function testCheckConnectionWithNotValidIp(){
        $nvr = new \Dglushakov\Trassir\TrassirServer('127.0.0.3');
        $this->assertEquals(false, $nvr->checkConnection());
    }
    public function testCheckConnectionWithValidIp(){
        $nvr = new \Dglushakov\Trassir\TrassirServer('10.18.144.33');
        $this->assertEquals(true, $nvr->checkConnection());
    }



}
