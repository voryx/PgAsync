# ReactivePostgres
Asynchronous Reactive Postgres Library for PHP (Non-blocking)

## What it is
This is an asynchronous Postgres library for PHP. Observables are returned by the query
methods allowing asynchronous row-by-row data handling (and other Rx operators on the data)
See [Rx.PHP](https://github.com/asm89/Rx.PHP). Network and event processing is handled by
[ReactPHP](http://reactphp.org/).

This is a pure PHP implementation (you don't need Postgres extensions to use it).

## Example - Simple Query
```php
$loop = \React\EventLoop\Factory::create();

$client = new PgAsync\Client([
    "host" => "127.0.0.1",
    "port" => "5432",
    "user"     => "matt",
    "database" => "matt"
], $loop);

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

$client = new PgAsync\Client([
     "host" => "127.0.0.1",
     "port" => "5432",
     "user"     => "matt",
     "database" => "matt"
], $loop);

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
- Run queries (CREATE, UPDATE, INSERT, SELECT, DELETE)
- Queue commands
- Return results asynchronously (using Observables - you get data one row at a time as it comes from the db server)
- Prepared statements (as parameterized queries)
- Connection pooling (basic pooling)

## What it can't quite do yet
- Transactions

## What's next
- Add more testing
- Transactions
- Take over the world
