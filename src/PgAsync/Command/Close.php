<?php

namespace PgAsync\Command;

use PgAsync\Message\Message;

class Close implements CommandInterface
{
    use CommandTrait;

    private $statementName;

    public function __construct(string $statementName = "")
    {
        $this->statementName = $statementName;
    }

    public function encodedMessage(): string
    {
        return 'C' . Message::prependLengthInt32('S' . $this->statementName . "\0");
    }

    public function shouldWaitForComplete(): bool
    {
        return false;
    }
}
