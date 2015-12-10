<?php
require_once __DIR__ . '/bootstrap.php';

$client = new \PgAsync\Client([
    "user"     => "matt",
    "database" => "matt"
]);

$insert = $client->query("INSERT INTO channel(name, description) VALUES('SQ', 'SQ Insert')");

$select = $client->executeStatement("SELECT * FROM channel WHERE name = $1", ['SQ']);

$insert
    ->concat($select)
    ->count()
    ->subscribe(new \Rx\Observer\CallbackObserver(
        function ($x) {
            echo json_encode($x) . "\n";
        }
    ));
