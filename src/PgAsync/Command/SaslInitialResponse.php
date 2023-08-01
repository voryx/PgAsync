<?php
declare(strict_types=1);

namespace PgAsync\Command;

use PgAsync\ScramSha256;

class SaslInitialResponse implements CommandInterface
{
    use CommandTrait;

    const SCRAM_SHA_256 = "SCRAM-SHA-256";

    /**
     * @var ScramSha256
     */
    private $scramSha265;

    public function __construct(ScramSha256 $scramSha265)
    {
        $this->scramSha265 = $scramSha265;
    }

    public function encodedMessage(): string
    {
        $mechanism = self::SCRAM_SHA_256 . "\0";
        $clientFirstMessage = $this->scramSha265->getClientFirstMessage();

        $message = "p";
        $messageLength = strlen($mechanism) + strlen($clientFirstMessage) + 8;
        $message .= pack("N", $messageLength) . $mechanism;
        $message .= pack("N", strlen($clientFirstMessage)) . $clientFirstMessage;

        return $message;
    }

    public function shouldWaitForComplete(): bool
    {
        return false;
    }
}