<?php

namespace PgAsync\Message;

trait ParserTrait
{
    private $currentMsg = "";
    private $msgLen = 0;

    public function parseData($data)
    {
        $this->currentMsg .= $data;

        $len = strlen($this->currentMsg);
        if ($len >= 5) {
            $this->msgLen = unpack('N', substr($this->currentMsg, 1, 4))[1];
            if ($this->msgLen > 0 && $len > $this->msgLen) {
                $theMessage = substr($this->currentMsg, 0, $this->msgLen + 1);

                $this->parseMessage($theMessage);

                if ($len > $this->msgLen + 1) {
                    return substr($this->currentMsg, $this->msgLen + 1);
                }

                return "";
            }
        }

        return false;
    }
}
