<?php

namespace PgAsync\Tests\Integration;

use PgAsync\Connection;
use React\Dns\RecordNotFoundException;
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
                $this->stopLoop();
            }
        ));
        
        $this->runLoopWithTimeout(2);
        
        $this->assertEquals('Hello', $hello);
        $this->assertEquals(Connection::CONNECTION_CLOSED, $conn->getConnectionStatus());
    }

    public function testConnectionDisconnectAfterSuccessfulStatement()
    {
        $conn = new Connection([
            "user"            => $this->getDbUser(),
            "database"        => $this::getDbName(),
            "auto_disconnect" => true
        ], $this->getLoop());

        $hello = null;

        $conn->executeStatement("SELECT 'Hello' AS hello", [])->subscribe(new CallbackObserver(
            function ($x) use (&$hello) {
                $this->assertNull($hello);
                $hello = $x['hello'];
            },
            function (\Exception $e) {
                $this->fail();
                $this->stopLoop();
            },
            function () {
                $this->stopLoop();
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
                $this->stopLoop();
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

    public function testInvalidHostName()
    {
        $conn = new Connection([
            "host"            => 'host.invalid',
            "user"            => $this->getDbUser(),
            "database"        => $this::getDbName(),
            "auto_disconnect" => true
        ], $this->getLoop());

        $testQuery = $conn->query("SELECT 1");

        $error = null;

        $testQuery->subscribe(new \Rx\Observer\CallbackObserver(
            function ($row) {
                $this->fail('Did not expect onNext to be called.');
            },
            function (\Throwable $e) use (&$error) {
                $error = $e;
                $this->stopLoop();
            },
            function () {
                $this->fail('Did not expect onNext to be called.');
            }
        ));

        $this->runLoopWithTimeout(2);

        $this->assertInstanceOf(RecordNotFoundException::class, $error);
    }

    public function testSendingTwoQueriesWithoutWaitingNoAutoDisconnect()
    {
        $conn = new Connection([
            "user"            => $this->getDbUser(),
            "database"        => $this::getDbName()
        ], $this->getLoop());

        $testQuery = $conn->query("SELECT pg_sleep(0.1)")->mapTo(1)
            ->merge($conn->query("SELECT pg_sleep(0.2)")->mapTo(2))
            ->toArray();

        $value = null;

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

        $this->runLoopWithTimeout(2);

        $this->assertEquals([1,2], $value);

        $conn->disconnect();
        $this->getLoop()->run();
    }

    public function testSendingTwoQueriesWithoutWaitingAutoDisconnect()
    {
        $conn = new Connection([
            "user"            => $this->getDbUser(),
            "database"        => $this::getDbName(),
            "auto_disconnect" => true
        ], $this->getLoop());

        $testQuery = $conn->query("SELECT pg_sleep(0.1)")->mapTo(1)
            ->merge($conn->query("SELECT pg_sleep(0.2)")->mapTo(2))
            ->toArray();

        $value = null;

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

        $this->runLoopWithTimeout(2);

        $this->assertEquals([1,2], $value);

        $this->getLoop()->run();
    }
}