<?php

namespace PgAsync\Command;

use Rx\Subject\Subject;

interface CommandInterface
{
    public function encodedMessage();

    public function complete();

    public function error();

    public function shouldWaitForComplete();

    /** @return Subject */
    public function getSubject();
}
