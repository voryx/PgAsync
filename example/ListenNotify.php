<?php

require_once __DIR__ . '/bootstrap.php';

$client = new PgAsync\Client([
    'host'     => '127.0.0.1',
    'port'     => '5432',
    'user'     => 'matt',
    'database' => 'matt',
]);

$client->listen('some_channel')
    ->subscribe(function (\PgAsync\Message\NotificationResponse $message) {
        echo $message->getChannelName() . ': ' . $message->getPayload() . "\n";
    });

\Rx\Observable::timer(1000)
    ->flatMapTo($client->query("NOTIFY some_channel, 'Hello World'"))
    ->subscribe();

