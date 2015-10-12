<?php


namespace PgAsync;


use Evenement\EventEmitter;
use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use PgAsync\Message\CommandInterface;
use PgAsync\Message\Describe;
use PgAsync\Message\Parse;
use PgAsync\Message\Query;
use PgAsync\Message\SSLRequest;
use PgAsync\Message\StartupMessage;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\SocketClient\Connector;
use React\Stream\Stream;
use SebastianBergmann\Exporter\Exception;

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
    /** @var Connector */
    protected $socket;

    /**
     * This can be "CLOSED" "STARTUP" "READY"
     *
     * @var string
     */
    protected $protoState = "CLOSED";

    /**
     *
     * this is the current message parser
     *
     * @var callable
     */
    protected $currentParser;

    protected $currentMsg = "";
    protected $msgLen = 0;
    protected $params = [];

    /** @var  Stream */
    protected $stream;

    /** @var bool  */
    protected $readyForQuery = false;

    /** @var  \SplQueue */
    protected $commandQueue;

    /** @var  CommandInterface */
    protected $currentCommand;

    /**
     * Can be 'I' for Idle, 'T' if in transactions block
     * or 'E' if in failed transaction block (queries will fail until end of trans)
     *
     * @var string
     */
    protected $backendTransactionStatus = "UNKNOWN";

    /**
     * @param $connectString
     * @param $loop
     */
    function __construct($connectString, $loop)
    {
        $this->connectString = $connectString;
        $this->loop          = $loop;
        $this->commandQueue    = new \SplQueue();
    }

    public function connect($parameters = null) {
        if (!is_array($parameters) ||
            !isset($parameters['user']) ||
            !isset($parameters['database'])
        ) {
            throw new \InvalidArgumentException("Parameters must be an associative array with at least 'database' and 'user' set.");
        }

        $dnsResolverFactory = new \React\Dns\Resolver\Factory();
        $dns = $dnsResolverFactory->createCached('8.8.8.8', $this->loop);
        $this->socket = new Connector($this->loop,$dns);

        $deferred = new Deferred();

        $this->socket->create('127.0.0.1', 5432)->then(function (Stream $stream) use ($deferred, $parameters) {
            $this->stream = $stream;

            $this->protoState = "STARTUP";

            $stream->on('data', [$this, 'onData']);

//            $ssl = new SSLRequest();
//            $stream->write($ssl->encodedMessage());

            $startup = new StartupMessage();
            $startup->setParameters($parameters);
            $stream->write($startup->encodedMessage());

            //$stream->on('drain', function () use ($stream) {

            //});

            $deferred->resolve();
        },function () use ($deferred) {
            $deferred->reject();
        });

        return $deferred->promise();
    }

    public function query($s) {
        $q = new Query($s);
        $this->commandQueue->enqueue($q);

        $this->processQueue();
        return $q->getSubject();
    }

    public function processQueue() {
        if ($this->commandQueue->count() > 0 && $this->readyForQuery) {
            /** @var Query $q */
            $q = $this->commandQueue->dequeue();
            $this->stream->write($q->encodedMessage());
            $this->currentCommand = $q;
        }
    }

    public function onData($data) {
        if ($this->currentParser) {
            call_user_func($this->currentParser, $data);
            return;
        }

        if (strlen($data) == 0) {
            return;
        }

        $this->currentMsg = "";
        $this->msgLen = 0;

        $type = $data[0];

        if (in_array($type, ['R','S','D','K','2','3','C','d','c','G','H','W','D','I','E','V','n','N','A','t','1','s','Z','T'])) {
            $this->currentParser = [$this, 'parse1PlusLenMessage'];
            call_user_func($this->currentParser, $data);
        } else {
            echo "Unhandled message \"" . $type . "\"";
        }

        //echo $data;
    }

    public function parse1PlusLenMessage($data) {
        $this->currentMsg .= $data;

        $len = strlen($this->currentMsg);
        if ($len >= 5) {
            $this->msgLen = unpack("N", substr($this->currentMsg, 1, 4))[1];
            if ($this->msgLen > 0 && $len >= $this->msgLen) {
                $theMessage = substr($this->currentMsg, 0, $this->msgLen + 1);
                switch ($theMessage[0]) {
                    case "1": // parse complete
                        $this->parse1($theMessage);
                        break;
                    case "C":
                        $this->parseC($theMessage);
                        break;
                    case "D":
                        $this->parseD($theMessage);
                        break;
                    case "E":
                        $this->parseE($theMessage);
                        break;
                    case "R":
                        $this->parseR($theMessage);
                        break;
                    case "S":
                        $this->parseS($theMessage);
                        break;
                    case "T":
                        $this->parseT($theMessage);
                        break;
                    case "Z":
                        $this->parseZ($theMessage);
                }

                $this->currentParser = null;
                if ($len > $this->msgLen) {
                    $this->onData(substr($this->currentMsg, $this->msgLen + 1));
                }
            }
        }
    }

    public function parse1($data) {
        $this->currentCommand->complete();
    }

    /**
     * Command Complete
     *
     * @param $data
     */
    public function parseC($data) {
        $completeTag = substr($data, 5);
        $parts = explode(" ", $completeTag);
        if (isset($parts[0])) {
            switch ($parts[0]) {
                case "INSERT":
                    echo $parts[1] . " inserted.";
                    if ($parts[1] == 1 && $parts[2] != 0) {
                        echo " (oid " . $parts[2] . ")";
                    }
                    echo "\n";
                    break;
                case "DELETE":
                    echo $parts[1] . " deleted.\n";
                    break;
                case "UPDATE":
                    echo $parts[1] . " updated.\n";
                    break;
                case "SELECT":
                    echo $parts[1] . " returned.\n";
                    break;
                case "MOVE":
                    echo $parts[1] . " moved.\n";
                    break;
                case "FETCH":
                    echo $parts[1] . " returned.\n";
                    break;
                case "COPY":
                    echo $parts[1] . " copied.\n";
                    break;
            }
        }

        $this->currentCommand->complete();
    }

    public function parseE($data) {
        $rawMsgs = substr($data, 5);
        $parts = explode("\0", $rawMsgs);

        foreach ($parts as $part) {
            if (strlen($part) < 2) break;
            $fieldType = $part[0];

            switch ($fieldType) {
                case 'S'://Severity: the field contents are ERROR, FATAL, or PANIC (in an error message), or WARNING, NOTICE, DEBUG, INFO, or LOG (in a notice message), or a localized translation of one of these. Always present.
                case 'C'://Code: the SQLSTATE code for the error (see Appendix A). Not localizable. Always present.
                case 'M'://Message: the primary human-readable error message. This should be accurate but terse (typically one line). Always present.
                case 'D'://Detail: an optional secondary error message carrying more detail about the problem. Might run to multiple lines.
                case 'H'://Hint: an optional suggestion what to do about the problem. This is intended to differ from Detail in that it offers advice (potentially inappropriate) rather than hard facts. Might run to multiple lines.
                case 'P'://Position: the field value is a decimal ASCII integer, indicating an error cursor position as an index into the original query string. The first character has index 1, and positions are measured in characters not bytes.
                case 'p'://Internal position: this is defined the same as the P field, but it is used when the cursor position refers to an internally generated command rather than the one submitted by the client. The q field will always appear when this field appears.
                case 'q'://Internal query: the text of a failed internally-generated command. This could be, for example, a SQL query issued by a PL/pgSQL function.
                case 'W'://Where: an indication of the context in which the error occurred. Presently this includes a call stack traceback of active procedural language functions and internally-generated queries. The trace is one entry per line, most recent first.
                case 'F'://File: the file name of the source-code location where the error was reported.
                case 'L'://Line: the line number of the source-code location where the error was reported.
                case 'R'://Routine: the name of the source-code routine reporting the error.
            }

            $msg = substr($part, 1);
            echo "Error: $msg\n";
        }

        if ($this->currentCommand) {
            $this->currentCommand->error();
            $this->currentCommand = null;
        }
    }

    /**
     * Identifies the message as a response to an empty query string. (This substitutes for CommandComplete.)
     *
     * @param $data
     */
    public function parseI($data) {
        if ($this->currentCommand) {
            $this->currentCommand->complete();
            $this->currentCommand = null;
        }
    }

    public function parseR($data) {
        $authCode = unpack("N", substr($data, 5, 4))[1];
        echo "authCode is " . $authCode . "\n";
    }

    public function parseS($data)
    {
        $payload = substr($this->currentMsg, 5, $this->msgLen - 5);

        $paramParts = explode("\0", $payload);
        $param = [$paramParts[0] => $paramParts[1]];

        $this->params[] = $param;
    }

    public function parseZ($data) {
        if ($this->msgLen != 5) throw new \Exception;

        $this->backendTransactionStatus = $data[5];
        if (!in_array($this->backendTransactionStatus, ['I','T','E'])) throw new \Exception;

        $this->setReadyForQuery(true);

        $this->emit('ready_for_query', [$this->backendTransactionStatus]);
    }

    /**
     * Row Description
     *
     * @param $data
     */
    public function parseT($data) {
        $len = strlen($data);
        if ($len < 7) throw new \UnderflowException;

        $columnCount = unpack("n", substr($data, 5, 2))[1];
        $columnStart = 7;
        $columns = [];
        for ($i = 0; $i < $columnCount; $i++) {
            $column = new Column();

            $strEnd = strpos($data, "\0", $columnStart);
            if ($strEnd === false) {
                throw new \InvalidArgumentException;
            }

            $column->name = substr($data, $columnStart, $strEnd - $columnStart);
            $pos = $strEnd + 1;
            $column->tableOid = unpack("N", substr($data, $pos, 4))[1];
            $pos += 4;
            $column->attrNo = unpack("n", substr($data, $pos, 2))[1];
            $pos += 2;
            $column->typeOid = unpack("N", substr($data, $pos, 4))[1];
            $pos += 4;
            $column->dataSize = unpack("n", substr($data, $pos, 2))[1];
            $pos += 2;
            $column->typeModifier = unpack("N", substr($data, $pos, 4))[1];
            $pos += 4;
            $column->formatCode = unpack("n", substr($data, $pos, 2))[1];
            $pos += 2;
            $columns[] = $column;
            $columnStart = $pos;
        }

        $this->currentCommand->addColumns($columns);
    }

    public function parseD($data) {
        $len = strlen($data);
        if ($len < 8) {
            throw new \UnderflowException;
        }

        $columnCount = unpack("n", substr($data, 5, 2))[1];
        $columnValues = [];
        $columnStart = 7;
        for ($i = 0; $i < $columnCount; $i++) {
            if ($len < $columnStart + 4) throw new \UnderflowException;
            $columnLen = unpack("N", substr($data, $columnStart, 4))[1];
            if ($columnLen == 4294967295) {
                $columnLen = 0;
                $columnValues[] = null;
            } else {
                if ($len < $columnStart + 4 + $columnLen) throw new \UnderflowException;
                $columnValues[] = substr($data, $columnStart + 4, $columnLen);
            }
            $columnStart += 4 + $columnLen;


        }

        if ($this->currentCommand) {
            $this->currentCommand->addRow($columnValues);
        }

        if ($len != $columnStart) {
            echo "Warning, there was some straggling info in the data row...";
        }
    }

    /**
     * @return boolean
     */
    public function isReadyForQuery()
    {
        return $this->readyForQuery;
    }

    /**
     * @param boolean $readyForQuery
     */
    public function setReadyForQuery($readyForQuery)
    {
        $this->readyForQuery = $readyForQuery;
        $this->processQueue();
    }

    public function prepare($queryString, $name = '') {
        $prepare = new Parse($name, $queryString);
        $this->commandQueue->enqueue($prepare);
        $this->processQueue();
        return $prepare->getSubject();
    }

    public function describePreparedStatement($name = '') {
        $describe = new Describe($name);
        $this->commandQueue->enqueue($describe);
        $this->processQueue();
        return $describe->getSubject();
    }
}