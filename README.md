[![Build Status](https://travis-ci.org/voryx/PgAsync.svg?branch=master)](https://travis-ci.org/voryx/PgAsync)
# PgAsync
Asynchronous Reactive Postgres Library for PHP (Non-blocking)

## What it is
This is an asynchronous Postgres library for PHP. Observables are returned by the query
methods allowing asynchronous row-by-row data handling (and other Rx operators on the data)
See [Rx.PHP](https://github.com/asm89/Rx.PHP). Network and event processing is handled by
[ReactPHP](http://reactphp.org/).

This is a pure PHP implementation (you don't need Postgres extensions to use it).

## Example - Simple Query
```php

$client = new PgAsync\Client([
    "host" => "127.0.0.1",
    "port" => "5432",
    "user"     => "matt",
    "database" => "matt"
]);

$client->query('SELECT * FROM channel')->subscribe(
    function ($row) {
        var_dump($row);
    },
    function ($e) {
        echo "Failed.\n";
    },
    function () {
        echo "Complete.\n";
    }
);


```

## Example - parameterized query
```php

$client = new PgAsync\Client([
     "host" => "127.0.0.1",
     "port" => "5432",
     "user"     => "matt",
     "database" => "matt",
     "auto_disconnect" => true //This option will force the client to disconnect as soon as it completes.  The connection will not be returned to the connection pool.

]);

$client->executeStatement('SELECT * FROM channel WHERE id = $1', ['5'])
    ->subscribe(
        function ($row) {
            var_dump($row);
        },
        function ($e) {
            echo "Failed.\n";
        },
        function () {
            echo "Complete.\n";
        }
    );

```

## Example - LISTEN/NOTIFY
```php
$client = new PgAsync\Client([
     "host" => "127.0.0.1",
     "port" => "5432",
     "user"     => "matt",
     "database" => "matt"
]);

$client->listen('some_channel')
    ->subscribe(function (\PgAsync\Message\NotificationResponse $message) {
        echo $message->getChannelName() . ': ' . $message->getPayload() . "\n";
    });
    
$client->query("NOTIFY some_channel, 'Hello World'")->subscribe();
```

## Install
With [composer](https://getcomposer.org/) install into you project with:

Install pgasync:
```composer require voryx/pgasync```

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
3 queries at the same time, but if you have some queries that need information
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
