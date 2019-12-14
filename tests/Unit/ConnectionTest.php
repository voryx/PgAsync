<?php

use PgAsync\Connection;
use PgAsync\Tests\TestCase;

class ConnectionTest extends TestCase
{
    public function testInvalidParametersThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        $conn = new Connection(['something' => ''], $this->getLoop());
    }

    public function testNoUserThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        $conn = new Connection(["database" => "some_database"], $this->getLoop());
    }

    public function testNoDatabaseThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        $conn = new Connection(["user" => "some_user"], $this->getLoop());
    }
}
