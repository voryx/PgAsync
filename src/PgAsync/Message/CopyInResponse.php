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
    static public function getMessageIdentifier()
    {
        return 'G';
    }
}