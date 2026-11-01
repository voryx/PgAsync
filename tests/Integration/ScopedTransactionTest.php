<?php

namespace PgAsync\Tests\Integration;

use PgAsync\Client;
use PgAsync\Transaction;
use Rx\Observable;
use Rx\Observer\CallbackObserver;

class ScopedTransactionTest extends TestCase
{
    public function tearDown(): void
    {
        try {
            $client = $this->client();
            $stop = function () {
                self::getLoop()->stop();
            };
            $client->executeStatement(
                "DELETE FROM thing WHERE thing_type NOT IN ('pen', 'pencil', 'marker')"
            )->subscribe(new CallbackObserver($stop, $stop, $stop));
            self::getLoop()->run();
            $client->closeNow();
        } catch (\Throwable $e) {
            // Ignore cleanup errors
        }
        parent::tearDown();
    }

    private function client(array $parameters = []): Client
    {
        return self::clientFromEnv(array_merge([
            'user'     => $this->getDbUser(),
            'password' => $this::getDbUser(),
            'database' => $this::getDbName(),
        ], $parameters), $this->getLoop());
    }

    private function insert(Transaction $tx, string $thingType): Observable
    {
        return $tx->executeStatement(
            'INSERT INTO thing(thing_type, thing_cost) VALUES ($1, $2)',
            [$thingType, 1.00]
        );
    }

    /**
     * Counts the rows of a thing type over the pool, and stops the loop with the count in $count.
     */
    private function countThings(Client $client, string $thingType, &$count)
    {
        $client->executeStatement('SELECT COUNT(*) AS c FROM thing WHERE thing_type = $1', [$thingType])
            ->subscribe(new CallbackObserver(
                function ($row) use (&$count) {
                    $count = (int) $row['c'];
                    $this->stopLoop();
                },
                function ($e) {
                    $this->fail('Count query failed: ' . $e->getMessage());
                    $this->stopLoop();
                }
            ));
    }

    public function testScopedTransactionCommitsWhenTheWorkCompletes()
    {
        $client = $this->client();
        $thingType = 'scoped_commit_' . uniqid();
        $emitted = [];
        $count = null;

        $client->transaction(function (Transaction $tx) use ($thingType) {
            return $this->insert($tx, $thingType)
                ->concat($tx->query("SELECT 'from-work' AS marker"));
        })->subscribe(new CallbackObserver(
            function ($row) use (&$emitted) {
                $emitted[] = $row;
            },
            function ($e) {
                $this->fail('Unexpected error: ' . $e->getMessage());
                $this->stopLoop();
            },
            function () use ($client, $thingType, &$count) {
                $this->countThings($client, $thingType, $count);
            }
        ));

        $this->runLoopWithTimeout(5);

        $client->closeNow();
        $this->assertSame(1, $count);
        // Only what the work emitted: not the COMMIT status.
        $this->assertSame([['marker' => 'from-work']], $emitted);
        $this->getLoop()->run();
    }

    public function testScopedTransactionRollsBackAndRethrowsTheOriginalError()
    {
        $client = $this->client(['max_connections' => 1]);
        $thingType = 'scoped_rollback_' . uniqid();
        $error = null;
        $count = null;

        $client->transaction(function (Transaction $tx) use ($thingType) {
            return $this->insert($tx, $thingType)->concat($tx->query('SELECT 1/0'));
        })->subscribe(new CallbackObserver(
            function () {
            },
            function ($e) use ($client, $thingType, &$error, &$count) {
                $error = $e;
                $this->countThings($client, $thingType, $count);
            }
        ));

        $this->runLoopWithTimeout(5);

        $client->closeNow();
        $this->assertNotNull($error);
        $this->assertStringContainsString('division by zero', $error->getMessage());
        $this->assertSame(0, $count, 'The insert was rolled back, and the single connection was released for the count');
        $this->getLoop()->run();
    }

    public function testScopedTransactionRollsBackWhenTheWorkThrows()
    {
        $client = $this->client(['max_connections' => 1]);
        $error = null;
        $released = false;

        $client->transaction(function (Transaction $tx) {
            throw new \DomainException('boom');
        })->subscribe(new CallbackObserver(
            function () {
            },
            function ($e) use ($client, &$error, &$released) {
                $error = $e;
                $client->transaction(function (Transaction $tx) use (&$released) {
                    $released = true;

                    return $tx->query('SELECT 1');
                })->subscribe(new CallbackObserver(null, null, function () {
                    $this->stopLoop();
                }));
            }
        ));

        $this->runLoopWithTimeout(5);

        $client->closeNow();
        $this->assertInstanceOf(\DomainException::class, $error);
        $this->assertTrue($released);
        $this->getLoop()->run();
    }

    public function testUnsubscribingBeforeTheWorkCompletesRollsBackAndReleasesTheConnection()
    {
        $client = $this->client(['max_connections' => 1]);
        $thingType = 'scoped_dispose_' . uniqid();
        $count = null;

        $subscription = null;
        $subscription = $client->transaction(function (Transaction $tx) use ($thingType, &$subscription) {
            return $this->insert($tx, $thingType)->concat(Observable::defer(function () use (&$subscription) {
                // The caller loses interest while the work is still running.
                self::getLoop()->futureTick(function () use (&$subscription) {
                    $subscription->dispose();
                    // Give the rollback a moment, then use the only connection of the pool.
                    self::getLoop()->addTimer(0.2, function () {
                        $this->stopLoop();
                    });
                });

                return Observable::never();
            }));
        })->subscribe(new CallbackObserver());

        $this->runLoopWithTimeout(5);
        $this->countThings($client, $thingType, $count);
        $this->runLoopWithTimeout(5);

        $client->closeNow();
        $this->assertSame(0, $count, 'The insert was rolled back and the connection was released for the count');
        $this->getLoop()->run();
    }

    public function testAWaiterThatUnsubscribesDoesNotTakeTheConnectionWhenItFreesUp()
    {
        $client = $this->client(['max_connections' => 1]);
        $thirdRan = false;

        $client->transaction()->subscribe(new CallbackObserver(function (Transaction $first) use ($client, &$thirdRan) {
            // The pool is full: this one waits, then gives up before the first transaction ends.
            $waiter = $client->transaction()->subscribe(new CallbackObserver(function () {
                $this->fail('The waiter unsubscribed and must never get a transaction');
            }));
            $waiter->dispose();

            $first->commit()->subscribe(new CallbackObserver(null, null, function () use ($client, &$thirdRan) {
                $client->transaction(function (Transaction $tx) use (&$thirdRan) {
                    $thirdRan = true;

                    return $tx->query('SELECT 1');
                })->subscribe(new CallbackObserver(null, null, function () {
                    $this->stopLoop();
                }));
            }));
        }));

        $this->runLoopWithTimeout(5);

        $client->closeNow();
        $this->assertTrue($thirdRan, 'The connection was not held by the waiter that had gone away');
        $this->getLoop()->run();
    }

    public function testAFailedCommitReleasesTheConnection()
    {
        $client = $this->client(['max_connections' => 1]);
        $table = 'deferred_' . substr(uniqid(), -8);
        $commitError = null;
        $usable = false;

        $client->query(
            "CREATE TABLE $table (id int PRIMARY KEY, ref int REFERENCES $table (id) DEFERRABLE INITIALLY DEFERRED)"
        )->toArray()->subscribe(new CallbackObserver(null, null, function () use ($client, $table, &$commitError, &$usable) {
            $client->transaction()->flatMap(function (Transaction $tx) use ($table) {
                // The foreign key is only checked at COMMIT, which then fails.
                return $tx->query("INSERT INTO $table (id, ref) VALUES (1, 99)")
                    ->concat($tx->commit());
            })->subscribe(new CallbackObserver(null, function ($e) use ($client, &$commitError, &$usable) {
                $commitError = $e;
                $client->query('SELECT 1')->subscribe(new CallbackObserver(function () use (&$usable) {
                    $usable = true;
                }, null, function () {
                    $this->stopLoop();
                }));
            }));
        }));

        $this->runLoopWithTimeout(5);

        $cleanup = false;
        $client->query("DROP TABLE IF EXISTS $table")->toArray()->subscribe(new CallbackObserver(null, null, function () use (&$cleanup) {
            $cleanup = true;
            $this->stopLoop();
        }));
        $this->runLoopWithTimeout(5);

        $client->closeNow();
        $this->assertNotNull($commitError);
        $this->assertTrue($usable, 'The only connection was released and is clean after the failed COMMIT');
        $this->assertTrue($cleanup);
        $this->getLoop()->run();
    }
}
