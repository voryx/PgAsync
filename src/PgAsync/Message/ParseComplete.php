<?php

namespace PgAsync\Message;

class ParseComplete implements ParserInterface
{
    use ParserTrait;

    /**
     * @inheritDoc
     */
    public function parseMessage($rawMessage)
    {
    }

    /**
     * @inheritDoc
     */
    public static function getMessageIdentifier()
    {
        return '1';
    }
}
