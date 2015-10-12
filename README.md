# ReactivePostgres
Async Reactive Postgres Driver for PHP (Non-blocking)

## What it is
This is an experimental asynchronous Postgres driver for PHP based on [Rx.PHP](https://github.com/asm89/Rx.PHP) and [ReactPHP](http://reactphp.org/).

## What it can do
- Run queries (CREATE, UPDATE, INSERT, SELECT)
- Queue commands
- Return results asynchronously (using Observables - you get data one row at a time as it comes from the db server)

## What it can't quite do yet
- Prepared statements
- Connection pooling (to allow multiple queries at once)

## What's next
- Make it more RXy
- Add Connection pooling
- Add support for prepared statements
- Add more testing
- Take over the world
