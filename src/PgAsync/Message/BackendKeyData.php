<?php

namespace PgAsync\Message;

class BackendKeyData extends Message
{
    private $pid;
    private $key;

    /**
     * @inheritDoc
     */
    public function parseMessage(string $rawMessage)
    {
        if (13 !== strlen($rawMessage)) {
            throw new \UnderflowException();
        }

        $this->pid = unpack('N', substr($rawMessage, 5, 4))[1];
        $this->key = unpack('N', substr($rawMessage, 9, 4))[1];
    }

    /**
     * @inheritDoc
     */
    public static function getMessageIdentifier(): string
    {
        return 'K';
    }

    public function getPid() : int
    {
        return $this->pid;
    }

    public function getKey() : int
    {
        return $this->key;
    }
}
