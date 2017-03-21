<?php

namespace PgAsync\Command;

use PgAsync\Message\Message;

class SSLRequest implements CommandInterface
{
    use CommandTrait;

    public function encodedMessage(): string
    {
        $msg = Message::int32(80877103);

        return Message::prependLengthInt32($msg);
    }

    public function shouldWaitForComplete(): bool
    {
        return false;
    }
}
