<?php

namespace PgAsync;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use React\EventLoop\LoopInterface;

/**
 * Class Client
 * @package PgAsync
 */
class Client implements EventEmitterInterface
{
    use EventEmitterTrait;

    /** @var  string */
    protected $connectString;
    /** @var LoopInterface */
    protected $loop;

    protected $params = [];

    /** @var Connection[] */
    private $connections = [];

    /**
     * @param $connectString
     * @param $loop
     */
    function __construct($connectString, $loop)
    {
        $this->connectString = $connectString;
        $this->loop          = $loop;
    }

    public function query($s)
    {
        $conn = $this->getIdleConnection();

        return $conn->query($s);
    }

    public function executeStatement($queryString, $parameters)
    {
        $conn = $this->getIdleConnection();
    }

    public function getIdleConnection() {
        // we want to get the first available one
        // this will keep the connections at the front the busiest
        // and then we can add an idle timer to the connections
        foreach ($this->connections as $connection) {
            // need to figure out different states (in trans etc.)
            if ($connection->getState() === Connection::STATE_READY) {
                return $connection;
            }
        }

        $connection = new Connection([
            "user" => "matt",
            "database" => "matt"
        ], $this->loop);

        $this->connections[] = $connection;

        // no idle connections were found - spin up new one
        return $connection;
    }

    public function getConnectionCount() {
        return count($this->connections);
    }
}