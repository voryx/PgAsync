<?php

class MessageTest extends PHPUnit_Framework_TestCase
{
    public function testInt32()
    {
        $this->assertEquals("\x04\xd2\x16\x2f", \PgAsync\Message\Message::int32(80877103));
        $this->assertEquals("\x00\x00\x00\x00", \PgAsync\Message\Message::int32(0));
    }
}
