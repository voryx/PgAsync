<?php

namespace PgAsync\Command;

use PgAsync\Message\Message;

class PasswordMessage implements CommandInterface
{
    use CommandTrait;

    private $password;

    public function __construct(string $password)
    {
        $this->password = $password;
    }

    public function encodedMessage(): string
    {

        return 'p' . Message::prependLengthInt32($this->password . "\x00");
    }

    public function shouldWaitForComplete(): bool
    {
        return false;
    }
}
