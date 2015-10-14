<?php


namespace PgAsync\Command;


class SSLRequest implements CommandInterface {
    use CommandTrait;

    public function encodedMessage() {
        $msg = Message::int32(80877103);

        return Message::prependLengthInt32($msg);
    }
} 