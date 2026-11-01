<?php

namespace PgAsync\Tests\Integration;

use PgAsync\Client;
use PgAsync\Transaction;
use Rx\Observer\CallbackObserver;

class TransactionConnectionReleaseTest extends TestCase
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
                $client->transaction()->subscribe(new CallbackObserver(function (Transaction $third) use (&$thirdRan) {
                    $thirdRan = true;
                    $third->rollback()->subscribe(new CallbackObserver(null, null, function () {
                        $this->stopLoop();
                    }));
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
