<?php

namespace PgAsync\Command;

use PgAsync\Message\Message;

class PasswordMessage implements CommandInterface
{
    use CommandTrait;

    private $password;

    /**
     * PasswordMessage constructor.
     */
    public function __construct($password)
    {
        $this->password = $password;
    }

    public function encodedMessage()
    {

        return "p" . Message::prependLengthInt32($this->password . "\x00");
    }

    public function shouldWaitForComplete()
    {
        return false;
    }
}
