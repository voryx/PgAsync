<?php


namespace PgAsync\Message;


interface CommandInterface {
    public function encodedMessage();
    public function complete();
    public function error();
} 