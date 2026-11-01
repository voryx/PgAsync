<?php

namespace PgAsync;

use React\EventLoop\LoopInterface;
use React\Socket\ConnectorInterface;
use Rx\Disposable\EmptyDisposable;
use Rx\Observable;
use Rx\Observable\AnonymousObservable;
use Rx\ObserverInterface;

class ConnectionPool
{
    /** @var array */
    private $parameters;

    /** @var LoopInterface */
    private $loop;

    /** @var ConnectorInterface|null */
    private $connector;

    /** @var Connection[] */
    private $connections = [];

    /** @var Connection[] */
    private $reservedConnections = [];

    /** @var Connection[] */
    private $pendingReservationConnections = [];

    /** @var array<string, array{resolve: callable, reject: callable}> */
    private $pendingReservationResolvers = [];

    /** @var array<int, array{forTransaction: bool, resolve: callable, reject: callable}> */
    private $connectionWaiters = [];

    /** @var bool */
    private $autoDisconnect;

    /** @var int */
    private $maxConnections;

    public function __construct(
        array $parameters,
        LoopInterface $loop,
        ?ConnectorInterface $connector = null,
        bool $autoDisconnect = false,
        int $maxConnections = 5
    ) {
        $this->parameters     = $parameters;
        $this->loop           = $loop;
        $this->connector      = $connector;
        $this->autoDisconnect = $autoDisconnect;
        $this->maxConnections = $maxConnections;
    }

    public function acquire(bool $forTransaction): Observable
    {
        $connection = $this->tryAcquire($forTransaction);
        if ($connection !== null) {
            return Observable::of($connection);
        }

        return new AnonymousObservable(function (ObserverInterface $observer) use ($forTransaction) {
            $this->connectionWaiters[] = [
                'forTransaction' => $forTransaction,
                'resolve'        => function ($connection) use ($observer) {
                    $observer->onNext($connection);
                    $observer->onCompleted();
                },
                'reject'         => function ($e) use ($observer) {
                    $observer->onError($e);
                },
            ];

            $this->processConnectionWaiters();

            return new EmptyDisposable();
        });
    }

    public function release(Connection $connection)
    {
        unset($this->reservedConnections[spl_object_hash($connection)]);
        $this->processConnectionWaiters();
    }

    public function getIdleConnection(): ?Connection
    {
        foreach ($this->connections as $connection) {
            if ($this->isConnectionReserved($connection)) {
                continue;
            }

            if ($connection->getState() === Connection::STATE_READY) {
                return $connection;
            }
        }

        if (count($this->connections) >= $this->maxConnections) {
            return null;
        }

        return $this->createConnection();
    }

    public function createConnection(): Connection
    {
        $connection = new Connection($this->parameters, $this->loop, $this->connector);
        if ($this->autoDisconnect) {
            return $connection;
        }

        $this->connections[] = $connection;

        $connection->on('ready', function () use ($connection) {
            $this->processConnectionWaiters();
        });

        $connection->on('error', function ($e) use ($connection) {
            $hash = spl_object_hash($connection);
            if (array_key_exists($hash, $this->pendingReservationResolvers)) {
                $callbacks = $this->pendingReservationResolvers[$hash];
                unset($this->pendingReservationConnections[$hash]);
                unset($this->pendingReservationResolvers[$hash]);
                $callbacks['reject']($e);
            }
        });

        $connection->on('close', function () use ($connection) {
            $hash = spl_object_hash($connection);
            if (array_key_exists($hash, $this->pendingReservationResolvers)) {
                $callbacks = $this->pendingReservationResolvers[$hash];
                unset($this->pendingReservationConnections[$hash]);
                unset($this->pendingReservationResolvers[$hash]);
                $callbacks['reject'](new \RuntimeException('Connection closed during establishment'));
            }
            $this->release($connection);
            $this->connections = array_values(array_filter($this->connections, function ($c) use ($connection) {
                return $connection !== $c;
            }));
        });

        return $connection;
    }

    public function getConnectionCount(): int
    {
        return count($this->connections);
    }

    public function closeAll()
    {
        $this->rejectConnectionWaiters(new \RuntimeException('Client closed'));

        $error = new \RuntimeException('Client closed');
        foreach ($this->pendingReservationResolvers as $callbacks) {
            $callbacks['reject']($error);
        }
        $this->pendingReservationResolvers = [];

        foreach ($this->connections as $connection) {
            $connection->disconnect();
        }
    }

    private function tryAcquire(bool $forTransaction): ?Connection
    {
        if ($forTransaction) {
            return $this->tryReserveConnection();
        }

        return $this->tryAcquireQueryConnection();
    }

    private function tryAcquireQueryConnection(): ?Connection
    {
        foreach ($this->connections as $connection) {
            if ($this->isConnectionReserved($connection)) {
                continue;
            }

            if ($connection->getState() === Connection::STATE_READY && $connection->getBacklogLength() === 0) {
                return $connection;
            }
        }

        if ($this->autoDisconnect || count($this->connections) < $this->maxConnections) {
            return $this->createConnection();
        }

        $leastBusy = null;

        foreach ($this->connections as $connection) {
            if ($this->isConnectionReserved($connection)) {
                continue;
            }

            if ($leastBusy === null || $leastBusy->getBacklogLength() > $connection->getBacklogLength()) {
                $leastBusy = $connection;
            }
        }

        return $leastBusy;
    }

    private function tryReserveConnection(): ?Connection
    {
        foreach ($this->connections as $connection) {
            if ($this->isConnectionReserved($connection)) {
                continue;
            }

            if ($connection->getState() === Connection::STATE_READY && $connection->getBacklogLength() === 0) {
                $this->markConnectionReserved($connection);

                return $connection;
            }
        }

        return null;
    }

    private function markConnectionReserved(Connection $connection)
    {
        $this->reservedConnections[spl_object_hash($connection)] = $connection;
    }

    private function processConnectionWaiters()
    {
        foreach ($this->pendingReservationConnections as $hash => $connection) {
            if ($connection->getState() === Connection::STATE_READY) {
                $callbacks = $this->pendingReservationResolvers[$hash];
                unset($this->pendingReservationConnections[$hash]);
                unset($this->pendingReservationResolvers[$hash]);
                $callbacks['resolve']($connection);
            }
        }

        while (count($this->connectionWaiters) > 0) {
            $waiter = current($this->connectionWaiters);
            $connection = $this->tryAcquire($waiter['forTransaction']);
            if ($connection === null) {
                if ($waiter['forTransaction']) {
                    if ($this->autoDisconnect || count($this->connections) < $this->maxConnections) {
                        $newConnection = $this->createConnection();
                        $this->pendingReservationConnections[spl_object_hash($newConnection)] = $newConnection;
                        $this->markConnectionReserved($newConnection);
                        $newConnection->start();
                        array_shift($this->connectionWaiters);
                        $this->pendingReservationResolvers[spl_object_hash($newConnection)] = [
                            'resolve' => $waiter['resolve'],
                            'reject' => $waiter['reject'],
                        ];
                        continue;
                    }
                }
                return;
            }

            array_shift($this->connectionWaiters);
            $waiter['resolve']($connection);
        }
    }

    private function rejectConnectionWaiters(\Throwable $e)
    {
        $waiters = $this->connectionWaiters;
        $this->connectionWaiters = [];

        foreach ($waiters as $waiter) {
            $waiter['reject']($e);
        }
    }

    private function isConnectionReserved(Connection $connection): bool
    {
        $hash = spl_object_hash($connection);
        return array_key_exists($hash, $this->reservedConnections)
            || array_key_exists($hash, $this->pendingReservationConnections);
    }
}
