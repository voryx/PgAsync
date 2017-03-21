<?php

namespace PgAsync\Message;

class CopyOutResponse implements ParserInterface
{
    use ParserTrait;

    /**
     * @inheritDoc
     */
    public function parseMessage(string $rawMessage)
    {
        // TODO: Implement parseMessage() method.
    }

    /**
     * @inheritDoc
     */
    public static function getMessageIdentifier(): string
    {
        return 'H';
    }
}
