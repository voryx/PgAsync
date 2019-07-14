<?php

namespace PgAsync\Tests\Integration;

use PgAsync\Connection;
use PgAsync\ErrorException;
use React\Dns\RecordNotFoundException;
use Rx\Observable;
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

        // At some point, DNS was returning RecordNotFoundException
        // as long as we are getting an Exception here, we should be good
        $this->assertInstanceOf(\Exception::class, $error);
        $this->assertInstanceOf(RecordNotFoundException::class, $error->getPrevious());
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

    public function testCancellationUsingDispose()
    {
        $conn = new Connection([
            "user"            => $this->getDbUser(),
            "database"        => $this::getDbName()
        ], $this->getLoop());

        $testQuery = $conn->query("SELECT pg_sleep(10)")->mapTo(1);

        $testQuery->takeUntil(Observable::timer(500))->subscribe(
            function ($results) {
                $this->fail('Expected no value');
                $this->stopLoop();
            },
            function (\Throwable $e) {
                $this->fail('Expected no error');
                $this->stopLoop();
            },
            function () {
                $this->stopLoop();
            }
        );

        $this->runLoopWithTimeout(2);

        $conn->disconnect();
        $this->getLoop()->run();
    }

    public function testCancellationUsingInternalFunctions()
    {
        $conn = new Connection([
            "user"            => $this->getDbUser(),
            "database"        => $this::getDbName()
        ], $this->getLoop());

        $testQuery = $conn->query("SELECT pg_sleep(10)")->mapTo(1);

        $error = null;

        $testQuery->subscribe(
            function ($results) {
                $this->fail('Expected no value');
                $this->stopLoop();
            },
            function (\Throwable $e) use (&$error) {
                $error = $e;
                $this->stopLoop();
            },
            function () {
                $this->fail('Expected no completion');
                $this->stopLoop();
            }
        );

        $this->getLoop()->addTimer(0.5, function () use ($conn) {
            $r = new \ReflectionClass($conn);
            $m = $r->getMethod('cancelRequest');
            $m->setAccessible(true);
            $m->invoke($conn);
            $m->setAccessible(false);
        });

        $this->runLoopWithTimeout(2);

        $this->assertInstanceOf(ErrorException::class, $error);
        $this->assertStringStartsWith('ERROR: canceling statement due to user request while executing', $error->getMessage());

        $conn->disconnect();
        $this->getLoop()->run();
    }

    public function testCancellationOfNonActiveQuery()
    {
        $conn = new Connection([
            "user"            => $this->getDbUser(),
            "database"        => $this::getDbName()
        ], $this->getLoop());

        $testQuery = $conn->query("SELECT pg_sleep(1)")->mapTo(1)
            ->merge($conn->query('SELECT pg_sleep(1)')
                ->mapTo(2)
                ->takeUntil(Observable::timer(250))
            )
            ->merge($conn->query('SELECT pg_sleep(1)')->mapTo(3))
            ->toArray();

        $value = null;

        $testQuery->subscribe(
            function ($results) use (&$value) {
                $value = $results;
                $this->stopLoop();
            },
            function (\Throwable $e) {
                $this->fail('Expected no error' . $e->getMessage());
                $this->stopLoop();
            },
            function () {
                $this->stopLoop();
            }
        );

        $this->runLoopWithTimeout(15);

        $this->assertEquals([1,3], $value);

        $conn->disconnect();
        $this->getLoop()->run();
    }

    public function testCancellationWithImmediateQueryQueuedUp() {
        $conn = new Connection([
            "user"            => $this->getDbUser(),
            "database"        => $this::getDbName()
        ], $this->getLoop());

        $q1 = $conn->query("SELECT * FROM generate_series(1,4)");
        $q2 = $conn->query("SELECT pg_sleep(10)");

        $testQuery = $q1->merge($q2)->take(1);

        $value = null;

        $testQuery->subscribe(
            function ($results) use (&$value) {
                $value = $results;
                $this->stopLoop();
            },
            function (\Throwable $e) {
                $this->fail('Expected no error' . $e->getMessage());
                $this->stopLoop();
            },
            function () {
                $this->stopLoop();
            }
        );

        $this->runLoopWithTimeout(15);

        $this->assertEquals(['generate_series' => '1'], $value);

        $conn->disconnect();
        $this->getLoop()->run();
    }

    public function testArrayInParameters() {
        $conn = new Connection([
            "user"            => $this->getDbUser(),
            "database"        => $this::getDbName()
        ], $this->getLoop());

        $testQuery = $conn->executeStatement("SELECT * FROM generate_series(1,4) WHERE generate_series = ANY($1)", ['{2, 3}']);

        $value = [];

        $testQuery->subscribe(
            function ($results) use (&$value) {
                $value[] = $results;
                $this->stopLoop();
            },
            function (\Throwable $e) {
                $this->fail('Expected no error' . $e->getMessage());
                $this->stopLoop();
            },
            function () {
                $this->stopLoop();
            }
        );

        $this->runLoopWithTimeout(15);

        $this->assertEquals([['generate_series' => 2], ['generate_series' => 3]], $value);

        $conn->disconnect();
        $this->getLoop()->run();
    }
}