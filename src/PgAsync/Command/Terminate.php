<?php

namespace PgAsync\Command;

class Terminate implements CommandInterface
{
    use CommandTrait;

    public function encodedMessage(): string
    {
        return "X\0\0\0\x04";
    }

    public function shouldWaitForComplete(): bool
    {
        return false;
    }
}
