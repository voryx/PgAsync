<?php

namespace PgAsync\Tests\Integration;

use PgAsync\Connection;
use Rx\Observer\CallbackObserver;

class ConnectionTest extends TestCase
{
    public function testConnectionDisconnectAfterSuccessfulQuery()
    {
        $conn = new Connection([
            "user"            => $this->getDbUser(),
            "database"        => $this::getDbName(),
            "auto_disconnect" => true
        ], $this->getLoop());
        
        $hello = null;
        
        $conn->query("SELECT 'Hello' AS hello")->subscribe(new CallbackObserver(
            function ($x) use (&$hello) {
                $this->assertNull($hello);
                $hello = $x['hello'];
            },
            function (\Exception $e) {
                $this->stopLoop();
                $this->fail();
            },
            function () {
                $this->getLoop()->addTimer(0.1, function () {
                    $this->stopLoop();
                });
            }
        ));
        
        $this->runLoopWithTimeout(2);
        
        $this->assertEquals('Hello', $hello);
        $this->assertEquals(Connection::CONNECTION_CLOSED, $conn->getConnectionStatus());
    }

    public function testConnectionDisconnectAfterFailedQuery()
    {
        $conn = new Connection([
            "user"            => $this->getDbUser(),
            "database"        => $this::getDbName(),
            "auto_disconnect" => true
        ], $this->getLoop());

        $hello = null;

        $conn->query("Some bad query")->subscribe(new CallbackObserver(
            function ($x) {
                echo "next\n";
                $this->fail('Should not get any items');
            },
            function (\Exception $e) use (&$hello) {
                $hello = "Hello";
                $this->getLoop()->addTimer(0.1, function () {
                    $this->stopLoop();
                });
            },
            function () {
                echo "complete\n";
                $this->fail('Should not complete');
            }
        ));

        $this->runLoopWithTimeout(2);

        $this->assertEquals('Hello', $hello);
        $this->assertEquals(Connection::CONNECTION_CLOSED, $conn->getConnectionStatus());
    }
}