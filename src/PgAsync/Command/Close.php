<?php

namespace PgAsync\Command;

use PgAsync\Message\Message;

class Close implements CommandInterface
{
    use CommandTrait;

    private $statementName = "";

    /**
     * @param string $statementName
     */
    public function __construct($statementName = "")
    {
        $this->statementName = $statementName;
    }

    public function encodedMessage()
    {
        return "C" . Message::prependLengthInt32("S" . $this->statementName . "\0");
    }

    public function shouldWaitForComplete()
    {
        return false;
    }
}
