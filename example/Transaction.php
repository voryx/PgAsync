<?php
require_once __DIR__ . '/bootstrap.php';

$client = new PgAsync\Client([
    "user"     => "pgasync",
    "database" => "pgasync_test",
]);

$client->query(
    'CREATE TABLE IF NOT EXISTS stroom(name text, description text, ts text)',
)->concat($client->transaction()->flatMap(function (PgAsync\Transaction $tx) {
    return $tx->executeStatement(
        'INSERT INTO stroom(name, description, ts) VALUES ($1, $2, $3)',
        ['Test Name', 'Inserted inside a transaction', time()]
    )->flatMap(function () use ($tx) {
        return $tx->commit();
    });
}))->concat($client->query(
    "SELECT name, description, ts FROM stroom WHERE name = 'Test Name' ORDER BY ts ASC"
)->toArray())->toPromise()->then(
    function ($rows) use ($client) {
        var_dump($rows);
        echo "Transaction committed.\n";
        $client->closeNow();
    },
    function ($e) use ($client) {
        echo "Transaction failed: " . $e->getMessage() . "\n";
        $client->closeNow();
    }
);
