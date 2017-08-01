<?php

namespace PgAsync\Command;

use PgAsync\Message\Message;

class Execute implements CommandInterface
{
    use CommandTrait;

    private $portalName;

    public function __construct(string $portalName = "")
    {
        $this->portalName = $portalName;
    }

    public function encodedMessage(): string
    {
        return 'E' . Message::prependLengthInt32($this->portalName . "\0"
                . Message::int32(0)); // max rows - 0 is unlimited;
    }

    public function shouldWaitForComplete(): bool
    {
        return false;
    }
}
