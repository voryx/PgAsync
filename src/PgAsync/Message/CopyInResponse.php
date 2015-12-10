<?php

namespace PgAsync\Message;

class CopyInResponse implements ParserInterface
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
        return 'G';
    }
}
