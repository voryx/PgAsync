<?php

use PgAsync\ConnectionPool;
use PgAsync\Tests\TestCase;
use React\Promise\Promise;
use React\Socket\ConnectorInterface;

class ConnectionPoolTest extends TestCase
{
    /** @var int */
    private $connectAttempts = 0;

    public function testTransactionWaitersDoNotOpenMoreConnectionsThanTheCapWhileConnecting()
    {
        $pool = $this->poolWithConnectionsThatNeverFinishConnecting(2);

        // A burst of transactions: none of the connections is ready yet when the next one asks for one.
        for ($i = 0; $i < 6; $i++) {
            $pool->acquire(true)->subscribe();
        }

        $this->assertSame(2, $pool->getConnectionCount());
        $this->assertSame(2, $this->connectAttempts);
    }

    public function testQueriesDoNotOpenConnectionsBeyondTheCapBecauseTransactionsAreStillConnecting()
    {
        $pool = $this->poolWithConnectionsThatNeverFinishConnecting(2);

        $pool->acquire(true)->subscribe();
        $pool->acquire(true)->subscribe();
        $pool->acquire(false)->subscribe();
        $pool->acquire(false)->subscribe();

        $this->assertSame(2, $pool->getConnectionCount());
    }

    private function poolWithConnectionsThatNeverFinishConnecting(int $maxConnections): ConnectionPool
    {
        $this->connectAttempts = 0;

        $connector = $this->createMock(ConnectorInterface::class);
        $connector->method('connect')->willReturnCallback(function () {
            $this->connectAttempts++;

            return new Promise(function () {
            });
        });

        return new ConnectionPool(
            ['host' => '127.0.0.1', 'database' => 'pgasync_test', 'user' => 'pgasync'],
            $this->getLoop(),
            $connector,
            false,
            $maxConnections
        );
    }
}
