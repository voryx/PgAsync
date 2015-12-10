<?php

namespace PgAsync\Message;

class CopyOutResponse implements ParserInterface
{
    use ParserTrait;

    /**
     * @inheritDoc
     */
    public function parseMessage($rawMessage)
    {
        // TODO: Implement parseMessage() method.
    }

    /**
     * @inheritDoc
     */
    public static function getMessageIdentifier()
    {
        return 'H';
    }
}
