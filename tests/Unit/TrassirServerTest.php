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
    /**
     * @var TrassirServer $server
     */
    private $server;
    public function setUp()
    {
        $this->server = new TrassirServer('127.0.0.1');
    }

    public function testGetName()
    {
        $this->server->setName('new name');
        $this->assertEquals('new name', $this->server->getName());
    }

    public function testSetName()
    {
        $this->server->setName('new name');
        $this->assertEquals('new name', $this->server->getName());
        $this->server->setName('anothername');
        $this->assertEquals('anothername', $this->server->getName());
    }

    public function testGetGuid()
    {
        $this->server->setGuid('new one');
        $this->assertEquals('new one', $this->server->getGuid());
    }

    public function testSetGuid()
    {
        $this->server->setGuid('new guid');
        $this->assertEquals('new guid', $this->server->getGuid());
        $this->server->setGuid('anotherguid');
        $this->assertEquals('anotherguid', $this->server->getGuid());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Server is offline
     */
    public function testAuthorizationWhenServerIsOffline()
    {
        $server = new TrassirServer('10.18.66.99');
        $this->assertEquals(false, $server->authorization('login', 'user'));
    }

    public function testAuthorizationWithWrongUserAndPassword()
    {
        $server = new TrassirServer('10.18.250.33');
        $this->assertFalse($server->authorization('wronguser', 'wringpassword'));
    }






}
