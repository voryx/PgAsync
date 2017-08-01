<?php

namespace PgAsync\Message;

class DataRow implements ParserInterface
{
    use ParserTrait;

    /** @var array */
    private $columnValues = [];

    /**
     * @inheritDoc
     */
    public function parseMessage(string $rawMessage)
    {
        $len = strlen($rawMessage);
        if ($len < 8) {
            throw new \UnderflowException;
        }

        $columnCount        = unpack('n', substr($rawMessage, 5, 2))[1];
        $this->columnValues = [];
        $columnStart        = 7;
        for ($i = 0; $i < $columnCount; $i++) {
            if ($len < $columnStart + 4) {
                throw new \UnderflowException;
            }
            $columnLen = unpack('N', substr($rawMessage, $columnStart, 4))[1];
            if ($columnLen == 4294967295) {
                $columnLen            = 0;
                $this->columnValues[] = null;
            } else {
                if ($len < $columnStart + 4 + $columnLen) {
                    throw new \UnderflowException;
                }
                $this->columnValues[] = substr($rawMessage, $columnStart + 4, $columnLen);
            }
            $columnStart += 4 + $columnLen;
        }

        if ($len !== $columnStart) {
            //echo "Warning, there was some straggling info in the data row...";
        }
    }

    /**
     * @inheritDoc
     */
    public static function getMessageIdentifier(): string
    {
        return 'D';
    }

    public function getColumnValues(): array
    {
        return $this->columnValues;
    }
}
