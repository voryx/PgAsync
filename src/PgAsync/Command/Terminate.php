<?php

namespace PgAsync\Command;

class Terminate implements CommandInterface
{
    use CommandTrait;

    /**
     * Terminate constructor.
     */
    public function __construct()
    {
        $this->getSubject();
    }

    public function encodedMessage()
    {
        return "X\0\0\0\x04";
    }

    public function shouldWaitForComplete()
    {
        return false;
    }
}
