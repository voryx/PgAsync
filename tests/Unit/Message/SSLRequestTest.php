<?php

use PHPUnit\Framework\TestCase;

class SSLRequestTest extends TestCase
{
    public function test()
    {
        $ssl = new \PgAsync\Command\SSLRequest();
        $this->assertEquals("\x00\x00\x00\x08\x04\xd2\x16\x2f", $ssl->encodedMessage());
    }
}
