<?php


namespace PgAsync\Message;


interface CommandInterface {
    public function encodedMessage();
    public function complete();
    public function error();
    public function shouldWaitForComplete();

    /** Subject */
    public function getSubject();
} 