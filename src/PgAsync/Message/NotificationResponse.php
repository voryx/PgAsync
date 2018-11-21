<?php

namespace PgAsync\Message;

class NotificationResponse implements ParserInterface
{
    use ParserTrait;

    private $payload = '';

    private $notifyingProcessId = 0;

    private $channelName = '';

    /**
     * @inheritDoc
     */
    public function parseMessage(string $rawMessage)
    {
        $len = strlen($rawMessage);
        if ($len < 10) {
            throw new \UnderflowException;
        }

        if ($rawMessage[0] !== static::getMessageIdentifier()) {
            throw new \InvalidArgumentException('Incorrect message type');
        }
        $currentPos = 1;
        $msgLen = unpack('N', substr($rawMessage, $currentPos, 4))[1];
        $currentPos += 4;
        $this->notifyingProcessId = unpack('N', substr($rawMessage, $currentPos, 4))[1];
        $currentPos += 4;

        $rawPayload = substr($rawMessage, $currentPos);
        $parts   = explode("\0", $rawPayload);

        if (count($parts) !== 3) {
            throw new \UnderflowException('Wrong number of notification parts in payload');
        }

        $this->channelName = $parts[0];
        $this->payload = $parts[1];
    }

    /**
     * @return string
     */
    public function getPayload(): string
    {
        return $this->payload;
    }

    /**
     * @return int
     */
    public function getNotifyingProcessId(): int
    {
        return $this->notifyingProcessId;
    }

    /**
     * @return string
     */
    public function getChannelName(): string
    {
        return $this->channelName;
    }

    /**
     * @inheritDoc
     */
    public static function getMessageIdentifier(): string
    {
        return 'A';
    }

    public function getNoticeMessages(): array
    {
        return $this->noticeMessages;
    }
}
