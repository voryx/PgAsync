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
} 