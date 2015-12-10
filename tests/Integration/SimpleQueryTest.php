<?php

namespace PgAsync\Tests\Integration;

use PgAsync\Client;
use Rx\Observer\CallbackObserver;

class SimpleQueryTest extends TestCase
{
    public function testSimpleQuery()
    {
        $client = new Client(["user" => $this::getDbUser(), "database" => $this::getDbName()]);

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

    public function testSimpleQueryNoResult()
    {
        $client = new Client(["user" => $this->getDbUser(), "database" => $this->getDbName()], $this->getLoop());

        $count = $client->query("SELECT count(*) AS the_count FROM thing WHERE thing_type = 'non-thing'");

        $theCount = -1;

        $count->subscribe(new CallbackObserver(
            function ($x) use (&$theCount) {
                $this->assertTrue($theCount == -1); // make sure we only run once
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

        $this->assertEquals(0, $theCount);
    }

    public function testSimpleQueryError()
    {
        $client = new Client(["user" => $this->getDbUser(), "database" => $this::getDbName()], $this->getLoop());

        $count = $client->query("SELECT count(*) abcdef AS the_count FROM thing WHERE thing_type = 'non-thing'");

        $theCount = -1;

        $count->subscribe(new CallbackObserver(
            function ($x) use ($client) {
                $client->closeNow();
                $this->cancelCurrentTimeoutTimer();
                $this->fail("Should not get result");
            },
            function ($e) use ($client) {
                $client->closeNow();
                $this->cancelCurrentTimeoutTimer();
            },
            function () use ($client) {
                $client->closeNow();
                $this->cancelCurrentTimeoutTimer();
                $this->fail("Should not complete");
            }
        ));

        $this->runLoopWithTimeout(2);

        $this->assertEquals(-1, $theCount);
    }
}
