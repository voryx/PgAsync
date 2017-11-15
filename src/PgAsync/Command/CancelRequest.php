<?php

namespace PgAsync\Command;

use PgAsync\Message\Message;

class CancelRequest implements CommandInterface
{
    use CommandTrait;

    private $pid;
    private $key;

    public function __construct(int $pid, int $key)
    {
        $this->pid = $pid;
        $this->key = $key;
    }

    public function encodedMessage(): string
    {
        $len         = '00000010';
        $requestCode = '04d2162e';

        return hex2bin($len . $requestCode) . Message::int32($this->pid) . Message::int32($this->key);
    }

    public function shouldWaitForComplete(): bool
    {
        return false;
    }
}