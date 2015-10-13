<?php

require_once __DIR__ . '/bootstrap.php';

$loop = \React\EventLoop\Factory::create();

$client = new PgAsync\Client('file:/tmp/.s.PGSQL.5432', $loop);

$client->connect([
    "user"     => "matt",
    "database" => "matt"
]);

$statement = $client->executeStatement("SELECT * FROM channel WHERE id = $1", ['2']);

$statement
    ->subscribe(new \Rx\Observer\CallbackObserver(
        function ($row) {
            echo json_encode($row) . "\n";
        },
        function ($err) {

        },
        function () {
            echo "Complete.";
        }
    ));

$loop->run();
