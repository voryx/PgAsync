<?php


namespace PgAsync\Message;


class Execute implements CommandInterface
{
    use CommandTrait;

    private $portalName = "";

    /**
     * Execute constructor.
     * @param string $portalName
     */
    public function __construct($portalName = "")
    {
        $this->portalName = $portalName;
    }

    public function encodedMessage()
    {
        return "E" . Message::prependLengthInt32($this->portalName . "\0"
            . Message::int32(0)); // max rows - 0 is unlimited;
    }

    public function shouldWaitForComplete() {
        return false;
    }
}