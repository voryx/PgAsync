<?php

namespace PgAsync\Command;

interface CommandInterface
{
    public function encodedMessage(): string;

    public function complete();

    public function error(\Throwable $throwable);

    public function next($value);

    public function isActive(): bool;

    public function cancel();

    public function shouldWaitForComplete(): bool;
}
