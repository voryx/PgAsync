<?php


namespace PgAsync\Message;

use Rx\Subject\Subject;

class Describe implements CommandInterface {
    use CommandTrait;

    private $name;

    function __construct($name = "")
    {
        $this->name = $name;
        $this->subject = new Subject();
    }

    public function encodedMessage()
    {
        return 'D' . Message::prependLengthInt32("S$this->name\0");
    }
}