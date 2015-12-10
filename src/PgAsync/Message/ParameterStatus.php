<?php

namespace PgAsync\Message;

class ParameterStatus implements ParserInterface
{
    use ParserTrait;

    private $parameterName = "";
    private $parameterValue = "";

    /**
     * @inheritDoc
     */
    public function parseMessage($rawMessage)
    {
        $payload = substr($rawMessage, 5, strlen($rawMessage) - 5);

        $paramParts           = explode("\0", $payload);
        $this->parameterName  = $paramParts[0];
        $this->parameterValue = $paramParts[1];
    }

    /**
     * @inheritDoc
     */
    public static function getMessageIdentifier()
    {
        return 'S';
    }

    /**
     * @return string
     */
    public function getParameterName()
    {
        return $this->parameterName;
    }

    /**
     * @return string
     */
    public function getParameterValue()
    {
        return $this->parameterValue;
    }
}
