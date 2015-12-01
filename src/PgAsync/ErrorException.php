<?php


namespace PgAsync;


use PgAsync\Message\ErrorResponse;

class ErrorException extends \Exception
{
    /** @var ErrorResponse */
    private $errorResponse;

    private $extraInfo;

    /**
     * ErrorException constructor.
     *
     * @param ErrorResponse $errorResponse
     * @param mixed $extraInfo
     */
    public function __construct(ErrorResponse $errorResponse, $extraInfo = null)
    {
        $this->errorResponse = $errorResponse;
        $this->message = $this->errorResponse->__toString();
        $this->extraInfo = $extraInfo;

        if (is_array($extraInfo) && isset($extraInfo['query_string'])) {
            $this->message .= " while executing \"" . $extraInfo['query_string'] . "\"";
        }
    }

    /**
     * @return ErrorResponse
     */
    public function getErrorResponse()
    {
        return $this->errorResponse;
    }

    /**
     * @return mixed|null
     */
    public function getExtraInfo()
    {
        return $this->extraInfo;
    }
}