<?php

namespace PgAsync\Command;

class Sync implements CommandInterface
{
    use CommandTrait;

    private $description;

    /**
     * Sync constructor.
     * @param string $description
     */
    public function __construct($description = "")
    {
        $this->description = $description;
        $this->getSubject();
    }

    public function encodedMessage()
    {
        return "S\0\0\0\x04";
    }

    public function shouldWaitForComplete()
    {
        return true;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }
}
