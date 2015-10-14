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
    static public function getMessageIdentifier()
    {
        return 'H';
    }
}