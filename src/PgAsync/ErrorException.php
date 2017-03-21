<?php

namespace PgAsync;

use PgAsync\Message\ErrorResponse;

class ErrorException extends \Exception
{
    private $errorResponse;
    private $extraInfo;

    public function __construct(ErrorResponse $errorResponse, array $extraInfo = null)
    {
        $this->errorResponse = $errorResponse;
        $this->message       = $this->errorResponse->__toString();
        $this->extraInfo     = $extraInfo;

        if (is_array($extraInfo) && isset($extraInfo['query_string'])) {
            $this->message .= ' while executing "' . $extraInfo['query_string'] . '"';
        }
    }

    public function getErrorResponse(): ErrorResponse
    {
        return $this->errorResponse;
    }

    public function getExtraInfo(): array
    {
        return $this->extraInfo;
    }
}
