<?php

namespace PgAsync\Tests\Integration;

use PgAsync\Client;
use PgAsync\Message\NotificationResponse;
use Rx\Observable;
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
                $this->cancelCurrentTimeoutTimer();
                $this->stopLoop();
                $this->fail("Got an error");
            },
            function () {
                $this->cancelCurrentTimeoutTimer();
                // We should wait here for a moment for the connection state to return to ready
                $this->stopLoop();
            }
        ));

        $this->runLoopWithTimeout(2);

        $this->assertEquals(1, $client->getConnectionCount());
        $this->assertEquals([ 'hello' => 'Hello' ], $hello);

        $connNew = $client->getIdleConnection();
        
        $this->assertSame($conn, $connNew);
        
        $this->assertEquals(1, $client->getConnectionCount());
        
        $client->closeNow();

        $this->getLoop()->run(); // run the loop to allow the connection to disconnect
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

    public function testSendingTwoQueriesRepeatedlyOnlyCreatesTwoConnections()
    {
        $client = new Client([
            "user"            => $this->getDbUser(),
            "database"        => $this::getDbName(),
        ], $this->getLoop());

        $value = null;

        $testQuery = $client->query("SELECT pg_sleep(0.1)")->mapTo(1)
            ->merge($client->query("SELECT pg_sleep(0.2)")->mapTo(2))
            ->concat(Observable::timer(1000)->flatMapTo(Observable::empty()))
            ->concat($client->query("SELECT pg_sleep(0.1)")->mapTo(3)
                ->merge($client->query("SELECT pg_sleep(0.2)")->mapTo(4)))
            ->concat(Observable::timer(1000)->flatMapTo(Observable::empty()))
            ->concat($client->query("SELECT pg_sleep(0.1)")->mapTo(5)
                ->merge($client->query("SELECT pg_sleep(0.2)")->mapTo(6)))
            ->toArray();

        $testQuery->subscribe(new \Rx\Observer\CallbackObserver(
            function ($results) use (&$value) {
                $value = $results;
            },
            function (\Throwable $e) use (&$error) {
                $this->fail('Error while testing');
                $this->stopLoop();
            },
            function () {
                $this->stopLoop();
            }
        ));

        $this->runLoopWithTimeout(4);

        $this->assertEquals([1,2,3,4,5,6], $value);
        $this->assertEquals(2, $client->getConnectionCount());

        $client->closeNow();
        $this->getLoop()->run();
    }

    public function testMaxConnections()
    {
        $client = new Client([
            "user"            => $this->getDbUser(),
            "database"        => $this::getDbName(),
            "max_connections" => 3
        ], $this->getLoop());

        $value = null;

        $testQuery = $client->query("SELECT pg_sleep(0.1)")->mapTo(1)
            ->merge($client->query("SELECT pg_sleep(0.2)")->mapTo(2))
            ->merge($client->query("SELECT pg_sleep(0.3)")->mapTo(3))
            ->merge($client->query("SELECT pg_sleep(0.4)")->mapTo(4))
            ->merge($client->query("SELECT pg_sleep(0.5)")->mapTo(5))
            ->merge($client->query("SELECT pg_sleep(0.6)")->mapTo(6))
            ->toArray();

        $testQuery->subscribe(new \Rx\Observer\CallbackObserver(
            function ($results) use (&$value) {
                $value = $results;
            },
            function (\Throwable $e) use (&$error) {
                $this->fail('Error while testing' . $e->getMessage());
                $this->stopLoop();
            },
            function () {
                $this->stopLoop();
            }
        ));

        $this->runLoopWithTimeout(4);

        $this->assertEquals([1,2,3,4,5,6], $value);
        $this->assertEquals(3, $client->getConnectionCount());

        $client->closeNow();
        $this->getLoop()->run();
    }

    public function testListen()
    {
        $client = new Client([
            "user"            => $this->getDbUser(),
            "database"        => $this::getDbName(),
        ], $this->getLoop());

        $testQuery = $client->listen('some_channel')
            ->merge($client->listen('some_channel')->take(1))
            ->take(3)
            ->concat($client->listen('some_channel')->take(1));

        $values = [];

        $testQuery->subscribe(
            function (NotificationResponse $results) use (&$values) {
                $values[] = $results->getPayload();
            },
            function (\Throwable $e) use (&$error) {
                $this->fail('Error while testing: ' . $e->getMessage());
                $this->stopLoop();
            },
            function () {
                $this->stopLoop();
            }
        );

        Observable::interval(300)
            ->take(3)
            ->flatMap(function ($x) use ($client) {
                return $client->executeStatement("NOTIFY some_channel, 'Hello" . $x . "'");
            })
            ->subscribe();

        $this->runLoopWithTimeout(4);

        $this->assertEquals(['Hello0', 'Hello0', 'Hello1', 'Hello2'], $values);

        $client->closeNow();
        $this->getLoop()->run();
    }
}