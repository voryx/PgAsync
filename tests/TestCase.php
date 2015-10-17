<?php


namespace PgAsync\Tests;


use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\Timer;

class TestCase extends \PHPUnit_Framework_TestCase
{
    const dbName = "pgasync_test";

    /** @var LoopInterface */
    static $loop;

    /** @var Timer */
    static $timeoutTimer;

    static $dbUser = "";

    public static function getLoop() {
        if (static::$loop === null) {
            static::$loop = Factory::create();
        }

        return static::$loop;
    }

    public static function stopLoop() {

        static::getLoop()->stop();
    }

    public static function cancelCurrentTimeoutTimer() {
        if (static::$timeoutTimer !== null) {
            static::$timeoutTimer->cancel();
            static::$timeoutTimer = null;
        }
    }

    public static function runLoopWithTimeout($seconds) {
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

    public static function getDbName() {
        return self::dbName;
    }
}