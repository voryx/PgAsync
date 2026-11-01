<?php

namespace PgAsync\Tests;

use EventLoop\EventLoop;
use PgAsync\Client;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\Timer;
use PHPUnit\Framework\TestCase as BaseTestCase;
use React\Socket\ConnectorInterface;

class TestCase extends BaseTestCase
{
    const DBNAME = 'pgasync_test';

    /** @var LoopInterface */
    public static $loop;

    /** @var Timer */
    public static $timeoutTimer;

    public static $dbUser = 'pgasync';

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

    public static function clientFromEnv(array $parameters = [], ?LoopInterface $loop = null, ?ConnectorInterface $connector = null): Client
    {
        $dsn = getenv('TEST_POSTGRES_DSN');
        if (is_string($dsn)) {
            $parts = parse_url($dsn);
            if (is_array($parts)) {
                if (array_key_exists('host', $parts) && !array_key_exists('host', $parameters)) {
                    $parameters['host'] = $parts['host'];
                }
                if (array_key_exists('user', $parts) && !array_key_exists('user', $parameters)) {
                    $parameters['user'] = $parts['user'];
                }
                if (array_key_exists('port', $parts) && !array_key_exists('port', $parameters)) {
                    $parameters['port'] = $parts['port'];
                }
                if (array_key_exists('pass', $parts) && !array_key_exists('password', $parameters)) {
                    $parameters['password'] = $parts['pass'];
                }
                if (array_key_exists('path', $parts) && !array_key_exists('database', $parameters)) {
                    $parameters['database'] = trim($parts['path'], '/');
                }
                if (array_key_exists('query', $parts)) {
                    parse_str($parts['query'], $query);
                    if (!array_key_exists('tls', $parameters)) {
                        if (array_key_exists('tlsmode', $query)) {
                            $parameters['tls'] = $query['tlsmode'];
                        }
                    }
                    if (!array_key_exists('tls_connector_flags', $parameters)) {
                        if (array_key_exists('tlsservercert', $query)) {
                            $parameters['tls_connector_flags'] = [
                                'cafile' => $query['tlsservercert'],
                            ];
                        }
                    }
                }
            }
        }

        return new Client($parameters, $loop, $connector);
    }
}
