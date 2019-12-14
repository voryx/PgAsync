<?php

namespace PgAsync\Tests\Integration;

use PgAsync\Client;
use Rx\Observer\CallbackObserver;

class Md5PasswordTest extends TestCase
{
    public function testMd5Login()
    {
        $client = new Client([
            "user" => "pgasyncpw",
            "database" => $this->getDbName(),
            "auto_disconnect" => true,
            "password" => "example_password"
        ], $this->getLoop());
        
        $hello = null;
        
        $client->query("SELECT 'Hello' AS hello")
            ->subscribe(new CallbackObserver(
                function ($x) use (&$hello) {
                    $this->assertNull($hello);
                    $hello = $x['hello'];
                },
                function ($e) {
                    $this->fail('Unexpected error');
                },
                function () {
                    $this->getLoop()->addTimer(0.1, function () {
                        $this->stopLoop();
                    });
                }
            ));

        $this->runLoopWithTimeout(2);
        
        $this->assertEquals('Hello', $hello);
    }
}