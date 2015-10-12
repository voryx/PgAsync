<?php


namespace PgAsync\Message;


class SSLRequest extends Message {
    public function encodedMessage() {
        $msg = Message::int32(80877103);

        return Message::prependLengthInt32($msg);
    }
} 