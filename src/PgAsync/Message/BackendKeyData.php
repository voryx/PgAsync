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
    static public function getMessageIdentifier()
    {
        return 'K';
    }


}