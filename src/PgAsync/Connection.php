<?php

namespace PgAsync;

use PgAsync\Message\Authentication;
use PgAsync\Message\BackendKeyData;
use PgAsync\Message\CommandComplete;
use PgAsync\Message\CommandInterface;
use PgAsync\Message\CopyInResponse;
use PgAsync\Message\CopyOutResponse;
use PgAsync\Message\DataRow;
use PgAsync\Message\EmptyQueryResponse;
use PgAsync\Message\ErrorResponse;
use PgAsync\Message\Message;
use PgAsync\Message\NoticeResponse;
use PgAsync\Message\ParameterStatus;
use PgAsync\Message\ParseComplete;
use PgAsync\Message\ParserInterface;
use PgAsync\Message\Query;
use PgAsync\Message\ReadyForQuery;
use PgAsync\Message\RowDescription;
use PgAsync\Message\StartupMessage;
use React\EventLoop\LoopInterface;
use React\SocketClient\Connector;
use React\Stream\Stream;

class Connection
{
    const STATE_IDLE = 0;
    const STATE_BUSY = 1;
    const STATE_READY = 2;
    const STATE_COPY_IN = 3;
    const STATE_COPY_OUT = 4;
    const STATE_COPY_BOTH = 5;

    const QUERY_SIMPLE = 0;
    const QUERY_EXTENDED = 1;
    const QUERY_PREPARE = 2;
    const QUERY_DESCRIBE = 3;

    const CONNECTION_OK = 0;
    const CONNECTION_BAD = 1;
    const CONNECTION_STARTED = 2;           /* Waiting for connection to be made.  */
    const CONNECTION_MADE = 3;              /* Connection OK; waiting to send.     */
    const CONNECTION_AWAITING_RESPONSE = 4; /* Waiting for a response from the
                                         * postmaster.        */
    const CONNECTION_AUTH_OK = 5;           /* Received authentication; waiting for
                                         * backend startup. */
    const CONNECTION_SETENV = 6;            /* Negotiating environment. */
    const CONNECTION_SSL_STARTUP = 7;       /* Negotiating SSL. */
    const CONNECTION_NEEDED = 8;            /* Internal state: connect() needed */

    private $queryState;
    private $queryType;
    private $connStatus;

    /** @var Stream */
    private $stream;
    private $socket;

    private $parameters;

    /** @var LoopInterface */
    private $loop;

    /** @var \SplQueue */
    private $commandQueue;

    /** @var ParserInterface */
    private $currentMessage;

    /**
     * Connection constructor.
     */
    public function __construct($parameters, LoopInterface $loop)
    {
        if (!is_array($parameters) ||
            !isset($parameters['user']) ||
            !isset($parameters['database'])
        ) {
            throw new \InvalidArgumentException("Parameters must be an associative array with at least 'database' and 'user' set.");
        }

        $this->parameters = $parameters;
        $this->loop       = $loop;

        $this->commandQueue = new \SplQueue();

        $this->queryState = static::STATE_BUSY;
        $this->queryType  = static::QUERY_SIMPLE;
        $this->connStatus = static::CONNECTION_NEEDED;

        $this->start();
    }

    private function getDnsResolver()
    {
        $dnsResolverFactory = new \React\Dns\Resolver\Factory();
        $dns                = $dnsResolverFactory->createCached('8.8.8.8', $this->loop);

        return $dns;
    }

    public function start()
    {
        if ($this->connStatus !== static::CONNECTION_NEEDED) {
            throw new \Exception("Connection not in startable state");
        }

        $this->connStatus = static::CONNECTION_STARTED;

        $this->socket = new Connector($this->loop, $this->getDnsResolver());

        $this->socket->create('127.0.0.1', 5432)->then(
            function (Stream $stream) {
                $this->stream = $stream;

                $this->connStatus = static::CONNECTION_MADE;


                $stream->on('data', [$this, 'onData']);

//            $ssl = new SSLRequest();
//            $stream->write($ssl->encodedMessage());

                $startup = new StartupMessage();
                $startup->setParameters($this->parameters);
                $stream->write($startup->encodedMessage());
            },
            function () {
                // connection error
                // TODO - this needs to notify someone
                $this->connStatus = static::CONNECTION_BAD;
            }
        );
    }

    public function onData($data)
    {
        if ($this->currentMessage) {
            $overflow = $this->currentMessage->parseData($data);
            echo "-" . json_encode($overflow) . "-\n";
            if ($overflow === false) {
                // there was not enough data to complete the message
                // leave this as the currentParser
                echo "false\n";
                return;
            }

            $this->handleMessage($this->currentMessage);

            $this->currentMessage = null;

            if (strlen($overflow) > 0) {
                $this->onData($overflow);
            }

            return;
        }

        if (strlen($data) == 0) {
            return;
        }

        $type = $data[0];

        $message = Message::createMessageFromIdentifier($type);
        if ($message !== false) {
            $this->currentMessage = $message;
            $this->onData($data);
        }

//        if (in_array($type, ['R', 'S', 'D', 'K', '2', '3', 'C', 'd', 'c', 'G', 'H', 'W', 'D', 'I', 'E', 'V', 'n', 'N', 'A', 't', '1', 's', 'Z', 'T'])) {
//            $this->currentParser = [$this, 'parse1PlusLenMessage'];
//            call_user_func($this->currentParser, $data);
//        } else {
//            echo "Unhandled message \"".$type."\"";
//        }
    }

    public function handleMessage($message)
    {
        echo "Handling " . get_class($message) . "\n";
        if ($message instanceof DataRow) {
            $this->handleDataRow($message);
        } elseif ($message instanceof Authentication) {
            $this->handleAuthentication($message);
        } elseif ($message instanceof BackendKeyData) {
            $this->handleBackendKeyData($message);
        } elseif ($message instanceof CommandComplete) {
            $this->handleCommandComplete($message);
        } elseif ($message instanceof CopyInResponse) {
            $this->handleCopyInResponse($message);
        } elseif ($message instanceof CopyOutResponse) {
            $this->handleCopyOutResponse($message);
        } elseif ($message instanceof EmptyQueryResponse) {
            $this->handleEmptyQueryResponse($message);
        } elseif ($message instanceof ErrorResponse) {
            $this->handleErrorResponse($message);
        } elseif ($message instanceof NoticeResponse) {
            $this->handleNoticeResponse($message);
        } elseif ($message instanceof ParameterStatus) {
            $this->handleParameterStatus($message);
        } elseif ($message instanceof ParseComplete) {
            $this->handleParseComplete($message);
        } elseif ($message instanceof ReadyForQuery) {
            $this->handleReadyForQuery($message);
        } elseif ($message instanceof RowDescription) {
            $this->handleRowDescription($message);
        }
    }

    private function handleDataRow(DataRow $dataRow)
    {
        foreach($dataRow->getColumnValues() as $value) {
            echo $value . " ";
        }
        echo "\n";
    }

    private function handleAuthentication(Authentication $message)
    {
    }

    private function handleBackendKeyData(BackendKeyData $message)
    {
    }

    private function handleCommandComplete(CommandComplete $message)
    {
        echo "Complete.\n";
    }

    private function handleCopyInResponse(CopyInResponse $message)
    {
    }

    private function handleCopyOutResponse(CopyOutResponse $message)
    {
    }

    private function handleEmptyQueryResponse(EmptyQueryResponse $message)
    {
    }

    private function handleErrorResponse(ErrorResponse $message)
    {
    }

    private function handleNoticeResponse(NoticeResponse $message)
    {
    }

    private function handleParameterStatus(ParameterStatus $message)
    {
        echo $message->getParameterName() . ": " . $message->getParameterValue() . "\n";
    }

    private function handleParseComplete(ParseComplete $message)
    {
    }

    private function handleReadyForQuery(ReadyForQuery $message)
    {
        $this->queryState = $this::STATE_READY;
        $this->processQueue();
    }

    private function handleRowDescription(RowDescription $message)
    {
        foreach($message->getColumns() as $column) {
            echo $column->name . " ";
        }
        echo "\n";
    }

    public function processQueue()
    {
        while ($this->commandQueue->count() > 0 && $this->queryState === static::STATE_READY) {
            /** @var CommandInterface $c */
            $c = $this->commandQueue->dequeue();
            $this->stream->write($c->encodedMessage());
            if ($c->shouldWaitForComplete()) {
                $this->currentCommand = $c;
                return;
            }
        }
    }

    public function query($query)
    {
        $q = new Query($query);
        $this->commandQueue->enqueue($q);

        $this->processQueue();

        return $q->getSubject();
    }
}