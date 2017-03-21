<?php

namespace PgAsync\Command;

use PgAsync\Message\Message;
use Rx\Subject\Subject;

class Query implements CommandInterface
{
    use CommandTrait;

    protected $queryString = "";

    public function __construct(string $queryString)
    {
        $this->queryString = $queryString;

        $this->subject = new Subject();
    }

    public function encodedMessage(): string
    {
        return 'Q' . Message::prependLengthInt32($this->queryString . "\0");
    }

    public function shouldWaitForComplete(): bool
    {
        return true;
    }

    public function getQueryString(): string
    {
        return $this->queryString;
    }
}
