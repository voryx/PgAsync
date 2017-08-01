<?php

namespace PgAsync\Message;

class EmptyQueryResponse implements ParserInterface
{
    use ParserTrait;

    /**
     * @inheritDoc
     */
    public function parseMessage(string $rawMessage)
    {
        // there is nothing to parse here
    }

    /**
     * @inheritDoc
     */
    public static function getMessageIdentifier(): string
    {
        return 'I';
    }
}
