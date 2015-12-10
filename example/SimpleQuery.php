<?php
require_once __DIR__ . '/bootstrap.php';

$client = new PgAsync\Client([
    "host"     => "127.0.0.1",
    "port"     => "5432",
    "user"     => "matt",
    "database" => "matt"
]);

$insert = $client->query("INSERT INTO channel(name, description) VALUES('Test Name', 'This was inserted using the PgAsync thing')");

$insert->subscribe(new \Rx\Observer\CallbackObserver(
    function ($row) {
        echo "Row on insert?\n";
        var_dump($row);
    },
    function ($e) {
        echo "Failed.\n";
    },
    function () {
        echo "INSERT Complete.\n";
    }
));

$select = $client->query('SELECT * FROM channel');

$select->subscribe(new \Rx\Observer\CallbackObserver(
    function ($row) {
        var_dump($row);
    },
    function ($e) {
        echo "Failed.\n";
    },
    function () {
        echo "SELECT complete.\n";
    }
));

$timerCount = 0;

\EventLoop\addPeriodicTimer(1, function ($timer) use ($client, $select, &$timerCount) {
    echo "There are " . $client->getConnectionCount() . " connections. ($timerCount)\n";
    if ($timerCount < 3) {
        $select->subscribe(new \Rx\Observer\CallbackObserver(
            function ($row) {
                var_dump($row);
            },
            function ($e) {
                echo "Failed.\n";
            },
            function () {
                echo "SELECT complete.\n";
            }
        ));
    }
    $timerCount++;
});
