<?php

namespace PgAsync\Tests;

use EventLoop\EventLoop;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\Timer;
use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    const DBNAME = 'pgasync_test';

    /** @var LoopInterface */
    public static $loop;

    /** @var Timer */
    public static $timeoutTimer;

    public static $dbUser = "";

    public static function getLoop()
    {
        if (static::$loop === null) {
            static::$loop = EventLoop::getLoop();
        }

        return static::$loop;
    }

    public static function stopLoop()
    {
        static::getLoop()->addTimer(0.1, function () {
            static::getLoop()->stop();
        });
    }

    public static function cancelCurrentTimeoutTimer()
    {
        if (static::$timeoutTimer !== null) {
            static::getLoop()->cancelTimer(static::$timeoutTimer);
            static::$timeoutTimer = null;
        }
    }

    public static function runLoopWithTimeout($seconds)
    {
        $loop = static::getLoop();

        static::cancelCurrentTimeoutTimer();

        static::$timeoutTimer = $loop->addTimer($seconds, function ($timer) use ($seconds) {
            static::stopLoop();
            static::$timeoutTimer = null;

            throw new \Exception("Test timed out after " . $seconds . " seconds.");
        });

        $loop->run();

        static::cancelCurrentTimeoutTimer();
    }

    /**
     * @return string
     */
    public static function getDbUser()
    {
        return self::$dbUser;
    }

    /**
     * @param string $dbUser
     */
    public static function setDbUser($dbUser)
    {
        self::$dbUser = $dbUser;
    }

    public static function getDbName()
    {
        return self::DBNAME;
    }
}
