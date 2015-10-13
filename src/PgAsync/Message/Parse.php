<?php


namespace PgAsync\Message;

use Rx\Subject\Subject;

class Parse extends Message implements CommandInterface
{
    use CommandTrait;
    /**
     * Name of the prepared statement
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $queryString;

    function __construct($name, $queryString)
    {
        $this->name        = $name;
        $this->queryString = $queryString;
        $this->subject    = new Subject();
    }

    // there is mechanisms to pre-describe types - we aren't getting into that

    public function encodedMessage()
    {
        return "P" . Message::prependLengthInt32(
            $this->name . "\0" .
            $this->queryString . "\0" .
            "\0\0"

        );
    }

    public function shouldWaitForComplete() {
        return false;
    }
}