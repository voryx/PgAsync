<?php

namespace PgAsync\Message;

interface ParserInterface
{
    /**
     * Takes incoming data and parses a message from it
     * If there isn't enough data, it returns false, otherwise it will return a
     * string containing the overflow (empty string if there is no overflow)
     *
     * @param $data
     * @return mixed
     */
    public function parseData($data);

    /**
     * Called by parseData (in the trait) once the complete raw message has been received
     *
     * @param $rawMessage
     * @return mixed
     */
    public function parseMessage(string $rawMessage);

    /**
     * Returns a character that is the message identifier
     *
     * @return mixed
     */
    public static function getMessageIdentifier(): string;
}
