<?php


namespace PgAsync;


use PgAsync\Message\ErrorResponse;

class ErrorException extends \Exception
{
    /** @var ErrorResponse */
    private $errorResponse;

    /**
     * ErrorException constructor.
     *
     * @param ErrorResponse $errorResponse
     */
    public function __construct(ErrorResponse $errorResponse)
    {
        $this->errorResponse = $errorResponse;
        $this->message = $this->errorResponse->__toString();
    }
}