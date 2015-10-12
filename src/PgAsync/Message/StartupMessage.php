<?php


namespace PgAsync\Message;


class StartupMessage {
    /**
     * @var int
     *
     * Int32
     */
    private $messageLength;

    /**
     * @var int
     *
     * Int32
     */
    private $protocolVersion = 196608;

    /**
     * @var array
     *
     * string pairs
     */
    private $parameters = [];

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @param array $parameters
     */
    public function setParameters($parameters)
    {
        $this->parameters = $parameters;
    }

    public function encodedMessage() {
        $msg = "";

        $msg .= Message::int32($this->protocolVersion);

        foreach ($this->parameters as $k => $v) {
            $msg .= $k . "\0" . $v . "\0";
        }

        $msg .= "\0";

        return Message::prependLengthInt32($msg);
    }
} 