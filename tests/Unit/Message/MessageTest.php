<?php

class MessageTest extends PHPUnit_Framework_TestCase
{
    public function testInt32()
    {
        $this->assertEquals("\x04\xd2\x16\x2f", \PgAsync\Message\Message::int32(80877103));
        $this->assertEquals("\x00\x00\x00\x00", \PgAsync\Message\Message::int32(0));
    }

    public function testNotificationResponse()
    {
        $rawNotificationMessage = hex2bin('41000000190000040c686572650048656c6c6f20746865726500');


        $notificationResponse = \PgAsync\Message\Message::createMessageFromIdentifier($rawNotificationMessage[0]);
        $this->assertInstanceOf(\PgAsync\Message\NotificationResponse::class, $notificationResponse);
        /** @var \PgAsync\Message\NotificationResponse */
        $notificationResponse->parseData($rawNotificationMessage);

        $this->assertEquals('Hello there', $notificationResponse->getPayload());
        $this->assertEquals('here', $notificationResponse->getChannelName());
        $this->assertEquals(1036, $notificationResponse->getNotifyingProcessId());

    }
}
