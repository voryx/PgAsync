<?php

use PHPUnit\Framework\TestCase;

class ParseTest extends TestCase
{
    /**
     * Still working on this
     */
    public function test()
    {
        $prepared = new \PgAsync\Command\Parse("Hello", "SELECT * FROM some_table WHERE id = $1");
        $this->assertEquals("P\00\00\00\x33Hello\0SELECT * FROM some_table WHERE id = $1\0\0\0", $prepared->encodedMessage());
    }
}
