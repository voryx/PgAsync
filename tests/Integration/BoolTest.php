<?php

namespace PgAsync\Tests\Integration;

use PgAsync\Client;
use Rx\Observer\CallbackObserver;

class BoolTest extends TestCase
{
    public function testBools() {

        $client = new Client(["user" => $this::getDbUser(), "database" => $this::getDbName()]);

        $count = $client->query("SELECT * FROM thing");

        $trueCount = 0;
        $falseCount = 0;
        $nullCount = 0;
        $completes = false;
        $count->subscribe(new CallbackObserver(
            function ($x) use (&$trueCount, &$falseCount, &$nullCount) {
                if ($x['thing_in_stock'] === true) {
                    $trueCount++;
                }
                if ($x['thing_in_stock'] === false) {
                    $falseCount++;
                }
                if ($x['thing_in_stock'] === null) {
                    $nullCount++;
                }
            },
            function ($e) use ($client) {
                $client->closeNow();
                $this->cancelCurrentTimeoutTimer();
                throw $e;
            },
            function () use (&$completes, $client) {
                $completes = true;
                $client->closeNow();
                $this->cancelCurrentTimeoutTimer();
            }
        ));

        $this->runLoopWithTimeout(2);

        $client->closeNow();

        $this->assertTrue($completes);
        $this->assertEquals(1, $trueCount);
        $this->assertEquals(1, $falseCount);
        $this->assertEquals(1, $nullCount);
    }

}