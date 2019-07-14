<?php

use PgAsync\Client;
use PgAsync\Tests\TestCase;
use React\Dns\Query\ExecutorInterface;
use React\Dns\Resolver\Resolver;
use React\Promise\Deferred;
use React\Promise\RejectedPromise;
use React\Socket\Connector;

class ClientTest extends TestCase
{
    public function testFailedDNSLookup()
    {
        $executor = $this->getMockBuilder(ExecutorInterface::class)
            ->setMethods(['query'])
            ->getMock();

        $deferred = new Deferred();

        $executor
            ->expects($this->once())
            ->method('query')
            ->willReturn($deferred->promise());

        $resolver = new Resolver($executor);

        $conn = new Client([
            "database" => $this->getDbName(),
            "user" => $this->getDbUser(),
            "host" => 'somenonexistenthost.'
        ], $this->getLoop(), new Connector($this->getLoop(), ['dns' => $resolver]));

        $exception = null;

        $conn->query("SELECT now() as something")
            ->subscribe(
                null,
                function (Exception $e) use (&$exception) {
                    $exception = $e;
                    $this->cancelCurrentTimeoutTimer();
                }
            );

        $this->getLoop()->addTimer(0.01, function () use ($deferred) {
            $deferred->reject(new React\Dns\RecordNotFoundException());
        });

        $this->runLoopWithTimeout(5);

        $this->assertInstanceOf(Exception::class, $exception);
    }

    public function testFailedDNSLookupEarlyRejection()
    {
        $executor = $this->getMockBuilder(ExecutorInterface::class)
            ->setMethods(['query'])
            ->getMock();

        $executor
            ->expects($this->once())
            ->method('query')
            ->willReturn(new RejectedPromise(new React\Dns\RecordNotFoundException()));

        $resolver = new Resolver($executor);

        $conn = new Client([
            "database" => $this->getDbName(),
            "user" => $this->getDbUser(),
            "host" => 'somenonexistenthost.'
        ], $this->getLoop(), new Connector($this->getLoop(), ['dns' => $resolver]));

        $exception = null;

        $conn->query("SELECT now() as something")
            ->subscribe(
                null,
                function (Exception $e) use (&$exception) {
                    $exception = $e;
                }
            );

        $this->assertInstanceOf(Exception::class, $exception);
    }
}