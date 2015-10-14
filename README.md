# ReactivePostgres
Async Reactive Postgres Driver for PHP (Non-blocking)

## What it is
This is an experimental asynchronous Postgres driver for PHP based on [Rx.PHP](https://github.com/asm89/Rx.PHP) and [ReactPHP](http://reactphp.org/).

## Example - Simple Query
```php
$loop = \React\EventLoop\Factory::create();

$client = new PgAsync\Client('file:/tmp/.s.PGSQL.5432', $loop);

$client->connect([
    "user"     => "matt",
    "database" => "matt"
]);

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
```

## Example - parameterized query
```php
$loop = \React\EventLoop\Factory::create();

$client = new PgAsync\Client('file:/tmp/.s.PGSQL.5432', $loop);

$client->connect([
    "user"     => "matt",
    "database" => "matt"
]);

$client->executeStatement('SELECT * FROM channel WHERE id = $1', ['5'])
    ->subscribe(new \Rx\Observer\CallbackObserver(
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
```

## Install
With [composer](https://getcomposer.org/) install into you project with:

Install the Rx.PHP dependency (please note that we sometimes are using the davidwdan fork for development):
```composer require asm89/rx.php:dev-master```

Install pgasync:
```composer require voryx/pgadmin:dev-master```

* Note that Rx.PHP is under heavy development - you may want to check the forks.

## What it can do
- Run queries (CREATE, UPDATE, INSERT, SELECT)
- Queue commands
- Return results asynchronously (using Observables - you get data one row at a time as it comes from the db server)
- Prepared statements (well - just as parameterized queries)

## What it can't quite do yet
- Connection pooling (to allow multiple queries at once)
- Transactions

## What's next
- Add Connection pooling
- Add more testing
- Transactions
- Take over the world
