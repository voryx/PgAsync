<?php

namespace PgAsync\Tests\Integration;

use PgAsync\Client;
use PgAsync\Transaction;
use Rx\Observable;
use Rx\Observer\CallbackObserver;

class TransactionTest extends TestCase
{
    public function tearDown(): void
    {
        // Clean up any rows inserted by transaction tests from the shared `thing` table,
        // keeping only the original fixture rows (pen, pencil, marker).
        try {
            $client = $this->client();
            $stopLoop = function () {
                self::getLoop()->stop();
            };
            $client->executeStatement(
                "DELETE FROM thing WHERE thing_type NOT IN ('pen', 'pencil', 'marker')"
            )->subscribe(new CallbackObserver(
                $stopLoop,
                $stopLoop,
                $stopLoop
            ));
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

    private function countThings(Client $client, string $thingType): Observable
    {
        return $client->executeStatement(
            'SELECT COUNT(*) AS c FROM thing WHERE thing_type = $1',
            [$thingType]
        );
    }

    public function testCommitPersistsChanges()
    {
        $client = $this->client();
        $thingType = 'txn_commit_' . uniqid();
        $count = null;

        $client->transaction()->flatMap(function (Transaction $tx) use ($thingType) {
            return $tx->executeStatement(
                'INSERT INTO thing(thing_type, thing_cost) VALUES ($1, $2)',
                [$thingType, 1.00]
            )->concat($tx->commit());
        })->flatMap(function () use ($client, $thingType) {
            return $this->countThings($client, $thingType);
        })->subscribe(new CallbackObserver(
            function ($row) use (&$count) {
                $count = (int) $row['c'];
                $this->stopLoop();
            },
            function ($e) {
                $this->fail('Count query failed: ' . $e->getMessage());
                $this->stopLoop();
            }
        ));

        $this->runLoopWithTimeout(5);

        $client->closeNow();
        $this->assertSame(1, $count);
        $this->getLoop()->run();
    }

    public function testConcurrentClientQueryUsesOtherConnection()
    {
        $client = $this->client(['max_connections' => 2]);
        $thingType = 'txn_concurrent_' . uniqid();
        $parallelResult = null;

        $client->transaction()->flatMap(function (Transaction $tx) use ($client, $thingType, &$parallelResult) {
            return $tx->executeStatement(
                'INSERT INTO thing(thing_type, thing_cost) VALUES ($1, $2)',
                [$thingType, 1.00]
            )->concat(
                $client->executeStatement("SELECT 'parallel' AS label", [])
                    ->do(function ($row) use (&$parallelResult) {
                        $parallelResult = $row['label'];
                    })
            )->concat($tx->rollback());
        })->subscribe(new CallbackObserver(
            function () {
                $this->stopLoop();
            },
            function ($e) {
                $this->fail('Concurrent query failed: ' . $e->getMessage());
                $this->stopLoop();
            }
        ));

        $this->runLoopWithTimeout(5);

        $client->closeNow();
        $this->assertSame('parallel', $parallelResult);
        $this->assertGreaterThanOrEqual(2, $client->getConnectionCount());
        $this->getLoop()->run();
    }

    public function testFailedTransactionRequiresRollback()
    {
        $client = $this->client();
        $error = null;
        $afterRollback = null;
        $transaction = null;

        $client->transaction()->flatMap(function (Transaction $tx) use (&$transaction) {
            $transaction = $tx;

            return $tx->query('SELECT 1/0');
        })->subscribe(
            function () {
                $this->fail('Expected query to fail');
            },
            function ($e) use ($client, &$error, &$afterRollback, &$transaction) {
                $error = $e;

                $transaction->rollback()->concat(
                    $client->executeStatement("SELECT 'ok' AS label", [])
                        ->do(function ($row) use (&$afterRollback) {
                            $afterRollback = $row['label'];
                        })
                )->subscribe(new CallbackObserver(
                    function () {
                        $this->stopLoop();
                    },
                    function ($e) {
                        $this->fail('Post-rollback query failed: ' . $e->getMessage());
                        $this->stopLoop();
                    }
                ));
            }
        );

        $this->runLoopWithTimeout(5);

        $client->closeNow();
        $this->assertNotNull($error);
        $this->assertSame('ok', $afterRollback);
        $this->getLoop()->run();
    }

    public function testTransactionAutoRollbackOnError()
    {
        $client = $this->client();
        $thingType = 'txn_scoped_rollback_' . uniqid();
        $count = null;
        $error = null;

        $client->transaction()->flatMap(function (Transaction $tx) use ($thingType) {
            return $tx->executeStatement(
                'INSERT INTO thing(thing_type, thing_cost) VALUES ($1, $2)',
                [$thingType, 2.00]
            )->concat($tx->query('SELECT 1/0'))
                ->catch(function (\Throwable $e) use ($tx) {
                    return $tx->rollback()->concat(Observable::error($e));
                });
        })->subscribe(
            function () {
                $this->fail('Expected transaction to fail');
            },
            function ($e) use ($client, $thingType, &$error, &$count) {
                $error = $e;

                $this->countThings($client, $thingType)->subscribe(new CallbackObserver(
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
        );

        $this->runLoopWithTimeout(5);

        $client->closeNow();
        $this->assertNotNull($error);
        $this->assertSame(0, $count);
        $this->getLoop()->run();
    }

    public function testDoubleCommitRejected()
    {
        $client = $this->client();
        $error = null;

        $client->transaction()->flatMap(function (Transaction $tx) {
            return $tx->commit()->concatMap(function () use ($tx) {
                return $tx->commit();
            });
        })->subscribe(
            function () {
                $this->fail('Expected second commit to error');
                $this->stopLoop();
            },
            function ($e) use (&$error) {
                $error = $e;
                $this->stopLoop();
            }
        );

        $this->runLoopWithTimeout(5);

        $client->closeNow();
        $this->assertInstanceOf(\RuntimeException::class, $error);
        $this->assertTrue(strpos($error->getMessage(), 'already been committed') !== false);
        $this->getLoop()->run();
    }

    public function testTransactionWaitsForAvailableConnection()
    {
        $client = $this->client(['max_connections' => 1]);
        $firstStarted = false;
        $secondStarted = false;
        $secondBegin = null;

        $client->transaction()->flatMap(function (Transaction $tx) use ($client, &$firstStarted, &$secondBegin) {
            $firstStarted = true;
            $secondBegin = $client->transaction();

            return $tx->commit();
        })->flatMap(function () use (&$secondBegin, &$secondStarted) {
            return $secondBegin->flatMap(function (Transaction $tx2) use (&$secondStarted) {
                $secondStarted = true;

                return $tx2->commit();
            });
        })->subscribe(new CallbackObserver(
            function () {
                $this->stopLoop();
            },
            function ($e) {
                $this->fail('Unexpected error: ' . $e->getMessage());
                $this->stopLoop();
            }
        ));

        $this->runLoopWithTimeout(5);

        $client->closeNow();
        $this->assertTrue($firstStarted);
        $this->assertTrue($secondStarted);
        $this->assertSame(1, $client->getConnectionCount());
        $this->getLoop()->run();
    }

    public function testQueryWaitsWhenAllConnectionsAreReservedForTransactions()
    {
        $client = $this->client(['max_connections' => 2]);
        $queryResult = null;

        $client->transaction()->flatMap(function (Transaction $tx1) use ($client, &$queryResult) {
            return $client->transaction()->flatMap(function (Transaction $tx2) use ($client, &$queryResult, $tx1) {
                $query = $client->executeStatement("SELECT 'queued' AS label", [])
                    ->do(function ($row) use (&$queryResult) {
                        $queryResult = $row['label'];
                    });

                return $tx2->rollback()->concat($query)->concat($tx1->rollback());
            });
        })->subscribe(new CallbackObserver(
            function () {
                $this->stopLoop();
            },
            function ($e) {
                $this->fail('Unexpected error: ' . $e->getMessage());
                $this->stopLoop();
            }
        ));

        $this->runLoopWithTimeout(5);

        $client->closeNow();
        $this->assertSame('queued', $queryResult);
        $this->assertSame(2, $client->getConnectionCount());
        $this->getLoop()->run();
    }

    public function testTransactionIsolation()
    {
        $client = $this->client(['max_connections' => 5]);
        $thingType = 'txn_isolation_' . uniqid();

        $results = [];
        $errors = [];

        // State: client: 0, A: 0, B: 0
        $client->transaction()->flatMap(function (Transaction $tx) use ($thingType) {
            // State: client: 0, A: 0, B: 0
            // Step 1: Insert initial row so table has 1 row (committed before any transaction)
            return $tx->executeStatement(
                'INSERT INTO thing(thing_type, thing_cost) VALUES ($1, $2)',
                [$thingType, 0.50]
            )->concat($tx->commit());
        })->flatMap(function () use ($client, $thingType, &$results) {
            // State: client: 1, A: 0, B: 0
            // Step 2: Start transaction A with REPEATABLE READ isolation
            return $client->transaction()->flatMap(function (Transaction $txA) use ($client, $thingType, &$results) {
                // State: client: 1, A: 1, B: 0
                // Step 3: In transaction A insert one row & validate count
                return $txA->executeStatement(
                    'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'
                )->concat($txA->executeStatement(
                    'INSERT INTO thing(thing_type, thing_cost) VALUES ($1, $2)',
                    [$thingType, 1.00]
                ))->concat($txA->executeStatement(
                    'SELECT COUNT(*) AS c FROM thing WHERE thing_type = $1',
                    [$thingType]
                ))->flatMap(function ($row) use ($txA, $client, $thingType, &$results) {
                    // State: client: 1, A: 2, B: 0
                    // Step 4: In transaction A validate the table now has two rows
                    $results['step4'] = (int) $row['c'];
                    // Step 5: Using the client validate the table has one item
                    return $client->executeStatement(
                        'SELECT COUNT(*) AS c FROM thing WHERE thing_type = $1',
                        [$thingType]
                    );
                })->flatMap(function ($row) use ($txA, $client, $thingType, &$results) {
                    // State: client: 1, A: 2, B: 0
                    // Step 5: Validate client sees 1 row (only the committed initial row)
                    $results['step5'] = (int) $row['c'];
                    // Step 6: Start transaction B
                    return $client->transaction()->flatMap(function (Transaction $txB) use ($txA, $client, $thingType, &$results) {
                        // State: client: 1, A: 2, B: 1
                        // Step 7: In transaction B insert 3 rows & validate counts
                        return $txB->executeStatement(
                            'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'
                        )->concat($txB->executeStatement(
                            'INSERT INTO thing(thing_type, thing_cost) VALUES ($1, $2)',
                            [$thingType, 3.00]
                        ))->concat($txB->executeStatement(
                            'INSERT INTO thing(thing_type, thing_cost) VALUES ($1, $2)',
                            [$thingType, 4.00]
                        ))->concat($txB->executeStatement(
                            'INSERT INTO thing(thing_type, thing_cost) VALUES ($1, $2)',
                            [$thingType, 5.00]
                        ))->concat($txA->executeStatement(
                            'SELECT COUNT(*) AS c FROM thing WHERE thing_type = $1',
                            [$thingType]
                        ))->flatMap(function ($row) use ($txA, $txB, $thingType, &$results) {
                            // State: client: 1, A: 2, B: 4
                            // Step 8: In transaction A validate the table still has two rows
                            $results['step8'] = (int) $row['c'];
                            return $txB->executeStatement(
                                'SELECT COUNT(*) AS c FROM thing WHERE thing_type = $1',
                                [$thingType]
                            );
                        })->flatMap(function ($row) use ($txA, $txB, $client, $thingType, &$results) {
                            // State: client: 1, A: 2, B: 4
                            // Step 9: In transaction B validate the table now has 4 rows
                            $results['step9'] = (int) $row['c'];
                            // Step 10: Using the client validate the table has one item
                            return $client->executeStatement(
                                'SELECT COUNT(*) AS c FROM thing WHERE thing_type = $1',
                                [$thingType]
                            );
                        })->flatMap(function ($row) use ($txA, $txB, &$results) {
                            // State: client: 1, A: 2, B: 4
                            // Step 10: Validate client sees 1 row (both tx uncommitted)
                            $results['step10'] = (int) $row['c'];
                            // Step 11: Commit transaction B
                            return $txB->commit();
                        })->flatMap(function () use ($txA, $thingType, &$results) {
                            // State: client: 4, A: 2, B: 0
                            // Step 12: In transaction A validate the table still has two rows
                            return $txA->executeStatement(
                                'SELECT COUNT(*) AS c FROM thing WHERE thing_type = $1',
                                [$thingType]
                            );
                        })->flatMap(function ($row) use ($txA, $client, $thingType, &$results) {
                            // State: client: 4, A: 2, B: 0
                            // Step 12: Validate txA still sees 2 rows (isolated from txB commit)
                            $results['step12'] = (int) $row['c'];
                            // Step 13: Using the client validate the table now has 4 rows
                            return $client->executeStatement(
                                'SELECT COUNT(*) AS c FROM thing WHERE thing_type = $1',
                                [$thingType]
                            );
                        })->flatMap(function ($row) use ($txA, &$results) {
                            // State: client: 4, A: 2, B: 0
                            // Step 13: Validate client sees 4 rows (1 initial + 3 from txB committed)
                            $results['step13'] = (int) $row['c'];
                            // Step 14: Commit transaction A
                            return $txA->commit();
                        })->flatMap(function () use ($client, $thingType, &$results) {
                            // State: client: 5, A: 0, B: 0
                            // Step 15: Using the client validate the table now has 5 rows
                            return $client->executeStatement(
                                'SELECT COUNT(*) AS c FROM thing WHERE thing_type = $1',
                                [$thingType]
                            );
                        })->flatMap(function ($row) use (&$results) {
                            // State: client: 5, A: 0, B: 0
                            // Step 15: Validate client sees 5 rows (1 initial + 1 from txA + 3 from txB)
                            $results['step15'] = (int) $row['c'];
                            return Observable::of('done');
                        });
                    });
                });
            });
        })->subscribe(new CallbackObserver(
            function () {
                $this->stopLoop();
            },
            function ($e) use (&$errors) {
                $errors[] = $e->getMessage();
                $this->stopLoop();
            }
        ));

        $this->runLoopWithTimeout(30);

        $client->closeNow();

        if (!empty($errors)) {
            $this->fail('Error: ' . implode('; ', $errors));
        }

        if (!isset($results['step4'])) {
            $this->fail('Observable chain did not complete. Results: ' . print_r($results, true) . ' Errors: ' . print_r($errors, true));
        }

        $this->assertSame(2, $results['step4'], 'Step 4: txA sees 2 rows (1 initial + 1 inserted in txA)');
        $this->assertSame(1, $results['step5'], 'Step 5: client sees 1 row (only the committed initial row)');
        $this->assertSame(2, $results['step8'], 'Step 8: txA still sees 2 rows (isolated from txB inserts)');
        $this->assertSame(4, $results['step9'], 'Step 9: txB sees 4 rows (1 initial + 3 from txB)');
        $this->assertSame(1, $results['step10'], 'Step 10: client sees 1 row (both tx uncommitted)');
        $this->assertSame(2, $results['step12'], 'Step 12: txA still sees 2 rows (isolated from txB commit)');
        $this->assertSame(4, $results['step13'], 'Step 13: client sees 4 rows (1 initial + 3 from txB committed)');
        $this->assertSame(5, $results['step15'], 'Step 15: client sees 5 rows (1 initial + 1 from txA + 3 from txB)');

        $this->getLoop()->run();
    }
}
