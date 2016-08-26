<?php

namespace PgAsync\Tests\Integration;

use PgAsync\Client;
use Rx\Observer\CallbackObserver;

class ClientTest extends TestCase
{
    public function testClientReusesIdleConnection()
    {
        $client = new Client(["user" => $this->getDbUser(), "database" => $this::getDbName()], $this->getLoop());
        
        $hello = null;
        
        $client->executeStatement("SELECT 'Hello' AS Hello")->subscribe(new CallbackObserver(
            function ($x) use (&$hello) {
                $this->assertNull($hello);
                $hello = $x;
            },
            function ($e) {
                $this->fail("Got an error");
                $this->cancelCurrentTimeoutTimer();
                $this->stopLoop();
            },
            function () {
                $this->cancelCurrentTimeoutTimer();
                // We should wait here for a moment for the connection state to return to ready
                $this->getLoop()->addTimer(0.1, function () {
                    $this->stopLoop();    
                }); 
            }
        ));

        $this->runLoopWithTimeout(2);
        
        $this->assertEquals(1, $client->getConnectionCount());
        $this->assertEquals([ 'hello' => 'Hello' ], $hello);
        
        $conn = $client->getIdleConnection();
        $this->assertEquals(1, $client->getConnectionCount());
        
        $hello = null;

        $client->executeStatement("SELECT 'Hello' AS Hello")->subscribe(new CallbackObserver(
            function ($x) use (&$hello) {
                $this->assertNull($hello);
                $hello = $x;
            },
            function ($e) {
                $this->fail("Got an error");
                $this->cancelCurrentTimeoutTimer();
                $this->stopLoop();
            },
            function () {
                $this->cancelCurrentTimeoutTimer();
                // We should wait here for a moment for the connection state to return to ready
                $this->getLoop()->addTimer(0.1, function () {
                    $this->stopLoop();
                });
            }
        ));

        $this->runLoopWithTimeout(2);

        $this->assertEquals(1, $client->getConnectionCount());
        $this->assertEquals([ 'hello' => 'Hello' ], $hello);

        $connNew = $client->getIdleConnection();
        
        $this->assertSame($conn, $connNew);
        
        $this->assertEquals(1, $client->getConnectionCount());
        
        $client->closeNow();
    }

    public function testAutoDisconnect()
    {
        $client = new Client([
            "user"            => $this->getDbUser(),
            "database"        => $this::getDbName(),
            "auto_disconnect" => true
        ], $this->getLoop());

        $hello = null;

        $client->executeStatement("SELECT 'Hello' AS Hello")->subscribe(new CallbackObserver(
            function ($x) use (&$hello) {
                $this->assertNull($hello);
                $hello = $x;
            },
            function ($e) {
                $this->fail("Got an error");
                $this->cancelCurrentTimeoutTimer();
                $this->stopLoop();
            },
            function () {
                // wait a bit for things to close down
                $this->getLoop()->addTimer(0.1, function () {
                    $this->cancelCurrentTimeoutTimer();
                });
            }
        ));

        $this->runLoopWithTimeout(2);

        $this->assertEquals(0, $client->getConnectionCount());
        $this->assertEquals([ 'hello' => 'Hello' ], $hello);
    }
}