<?php


namespace PgAsync\Message;


class Message {
    static public function int16($i) {
        return pack("n", $i);
    }

    static public function int32($i) {
        return pack("N", $i);
    }

    static public function prependLengthInt32($s) {
        $len = strlen($s);

        return Message::int32($len + 4) . $s;
    }

    static public function createMessageFromIdentifier($identifier) {
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
        }

        return new Discard();
    }
} 