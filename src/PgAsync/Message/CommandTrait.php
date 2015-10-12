<?php


namespace PgAsync\Message;

use Rx\Subject\Subject;

trait CommandTrait {
    /** @var Subject */
    private $subject;

    public function complete() {
        $this->subject->onCompleted();
    }

    public function error(\Exception $exception = null) {
        if (!($exception instanceof \Exception)) {
            $exception = new \Exception("Unknown Error");
        }

        $this->subject->onError($exception);
    }

    /**
     * @return Subject
     */
    public function getSubject()
    {
        return $this->subject;
    }
}