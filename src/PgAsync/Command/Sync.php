<?php


namespace PgAsync\Command;


class Sync implements CommandInterface
{
    use CommandTrait;


    /**
     * Sync constructor.
     */
    public function __construct()
    {
        $this->getSubject();
    }

    public function encodedMessage()
    {
        return "S\0\0\0\x04";
    }

    public function shouldWaitForComplete() {
        return true;
    }
}