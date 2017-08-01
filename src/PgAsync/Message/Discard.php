<?php

namespace PgAsync\Message;

/**
 * Swallows messages - used if we don't know what it is or it is not implemented yet
 *
 * Class Discard
 * @package PgAsync\Message
 */
class Discard implements ParserInterface
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
        return '_';
    }
}
