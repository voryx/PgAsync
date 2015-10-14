<?php


namespace PgAsync;


use PgAsync\Message\ErrorResponse;

class ErrorException extends \Exception
{
    private $errorResponse;

    /**
     * ErrorException constructor.
     */
    public function __construct(ErrorResponse $errorResponse)
    {
        $this->errorResponse = $errorResponse;
    }

    /**
     * @inheritDoc
     */
    public function __toString()
    {
        $errStrings = array_map(function ($err) {
            return $err["type"] . ": " . $err["message"];
        }, $this->errorResponse->getErrorMessages());

        return implode(". ", $errStrings);
    }
}