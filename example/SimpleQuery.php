<?php
require_once __DIR__ . '/../vendor/autoload.php';


$loop = \React\EventLoop\Factory::create();


$client = new PgAsync\Client('file:/tmp/.s.PGSQL.5432', $loop);

$client->connect([
    "user"     => "matt",
    "database" => "matt"
])->then(function ($client) {
    echo "Connected to Postgres!\n";
});

// beginnings of prepared statement stuff
//$client->prepare("SELECT * FROM channel WHERE id = $1", "bla")->subscribe(new \Rx\Observer\CallbackObserver(
//    function ($x) {
//
//    },
//    function ($e) {
//
//    },
//    function () {
//
//    }
//));

//$loop->addTimer(5, function () use ($client) {
//    $client->describePreparedStatement('bla')->then(function ($something) {
//        var_dump($something);
//    }, function ($err) {
//        var_dump($err);
//    });
//});

$client->query("INSERT INTO channel(name, description) VALUES('Test Name', 'This was inserted using the PgAsync thing')")->subscribe(new \Rx\Observer\CallbackObserver(
    function ($row) {
        echo "Row on insert?\n";
        var_dump($row);
    },
    function ($e) {
        echo "Failed.\n";
    },
    function () {
        echo "Complete.\n";
    }
));

$client->query('SELECT * FROM channel')->subscribe(new \Rx\Observer\CallbackObserver(
    function ($row) {
        var_dump($row);
    },
    function ($e) {
        echo "Failed.\n";
    },
    function () {
        echo "Complete.\n";
    }
));

$loop->run();