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

## Keep in mind

This is an asynchronous library. If you begin 3 queries (subscribe to their observable):
```php
$client->query("SELECT * FROM table1")->subscribe(...);
$client->query("SELECT * FROM table2")->subscribe(...);
$client->query("SELECT * FROM table3")->subscribe(...);
```
It will start all of them almost simultaneously (and you will begin receiving rows on
all 3 before any of them have completed). This can be great if you want to run
3 queries at the same time, but it you have some queries that need information
that was modified by other statements, this can cause a race condition:
```php
$client->query("INSERT INTO invoices(inv_no, customer_id, amount) VALUES('1234A', 1, 35.75)")->subscribe(...);
$client->query("SELECT SUM(amount) AS balance FROM invoices WHERE customer_id = 1")->subscribe(...);
```
In the above situation, your balance may or may not include the invoice inserted
on the first line.

You can avoid this by using the Rx concat* operator to only start up the second observable
after the first has completed:
```php
$insert = $client->query("INSERT INTO invoices(inv_no, customer_id, amount) VALUES('1234A', 1, 35.75)");
$select = $client->query("SELECT SUM(amount) AS balance FROM invoices WHERE customer_id = 1");

$insert
    ->concat($select)
    ->subscribe(...);
```
## A Note about Rx.PHP
There has been a lot of work on Rx.PHP that has not been pulled into the main repo.
To use the concat operator mentioned above, you need to instruct composer to grab
Rx.PHP from a different fork. To do that, add the following to the composer.json file:
```json
  "repositories": [
    {
      "type": "git",
      "url": "git@github.com:davidwdan/Rx.PHP.git"
    }
  ]
```