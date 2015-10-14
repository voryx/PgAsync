<?php

require_once __DIR__ . '/bootstrap.php';

$loop = \React\EventLoop\Factory::create();

$conn = new \PgAsync\Connection([
    "user" => "matt",
    "database" => "matt"
], $loop);

$conn->query("SELECT * FROM channel");

$loop->run();