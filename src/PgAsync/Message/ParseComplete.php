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
    static public function getMessageIdentifier()
    {
        return '1';
    }
}