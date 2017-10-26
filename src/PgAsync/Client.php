<?php

namespace PgAsync;

use React\EventLoop\LoopInterface;
use React\Socket\ConnectorInterface;

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

    public function __construct(array $parameters, LoopInterface $loop = null, ConnectorInterface $connector = null)
    {
        $this->parameters = $parameters;
        $this->loop       = $loop ?: \EventLoop\getLoop();
        $this->connector  = $connector;

        if (isset($parameters['auto_disconnect'])) {
            $this->autoDisconnect = $parameters['auto_disconnect'];
        }
    }

    public function query($s)
    {
        $conn = $this->getIdleConnection();

        return $conn->query($s);
    }

    public function executeStatement(string $queryString, array $parameters = [])
    {
        $conn = $this->getIdleConnection();

        return $conn->executeStatement($queryString, $parameters);
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

        // no idle connections were found - spin up new one
        $connection = new Connection($this->parameters, $this->loop, $this->connector);
        if (!$this->autoDisconnect) {
            $this->connections[] = $connection;

            $connection->on('close', function () use ($connection) {
                $this->connections = array_filter($this->connections, function ($c) use ($connection) {
                    return $connection !== $c;
                });
            });
        }

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
}
