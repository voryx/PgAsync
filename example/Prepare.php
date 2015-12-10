<?php

require_once __DIR__ . '/bootstrap.php';

$client = new PgAsync\Client([
    "host"     => "127.0.0.1",
    "port"     => "5432",
    "user"     => "matt",
    "database" => "matt"
]);

$jsonObserverFactory = function () {
    return new \Rx\Observer\CallbackObserver(
        function ($row) {
            echo json_encode($row) . "\n";
        },
        function ($err) {
            echo "ERROR: " . $err . "\n";
        },
        function () {
            echo "Complete.";
        }
    );
};

$statement = $client->executeStatement("SELECT * FROM channel WHERE id = $1", ['2', '3']);

$statement->subscribe($jsonObserverFactory());

$insertStatement = $client->executeStatement("InSErT INTO channel(name, description) VALUES($1, $2)", ['The name', null]);

$insertStatement->subscribe($jsonObserverFactory());

$statement->subscribe($jsonObserverFactory());
