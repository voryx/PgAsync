<?php

namespace PgAsync\Message;

class ParseComplete implements ParserInterface
{
    use ParserTrait;

    /**
     * @inheritDoc
     */
    public function parseMessage(string $rawMessage)
    {
    }

    /**
     * @inheritDoc
     */
    public static function getMessageIdentifier(): string
    {
        return '1';
    }
}
