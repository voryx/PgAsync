<?php

namespace PgAsync;

use Evenement\EventEmitter;
use PgAsync\Command\Bind;
use PgAsync\Command\CancelRequest;
use PgAsync\Command\Close;
use PgAsync\Command\Describe;
use PgAsync\Command\Execute;
use PgAsync\Command\Parse;
use PgAsync\Command\PasswordMessage;
use PgAsync\Command\Sync;
use PgAsync\Command\Terminate;
use PgAsync\Message\Authentication;
use PgAsync\Message\BackendKeyData;
use PgAsync\Message\CommandComplete;
use PgAsync\Command\CommandInterface;
use PgAsync\Message\CopyInResponse;
use PgAsync\Message\CopyOutResponse;
use PgAsync\Message\DataRow;
use PgAsync\Message\EmptyQueryResponse;
use PgAsync\Message\ErrorResponse;
use PgAsync\Message\Message;
use PgAsync\Message\NoticeResponse;
use PgAsync\Message\NotificationResponse;
use PgAsync\Message\ParameterStatus;
use PgAsync\Message\ParseComplete;
use PgAsync\Command\Query;
use PgAsync\Message\ReadyForQuery;
use PgAsync\Message\RowDescription;
use PgAsync\Command\StartupMessage;
use React\EventLoop\LoopInterface;
use React\Socket\Connector;
use React\Socket\ConnectorInterface;
use React\Stream\DuplexStreamInterface;
use Rx\Disposable\CallbackDisposable;
use Rx\Disposable\EmptyDisposable;
use Rx\Observable;
use Rx\Observable\AnonymousObservable;
use Rx\ObserverInterface;
use Rx\SchedulerInterface;
use Rx\Subject\Subject;

class Connection extends EventEmitter
{
    // This is copied a lot of these states from the libpq library
    // Not many of these constants are used right now
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
    const CONNECTION_CLOSED = 9;

    private $queryState;
    private $queryType;
    private $connStatus;

    /** @var DuplexStreamInterface */
    private $stream;

    /** @var ConnectorInterface */
    private $socket;

    private $parameters;

    /** @var LoopInterface */
    private $loop;

    /** @var CommandInterface[] */
    private $commandQueue;

    /** @var Message */
    private $currentMessage;

    /** @var CommandInterface */
    private $currentCommand;

    /** @var Column[] */
    private $columns = [];

    /** @var array */
    private $columnNames = [];

    /** @var string */
    private $lastError;

    /** @var BackendKeyData */
    private $backendKeyData;

    /** @var string */
    private $uri;

    /** @var Subject */
    private $notificationSubject;

    /** @var bool */
    private $cancelPending;

    /** @var bool */
    private $cancelRequested;

    /**
     * Can be 'I' for Idle, 'T' if in transactions block
     * or 'E' if in failed transaction block (queries will fail until end of trans)
     *
     * @var string
     */
    private $backendTransactionStatus = 'UNKNOWN';

    /** @var  bool */
    private $auto_disconnect = false;
    private $password;

    public function __construct(array $parameters, LoopInterface $loop, ConnectorInterface $connector = null)
    {
        if (!is_array($parameters) ||
            !isset($parameters['user']) ||
            !isset($parameters['database'])
        ) {
            throw new \InvalidArgumentException("Parameters must be an associative array with at least 'database' and 'user' set.");
        }

        if (!isset($parameters['host'])) {
            $parameters['host'] = '127.0.0.1';
        }

        if (!isset($parameters['port'])) {
            $parameters['port'] = '5432';
        }

        if (array_key_exists('password', $parameters)) {
            $this->password = $parameters['password'];
            unset($parameters['password']);
        }

        if (isset($parameters['auto_disconnect'])) {
            $this->auto_disconnect = $parameters['auto_disconnect'];
            unset($parameters['auto_disconnect']);
        }

        if (!isset($parameters['application_name'])) {
            $parameters['application_name'] = 'pgasync';
        }

        $this->loop                = $loop;
        $this->commandQueue        = [];
        $this->queryState          = static::STATE_BUSY;
        $this->queryType           = static::QUERY_SIMPLE;
        $this->connStatus          = static::CONNECTION_NEEDED;
        $this->socket              = $connector ?: new Connector($loop);
        $this->uri                 = 'tcp://' . $parameters['host'] . ':' . $parameters['port'];
        $this->notificationSubject = new Subject();
        $this->cancelPending       = false;
        $this->cancelRequested     = false;

        $this->parameters          = $parameters;
    }

    private function start()
    {
        if ($this->connStatus !== static::CONNECTION_NEEDED) {
            throw new \Exception('Connection not in startable state');
        }

        $this->connStatus = static::CONNECTION_STARTED;

        $this->socket->connect($this->uri)->then(
            function (DuplexStreamInterface $stream) {
                $this->stream     = $stream;
                $this->connStatus = static::CONNECTION_MADE;

                $stream->on('close', [$this, 'onClose']);

                $stream->on('data', [$this, 'onData']);

                //  $ssl = new SSLRequest();
                //  $stream->write($ssl->encodedMessage());

                $startupParameters = $this->parameters;
                unset($startupParameters['host'], $startupParameters['port']);

                $startup = new StartupMessage();
                $startup->setParameters($startupParameters);
                $stream->write($startup->encodedMessage());
            },
            function ($e) {
                // connection error
                $this->failAllCommandsWith($e);
                $this->connStatus = static::CONNECTION_BAD;
                $this->emit('error', [$e]);
            }
        );
    }

    public function getState()
    {
        return $this->queryState;
    }

    public function getBacklogLength() : int
    {
        return array_reduce(
            $this->commandQueue,
            function ($a, CommandInterface $command) {
                if ($command instanceof Query || $command instanceof Sync) {
                    $a++;
                }
                return $a;
            },
            0);
    }

    public function onData($data)
    {
        while (strlen($data) > 0) {
            $data = $this->processData($data);
        }

        // We should only cancel if we have drained the input buffer (as much as we can see)
        // and there is still a pending query that needs to be canceled
        if ($this->cancelRequested) {
            $this->cancelRequest();
        }
    }

    private function processData($data)
    {
        if ($this->currentMessage) {
            $overflow = $this->currentMessage->parseData($data);
            // json_encode can slow things down here
            //$this->debug("onData: " . json_encode($overflow) . "");
            if ($overflow === false) {
                // there was not enough data to complete the message
                // leave this as the currentParser
                return '';
            }

            $this->handleMessage($this->currentMessage);

            $this->currentMessage = null;

            return $overflow;
        }

        if (strlen($data) == 0) {
            return '';
        }

        $type = $data[0];

        $message = Message::createMessageFromIdentifier($type);
        if ($message !== false) {
            $this->currentMessage = $message;
            return $data;
        }

//        if (in_array($type, ['R', 'S', 'D', 'K', '2', '3', 'C', 'd', 'c', 'G', 'H', 'W', 'D', 'I', 'E', 'V', 'n', 'N', 'A', 't', '1', 's', 'Z', 'T'])) {
//            $this->currentParser = [$this, 'parse1PlusLenMessage'];
//            call_user_func($this->currentParser, $data);
//        } else {
//            echo "Unhandled message \"".$type."\"";
//        }
    }

    public function onClose()
    {
        $this->connStatus = static::CONNECTION_CLOSED;
        $this->emit('close');
    }

    public function getConnectionStatus()
    {
        return $this->connStatus;
    }

    public function handleMessage($message)
    {
        $this->debug('Handling ' . get_class($message));
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
        } elseif ($message instanceof NotificationResponse) {
            $this->handleNotificationResponse($message);
        }
    }

    private function handleNotificationResponse(NotificationResponse $message)
    {
        $this->notificationSubject->onNext($message);
    }

    private function handleDataRow(DataRow $dataRow)
    {
        if ($this->queryState === $this::STATE_BUSY && $this->currentCommand instanceof CommandInterface) {
            if (count($dataRow->getColumnValues()) !== count($this->columnNames)) {
                throw new \Exception('Expected ' . count($this->columnNames) . ' data values got ' . count($dataRow->getColumnValues()));
            }
            $row = array_combine($this->columnNames, $dataRow->getColumnValues());

            // this should be broken out into a "data-mapper" type thing
            // where objects can be added to allow formatting data as it is
            // processed according to the type
            foreach ($this->columns as $column) {
                if ($column->typeOid === 16) { // bool
                    if ($row[$column->name] === null) {
                        continue;
                    }
                    if ($row[$column->name] === 'f') {
                        $row[$column->name] = false;
                        continue;
                    }

                    $row[$column->name] = true;
                }
            }

            $this->currentCommand->next($row);
        }
    }

    private function handleAuthentication(Authentication $message)
    {
        $this->lastError = 'Unhandled authentication message: ' . $message->getAuthCode();
        if ($message->getAuthCode() === $message::AUTH_CLEARTEXT_PASSWORD ||
            $message->getAuthCode() === $message::AUTH_MD5_PASSWORD
        ) {
            if ($this->password === null) {
                $this->lastError = 'Server asked for password, but none was configured.';
            } else {
                $passwordToSend = $this->password;
                if ($message->getAuthCode() === $message::AUTH_MD5_PASSWORD) {
                    $salt           = $message->getSalt();
                    $passwordToSend = 'md5' .
                        md5(md5($this->password . $this->parameters['user']) . $salt);
                }
                $passwordMessage = new PasswordMessage($passwordToSend);
                $this->stream->write($passwordMessage->encodedMessage());

                return;
            }
        }
        if ($message->getAuthCode() === $message::AUTH_OK) {
            $this->connStatus = $this::CONNECTION_AUTH_OK;

            return;
        }

        $this->connStatus = $this::CONNECTION_BAD;
        $this->failAllCommandsWith(new \Exception($this->lastError));
        $this->emit('error', [new \Exception($this->lastError)]);
        $this->disconnect();
    }

    private function handleBackendKeyData(BackendKeyData $message)
    {
        $this->backendKeyData = $message;
    }

    private function handleCommandComplete(CommandComplete $message)
    {
        if ($this->currentCommand instanceof CommandInterface) {
            $command = $this->currentCommand;
            $this->currentCommand = null;
            $command->complete();

            // if we have requested a cancel for this query
            // but we have received the command complete before we
            // had a chance to start canceling - then never mind
            $this->cancelRequested = false;
        }
        $this->debug('Command complete.');
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
        $this->lastError = $message;
        if ($message->getSeverity() === 'FATAL') {
            $this->connStatus = $this::CONNECTION_BAD;
            // notify any waiting commands
            $this->processQueue();
        }
        if ($this->connStatus === $this::CONNECTION_MADE) {
            $this->connStatus = $this::CONNECTION_BAD;
            // notify any waiting commands
            $this->processQueue();
        }
        if ($this->currentCommand !== null) {
            $extraInfo = null;
            if ($this->currentCommand instanceof Sync) {
                $extraInfo = [
                    'query_string' => $this->currentCommand->getDescription()
                ];
            } elseif ($this->currentCommand instanceof Query) {
                $extraInfo = [
                    'query_string' => $this->currentCommand->getQueryString()
                ];
            }
            $this->currentCommand->error(new ErrorException($message, $extraInfo));
            $this->currentCommand = null;
        }
    }

    private function handleNoticeResponse(NoticeResponse $message)
    {
    }

    private function handleParameterStatus(ParameterStatus $message)
    {
        $this->debug($message->getParameterName() . ': ' . $message->getParameterValue());
    }

    private function handleParseComplete(ParseComplete $message)
    {
    }

    private function handleReadyForQuery(ReadyForQuery $message)
    {
        $this->connStatus     = $this::CONNECTION_OK;
        $this->queryState     = $this::STATE_READY;
        $this->currentCommand = null;
        $this->processQueue();
    }

    private function handleRowDescription(RowDescription $message)
    {
        $this->addColumns($message->getColumns());
    }

    private function failAllCommandsWith(\Throwable $e = null)
    {
        $e = $e ?: new \Exception('unknown error');

        $this->notificationSubject->onError($e);

        while (count($this->commandQueue) > 0) {
            $c = array_shift($this->commandQueue);
            if ($c instanceof CommandInterface) {
                $c->error($e);
            }
        }
    }

    public function processQueue()
    {
        if ($this->cancelPending) {
            $this->debug("Not processing queue because there is a cancellation pending.");
            return;
        }

        if (count($this->commandQueue) === 0 && $this->queryState === static::STATE_READY && $this->auto_disconnect) {
            $this->commandQueue[] = new Terminate();
        }

        if (count($this->commandQueue) === 0) {
            return;
        }

        if ($this->connStatus === $this::CONNECTION_BAD) {
            $this->failAllCommandsWith(new \Exception('Bad connection: ' . $this->lastError));
            if ($this->stream) {
                $this->stream->end();
                $this->stream = null;
            }
            return;
        }

        while (count($this->commandQueue) > 0 && $this->queryState === static::STATE_READY) {
            /** @var CommandInterface $c */
            $c = array_shift($this->commandQueue);
            if (!$c->isActive()) {
                continue;
            }
            $this->debug('Sending ' . get_class($c));
            if ($c instanceof Query) {
                $this->debug('Sending simple query: ' . $c->getQueryString());
            }
            $this->stream->write($c->encodedMessage());
            if ($c instanceof Terminate) {
                $this->stream->end();
            }
            if ($c->shouldWaitForComplete()) {
                $this->queryState = $this::STATE_BUSY;
                if ($c instanceof Query) {
                    $this->queryType = $this::QUERY_SIMPLE;
                } elseif ($c instanceof Sync) {
                    $this->queryType = $this::QUERY_EXTENDED;
                }

                $this->currentCommand = $c;

                return;
            }
        }
    }

    public function query($query): Observable
    {
        return new AnonymousObservable(
            function (ObserverInterface $observer, SchedulerInterface $scheduler = null) use ($query) {
                if ($this->connStatus === $this::CONNECTION_NEEDED) {
                    $this->start();
                }
                if ($this->connStatus === $this::CONNECTION_BAD) {
                    $observer->onError(new \Exception('Connection failed'));
                    return new EmptyDisposable();
                }

                $q = new Query($query, $observer);
                $this->commandQueue[] = $q;

                $this->processQueue();

                return new CallbackDisposable(function () use ($q) {
                        if ($this->currentCommand === $q && $q->isActive()) {
                            $this->cancelRequested = true;
                        }
                        $q->cancel();
                    });
            }
        );

    }

    public function executeStatement(string $queryString, array $parameters = []): Observable
    {
        /**
         * http://git.postgresql.org/gitweb/?p=postgresql.git;a=blob;f=src/interfaces/libpq/fe-exec.c;h=828f18e1110119efc3bf99ecf16d98ce306458ea;hb=6bcce25801c3fcb219e0d92198889ec88c74e2ff#l1381
         *
         * Should make this return a Statement object
         *
         * To use prepared statements, looks like we need to:
         * - Parse (if needed?) (P)
         * - Bind (B)
         *   - Parameter Stuff
         * - Describe portal (D)
         * - Execute (E)
         * - Sync (S)
         *
         * Expect back
         * - Parse Complete (1)
         * - Bind Complete (2)
         * - Row Description (T)
         * - Row Data (D) 0..n
         * - Command Complete (C)
         * - Ready for Query (Z)
         */

        return new AnonymousObservable(
            function (ObserverInterface $observer, SchedulerInterface $scheduler = null) use ($queryString, $parameters) {
                if ($this->connStatus === $this::CONNECTION_NEEDED) {
                    $this->start();
                }
                if ($this->connStatus === $this::CONNECTION_BAD) {
                    $observer->onError(new \Exception('Connetion failed'));
                    return new EmptyDisposable();
                }

                $name = 'somestatement';

                /** @var CommandInterface[] $commandGroup */
                $commandGroup = [];
                $close = new Close($name);
                $commandGroup[] = $close;

                $prepare = new Parse($name, $queryString);
                $commandGroup[] = $prepare;

                $bind = new Bind($parameters, $name);
                $commandGroup[] = $bind;

                $describe = new Describe();
                $commandGroup[] = $describe;

                $execute = new Execute();
                $commandGroup[] = $execute;

                $sync = new Sync($queryString, $observer);
                $commandGroup[] = $sync;

                $this->commandQueue = array_merge($this->commandQueue, $commandGroup);

                $this->processQueue();

                return new CallbackDisposable(function () use ($sync, $commandGroup) {
                    if ($this->currentCommand === $sync && $sync->isActive()) {
                        $this->cancelRequested = true;
                        $sync->cancel();

                        // no point in canceling the other commands because they are out the door
                        return;
                    }
                    foreach ($commandGroup as $command) {
                        $command->cancel();
                    }
                });
            }
        );
    }

    /**
     * Add Column information (from T)
     *
     * @param $columns
     */
    private function addColumns($columns)
    {
        $this->columns     = $columns;
        $this->columnNames = array_map(function ($column) {
            return $column->name;
        }, $this->columns);
    }

    private function debug($string)
    {
        //echo "DEBUG: " . $string . "\n";
    }

    /**
     * https://www.postgresql.org/docs/9.2/static/protocol-flow.html#AEN95792
     */
    public function disconnect()
    {
        $this->commandQueue[] = new Terminate();
        $this->processQueue();
    }

    private function cancelRequest()
    {
        $this->cancelRequested = false;
        if ($this->queryState !== self::STATE_BUSY) {
            $this->debug("Not canceling because there is nothing to cancel.");
            return;
        }
        if ($this->currentCommand !== null) {
            $this->cancelPending = true;
            $this->socket->connect($this->uri)->then(function (DuplexStreamInterface $conn) {
                $cancelRequest = new CancelRequest($this->backendKeyData->getPid(), $this->backendKeyData->getKey());
                $conn->on('close', function () {
                    $this->cancelPending = false;
                    $this->processQueue();
                });
                $conn->end($cancelRequest->encodedMessage());
            }, function (\Throwable $e) {
                $this->cancelPending = false;
                $this->processQueue();
                $this->debug("Error connecting for cancellation... " . $e->getMessage() . "\n");
            });
        }
    }

    public function notifications() {
        return $this->notificationSubject->asObservable();
    }
}
