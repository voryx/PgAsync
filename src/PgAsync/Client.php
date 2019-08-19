<?php

namespace PgAsync;

use PgAsync\Message\NotificationResponse;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectorInterface;
use Rx\Observable;
use Rx\Subject\Subject;

class Client
{
    /** @var  string */
    protected $connectString;

    /** @var LoopInterface */
    protected $loop;

    protected $params = [];

    private $parameters = [];

    /** @var Connection[] */
    private $connections = [];

    /** @var boolean */
    private $autoDisconnect;

    /** @var ConnectorInterface */
    private $connector;

    /** @var int */
    private $maxConnections = 5;

    /** @var Subject[] */
    private $listeners = [];

    /** @var Connection */
    private $listenConnection;

    public function __construct(array $parameters, LoopInterface $loop = null, ConnectorInterface $connector = null)
    {
        $this->loop      = $loop ?: \EventLoop\getLoop();
        $this->connector = $connector;

        if (isset($parameters['auto_disconnect'])) {
            $this->autoDisconnect = $parameters['auto_disconnect'];
        }

        if (isset($parameters['max_connections'])) {
            if (!is_int($parameters['max_connections'])) {
                throw new \InvalidArgumentException('`max_connections` must an be integer greater than zero.');
            }
            $this->maxConnections = $parameters['max_connections'];
            unset($parameters['max_connections']);
            if ($this->maxConnections < 1) {
                throw new \InvalidArgumentException('`max_connections` must be greater than zero.');
            }
        }

        $this->parameters = $parameters;
    }

    public function query($s)
    {
        return Observable::defer(function () use ($s) {
            $conn = $this->getLeastBusyConnection();

            return $conn->query($s);
        });
    }

    public function executeStatement(string $queryString, array $parameters = [])
    {
        return Observable::defer(function () use ($queryString, $parameters) {
            $conn = $this->getLeastBusyConnection();

            return $conn->executeStatement($queryString, $parameters);
        });
    }

    private function getLeastBusyConnection(): Connection
    {
        if (count($this->connections) === 0) {
            // try to spin up another connection to return
            $conn = $this->createNewConnection();
            if ($conn === null) {
                throw new \Exception('There are no connections. Cannot find least busy one and could not create a new one.');
            }

            return $conn;
        }

        $min = $this->connections[0];

        foreach ($this->connections as $connection) {
            // if this connection is idle - just return it
            if ($connection->getBacklogLength() === 0 && $connection->getState() === Connection::STATE_READY) {
                return $connection;
            }

            if ($min->getBacklogLength() > $connection->getBacklogLength()) {
                $min = $connection;
            }
        }

        if (count($this->connections) < $this->maxConnections) {
            return $this->createNewConnection();
        }

        return $min;
    }

    public function getIdleConnection(): Connection
    {
        // we want to get the first available one
        // this will keep the connections at the front the busiest
        // and then we can add an idle timer to the connections
        foreach ($this->connections as $connection) {
            // need to figure out different states (in trans etc.)
            if ($connection->getState() === Connection::STATE_READY) {
                return $connection;
            }
        }

        if (count($this->connections) >= $this->maxConnections) {
            return null;
        }

        return $this->createNewConnection();
    }

    private function createNewConnection()
    {
        // no idle connections were found - spin up new one
        $connection = new Connection($this->parameters, $this->loop, $this->connector);
        if ($this->autoDisconnect) {
            return $connection;
        }

        $this->connections[] = $connection;

        $connection->on('close', function () use ($connection) {
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

    /**
     * This is here temporarily so that the tests can disconnect
     * Will be setup better/more gracefully at some point hopefully
     *
     * @deprecated
     */
    public function closeNow()
    {
        foreach ($this->connections as $connection) {
            $connection->disconnect();
        }
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
                $this->listenConnection = $this->createNewConnection();
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
