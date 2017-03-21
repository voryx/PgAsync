<?php

namespace PgAsync\Command;

use PgAsync\Message\Message;

class StartupMessage implements CommandInterface
{
    use CommandTrait;

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
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @param array $parameters
     */
    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;
    }

    public function encodedMessage(): string
    {
        $msg = "";

        $msg .= Message::int32($this->protocolVersion);

        foreach ($this->parameters as $k => $v) {
            $msg .= $k . "\0" . $v . "\0";
        }

        $msg .= "\0";

        return Message::prependLengthInt32($msg);
    }

    public function shouldWaitForComplete(): bool
    {
        return false;
    }
}
