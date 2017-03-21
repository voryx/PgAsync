<?php

namespace PgAsync\Command;

use PgAsync\Message\Message;
use Rx\Subject\Subject;

class Parse implements CommandInterface
{
    use CommandTrait;
    /**
     * Name of the prepared statement
     */
    public $name;

    public $queryString;

    public function __construct(string $name, string $queryString)
    {
        $this->name        = $name;
        $this->queryString = $queryString;
        $this->subject     = new Subject();
    }

    // there is mechanisms to pre-describe types - we aren't getting into that

    public function encodedMessage(): string
    {
        return 'P' . Message::prependLengthInt32(
                $this->name . "\0" .
                $this->queryString . "\0" .
                "\0\0"

            );
    }

    public function shouldWaitForComplete(): bool
    {
        return false;
    }
}
