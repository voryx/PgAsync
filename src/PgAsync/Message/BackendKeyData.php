<?php

namespace PgAsync\Message;

class BackendKeyData extends Message
{
    /**
     * @inheritDoc
     */
    public function parseMessage($rawMessage)
    {
        // TODO - this is unsupported right now
    }

    /**
     * @inheritDoc
     */
    public static function getMessageIdentifier()
    {
        return 'K';
    }
}
