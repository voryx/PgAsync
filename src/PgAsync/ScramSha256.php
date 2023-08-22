<?php
declare(strict_types=1);

namespace PgAsync;

class ScramSha256
{
    const CHARACTERS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
    const CLIENT_NONCE_LENGTH = 20;

    const STAGE_NOT_STARTED = 0;
    const STAGE_FIRST_MESSAGE = 1;
    const STAGE_FINAL_MESSAGE = 2;
    const STAGE_VERIFICATION = 3;

    /**
     * @var string
     */
    private $user;
    /**
     * @var string
     */
    private $password;
    /**
     * @var string
     */
    private $clientNonce = '';
    /**
     * @var string
     */
    private $nonce;
    /**
     * @var string
     */
    private $salt;
    /**
     * @var int
     */
    private $iteration;
    /**
     * @var string
     */
    private $verification;

    private $clientFirstMessageWithoutProof;
    private $saltedPassword;
    private $clientKey;
    private $storedKey;
    private $authMessage;

    private $currentStage = self::STAGE_NOT_STARTED;

    public function __construct(string $user, string $password)
    {
        $this->user = $user;
        $this->password = $password;
    }

    public function beginFirstClientMessageStage()
    {
        $length = strlen(self::CHARACTERS);

        for ($i = 0; $i < self::CLIENT_NONCE_LENGTH; $i++) {
            $this->clientNonce .= substr(self::CHARACTERS, random_int(0, $length), 1);
        }

        $this->currentStage = self::STAGE_FIRST_MESSAGE;
    }

    public function beginFinalClientMessageStage(string $nonce, string $salt, int $iteration)
    {
        $this->nonce = $nonce;
        $this->salt = $salt;
        $this->iteration = $iteration;

        $this->currentStage = self::STAGE_FINAL_MESSAGE;
    }

    public function beginVerificationStage(string $verification)
    {
        $this->verification = $verification;

        $this->currentStage = self::STAGE_VERIFICATION;
    }

    public function verify(): bool
    {
        $this->checkStage(self::STAGE_VERIFICATION);

        $serverKey = hash_hmac("sha256", "Server Key", $this->getSaltedPassword(), true);
        $serverSignature = hash_hmac('sha256', $this->getAuthMessage(), $serverKey, true);

        return $serverSignature === base64_decode($this->verification);
    }

    public function getClientFirstMessageWithoutProof(): string
    {
        if (null === $this->clientFirstMessageWithoutProof) {
            $this->clientFirstMessageWithoutProof = sprintf(
                'c=%s,r=%s',
                base64_encode('n,,'),
                $this->nonce
            );
        }

        return $this->clientFirstMessageWithoutProof;
    }

    public function getSaltedPassword(): string
    {
        $this->checkStage(self::STAGE_FINAL_MESSAGE);

        if (null === $this->saltedPassword) {
            $this->saltedPassword = hash_pbkdf2(
                "sha256",
                $this->password,
                base64_decode($this->salt),
                $this->iteration,
                32,
                true
            );
        }

        return $this->saltedPassword;
    }

    public function getClientKey(): string
    {
        $this->checkStage(self::STAGE_FINAL_MESSAGE);

        if (null === $this->clientKey) {
            $this->clientKey = hash_hmac("sha256", "Client Key", $this->getSaltedPassword(), true);
        }

        return $this->clientKey;
    }

    public function getStoredKey(): string
    {
        $this->checkStage(self::STAGE_FINAL_MESSAGE);

        if (null === $this->storedKey) {
            $this->storedKey = hash("sha256", $this->getClientKey(), true);
        }

        return $this->storedKey;
    }

    public function getClientFirstMessageBare(): string
    {
        $this->checkStage(self::STAGE_FIRST_MESSAGE);

        return sprintf(
            'n=%s,r=%s',
            $this->user,
            $this->clientNonce
        );
    }

    public function getClientFirstMessage(): string
    {
        $this->checkStage(self::STAGE_FIRST_MESSAGE);

        return sprintf('n,,%s', $this->getClientFirstMessageBare());
    }

    public function getAuthMessage(): string
    {
        $this->checkStage(self::STAGE_FINAL_MESSAGE);

        if (null === $this->authMessage) {
            $clientFirstMessageBare = $this->getClientFirstMessageBare();
            $serverFirstMessage = sprintf(
                'r=%s,s=%s,i=%s',
                $this->nonce,
                $this->salt,
                $this->iteration
            );

            $this->authMessage = implode(',', [
                $clientFirstMessageBare,
                $serverFirstMessage,
                $this->getClientFirstMessageWithoutProof()
            ]);
        }

        return $this->authMessage;
    }

    public function getClientProof(): string
    {
        $this->checkStage(self::STAGE_FINAL_MESSAGE);

        $clientKey = $this->getClientKey();
        $storedKey = $this->getStoredKey();
        $authMessage = $this->getAuthMessage();
        $clientSignature = hash_hmac("sha256", $authMessage, $storedKey, true);

        return $clientKey ^ $clientSignature;
    }

    private function checkStage(int $stage)
    {
        if ($this->currentStage < $stage) {
            throw new \LogicException('Invalid Stage of SCRAM authorization');
        }
    }
}