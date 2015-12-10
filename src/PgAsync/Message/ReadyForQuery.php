<?php

namespace PgAsync\Message;

class ReadyForQuery implements ParserInterface
{
    use ParserTrait;

    private $backendTransactionStatus;

    /**
     * @inheritDoc
     */
    public function parseMessage($rawMessage)
    {
        if (strlen($rawMessage) != 6) {
            throw new \Exception;
        }

        $this->backendTransactionStatus = $rawMessage[5];
        if (!in_array($this->backendTransactionStatus, ['I', 'T', 'E'])) {
            throw new \Exception;
        }
    }

    /**
     * @inheritDoc
     */
    public static function getMessageIdentifier()
    {
        return 'Z';
    }

    /**
     * @return mixed
     */
    public function getBackendTransactionStatus()
    {
        return $this->backendTransactionStatus;
    }
}
