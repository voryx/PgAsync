<?php

namespace PgAsync\Message;

abstract class Message implements ParserInterface
{
    use ParserTrait;

    public static function int16(string $i): string
    {
        return pack('n', $i);
    }

    public static function int32(string $i): string
    {
        return pack('N', $i);
    }

    public static function prependLengthInt32(string $s): string
    {
        $len = strlen($s);

        return Message::int32($len + 4) . $s;
    }

    public static function createMessageFromIdentifier(string $identifier): ParserInterface
    {
        switch ($identifier) {
            case 'R':
                return new Authentication();
            case 'K':
                return new BackendKeyData();
            case 'C':
                return new CommandComplete();
            case CopyInResponse::getMessageIdentifier():
                return new CopyInResponse();
            case CopyOutResponse::getMessageIdentifier():
                return new CopyOutResponse();
            case 'D':
                return new DataRow();
            case 'I':
                return new EmptyQueryResponse();
            case 'E':
                return new ErrorResponse();
            case 'N':
                return new NoticeResponse();
            case 'S':
                return new ParameterStatus();
            case '1':
                return new ParseComplete();
            case 'Z':
                return new ReadyForQuery();
            case 'T':
                return new RowDescription();
            case NotificationResponse::getMessageIdentifier():
                return new NotificationResponse();
        }

        return new Discard();
    }
}
