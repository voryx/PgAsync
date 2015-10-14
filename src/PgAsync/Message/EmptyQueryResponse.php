<?php


namespace PgAsync\Message;


class EmptyQueryResponse implements ParserInterface
{
    use ParserTrait;

    /**
     * @inheritDoc
     */
    public function parseMessage($rawMessage)
    {
        // there is nothing to parse here
    }

    /**
     * @inheritDoc
     */
    static public function getMessageIdentifier()
    {
        return 'I';
    }
}