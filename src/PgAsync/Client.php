<?php

namespace PgAsync;

use PgAsync\Message\NotificationResponse;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectorInterface;
use Rx\Observable;
use Rx\Subject\Subject;

class Client
{
    /** @var LoopInterface */
    protected $loop;

    /** @var ConnectionPool */
    private $connectionPool;

    /** @var Subject[] */
    private $listeners = [];

    /** @var Connection|null */
    private $listenConnection;

    public function __construct(array $parameters, ?LoopInterface $loop = null, ?ConnectorInterface $connector = null)
    {
        $this->loop = $loop ?: \EventLoop\getLoop();

        $autoDisconnect = false;
        $maxConnections = 5;

        if (isset($parameters['auto_disconnect'])) {
            $autoDisconnect = $parameters['auto_disconnect'];
        }

        if (isset($parameters['max_connections'])) {
            if (!is_int($parameters['max_connections'])) {
                throw new \InvalidArgumentException('`max_connections` must an be integer greater than zero.');
            }
            $maxConnections = $parameters['max_connections'];
            unset($parameters['max_connections']);
            if ($maxConnections < 1) {
                throw new \InvalidArgumentException('`max_connections` must be greater than zero.');
            }
        }

        $this->connectionPool = new ConnectionPool(
            $parameters,
            $this->loop,
            $connector,
            $autoDisconnect,
            $maxConnections
        );
    }

    public function query($s)
    {
        return Observable::defer(function () use ($s) {
            return $this->connectionPool->acquire(false)->flatMap(function (Connection $conn) use ($s) {
                // Wait for the connection to be ready after the query completes before releasing,
                // so the connection can be properly reused by subsequent queries.
                return $conn->query($s)
                    ->concat(Observable::defer(function () use ($conn) {
                        return $conn->whenReadyForQuery();
                    }))
                    ->do(function () use ($conn) {
                        $this->connectionPool->release($conn);
                    })
                    // Only emit the query rows, not the transaction status string
                    ->takeWhile(function ($v) {
                        return is_array($v);
                    });
            });
        });
    }

    public function transaction(): Observable
    {
        return Observable::defer(function () {
            return $this->connectionPool->acquire(true)->flatMap(function (Connection $connection) {
                return $connection->queryUntilReady('BEGIN')->flatMap(function ($status) use ($connection) {
                    if ($status !== 'T') {
                        $this->connectionPool->release($connection);

                        return Observable::error(new \RuntimeException('Expected transaction status T after BEGIN'));
                    }

                    return Observable::of($this->createTransaction($connection));
                })->catch(function (\Throwable $e) use ($connection) {
                    $this->connectionPool->release($connection);

                    return Observable::error($e);
                });
            });
        });
    }

    private function createTransaction(Connection $connection): Transaction
    {
        return new Transaction(
            $connection,
            function () use ($connection) {
                $this->connectionPool->release($connection);
            }
        );
    }

    public function executeStatement(string $queryString, array $parameters = [])
    {
        return Observable::defer(function () use ($queryString, $parameters) {
            return $this->connectionPool->acquire(false)->flatMap(function (Connection $conn) use ($queryString, $parameters) {
                return $conn->executeStatement($queryString, $parameters)->do(function () use ($conn) {
                    $this->connectionPool->release($conn);
                });
            });
        });
    }

    /**
     * @return Connection|null
     */
    public function getIdleConnection()
    {
        return $this->connectionPool->getIdleConnection();
    }

    public function getConnectionCount(): int
    {
        return $this->connectionPool->getConnectionCount();
    }

    /**
     * This is here temporarily so that the tests can disconnect
     * Will be setup better/more gracefully at some point hopefully
     *
     * @deprecated
     */
    public function closeNow()
    {
        $this->connectionPool->closeAll();
    }

    public function listen(string $channel): Observable
    {
        if (isset($this->listeners[$channel])) {
            return $this->listeners[$channel];
        }

        $unlisten = function () use ($channel) {
            $this->listenConnection->query('UNLISTEN ' . $channel)->subscribe();

            unset($this->listeners[$channel]);

            if (empty($this->listeners)) {
                $this->listenConnection->disconnect();
                $this->listenConnection = null;
            }
        };

        $this->listeners[$channel] = Observable::defer(function () use ($channel) {
            if ($this->listenConnection === null) {
                $this->listenConnection = $this->connectionPool->createConnection();
            }

            if ($this->listenConnection === null) {
                throw new \Exception('Could not get new connection to listen on.');
            }

            return $this->listenConnection->query('LISTEN ' . $channel)
                ->merge($this->listenConnection->notifications())
                ->filter(function (NotificationResponse $message) use ($channel) {
                    return $message->getChannelName() === $channel;
                });
        })
            ->finally($unlisten)
            ->share();

        return $this->listeners[$channel];
    }
}
