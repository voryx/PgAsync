<?php

use PgAsync\Connection;
use PgAsync\Tests\TestCase;

class ConnectionTest extends TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidParametersThrows()
    {
        $conn = new Connection(['something' => ''], $this->getLoop());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testNoUserThrows()
    {
        $conn = new Connection(["database" => "some_database"], $this->getLoop());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testNoDatabaseThrows()
    {
        $conn = new Connection(["user" => "some_user"], $this->getLoop());
    }
}
