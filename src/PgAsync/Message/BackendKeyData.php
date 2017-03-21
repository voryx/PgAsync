<?php

namespace PgAsync\Message;

class BackendKeyData extends Message
{
    /**
     * @inheritDoc
     */
    public function parseMessage(string $rawMessage)
    {
        // TODO - this is unsupported right now
    }

    /**
     * @inheritDoc
     */
    public static function getMessageIdentifier(): string
    {
        return 'K';
    }
}
