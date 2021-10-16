<?php

namespace PgAsync\Command;

use pq\Exception\InvalidArgumentException;
use Rx\ObserverInterface;

class Sync implements CommandInterface
{
    use CommandTrait;

    private $description;

    public function __construct(string $description = "", ?ObserverInterface $observer = null)
    {
        if (null === $observer) {
            throw new InvalidArgumentException('Observer interface is required.');
        }
        $this->description = $description;
        $this->observer    = $observer;
    }

    public function encodedMessage(): string
    {
        return "S\0\0\0\x04";
    }

    public function shouldWaitForComplete(): bool
    {
        return true;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
