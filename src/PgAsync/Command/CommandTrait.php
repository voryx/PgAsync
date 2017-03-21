<?php

namespace PgAsync\Command;

use Rx\Subject\Subject;

trait CommandTrait
{
    private $subject;

    public function complete()
    {
        $this->getSubject()->onCompleted();
    }

    public function error(\Exception $exception = null)
    {
        if (!($exception instanceof \Exception)) {
            $exception = new \Exception('Unknown Error');
        }

        $this->getSubject()->onError($exception);
    }

    public function getSubject(): Subject
    {
        if ($this->subject === null) {
            $this->subject = new Subject();
        }
        return $this->subject;
    }
}
