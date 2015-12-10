<?php

namespace PgAsync\Tests\Integration;

use PgAsync\Client;
use Rx\Observer\CallbackObserver;

class NullPasswordTest extends TestCase
{
    public function testNullPassword()
    {
        $client = new Client([
            "user"     => $this::getDbUser(),
            "database" => $this::getDbName(),
            "password" => null
        ]);

        $count = $client->query("SELECT count(*) AS the_count FROM thing");

        $theCount = -1;

        $count->subscribe(new CallbackObserver(
            function ($x) use (&$theCount) {
                $this->assertTrue($theCount == -1);
                $theCount = $x["the_count"];
            },
            function ($e) use ($client) {
                $client->closeNow();
                $this->cancelCurrentTimeoutTimer();
                $this->fail("onError");
            },
            function () use ($client) {
                $client->closeNow();
                $this->cancelCurrentTimeoutTimer();
            }
        ));

        $this->runLoopWithTimeout(2);

        $this->assertEquals(3, $theCount);
    }
}
