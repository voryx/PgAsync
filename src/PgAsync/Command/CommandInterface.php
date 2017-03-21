<?php

namespace PgAsync\Command;

use Rx\Subject\Subject;

interface CommandInterface
{
    public function encodedMessage(): string;

    public function complete();

    public function error();

    public function shouldWaitForComplete(): bool;

    public function getSubject(): Subject;
}
