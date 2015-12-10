<?php

class StartupMessageTest extends PHPUnit_Framework_TestCase
{
    public function test()
    {
        $m = new \PgAsync\Command\StartupMessage();

        $m->setParameters([
            "user"     => "zxcv",
            "database" => "asdf",
        ]);

        // len 24 + 4 byte proto ver
        $this->assertEquals("\x00\x00\x00\x21\x00\x03\x00\x00user\0zxcv\0database\0asdf\0\0", $m->encodedMessage());
    }
}
