<?php

namespace PgAsync\Tests\Integration;

class TestCase extends \PgAsync\Tests\TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $loop = self::getLoop();
        for ($i = 0; $i < 3; $i++) {
            $timer = $loop->addTimer(0.05, function () use ($loop) { $loop->stop(); });
            $loop->run();
            $loop->cancelTimer($timer);
        }
    }
}