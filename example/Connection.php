<?php

require_once __DIR__ . '/bootstrap.php';

$loop = \React\EventLoop\Factory::create();

$conn = new \PgAsync\Connection([
    "host" => "127.0.0.1",
    "port" => "5432",
    "user"     => "matt",
    "database" => "matt"
], $loop);

$jsonObserverFactory = function () {
    return new \Rx\Observer\CallbackObserver(
        function ($row) {
            echo json_encode($row) . "\n";
        },
        function ($err) {
            echo "ERROR: " . json_encode($err) . "\n";
        },
        function () {
            echo "Complete.\n";
        }
    );
};

$channels = $conn->query("SELECT * FROM channel");

$channels->subscribe($jsonObserverFactory());

$channelsWithParamQ = $conn->executeStatement("SELECT * FROM channel WHERE id % 3 = $1", ['0']);

$channelsWithParamQ->subscribe($jsonObserverFactory());

$channels->subscribe($jsonObserverFactory());
$channelsWithParamQ->subscribe($jsonObserverFactory());

$loop->run();