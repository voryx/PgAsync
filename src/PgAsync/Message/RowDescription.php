<?php

namespace PgAsync\Message;

use PgAsync\Column;

class RowDescription implements ParserInterface
{
    use ParserTrait;

    /** @var Column[] */
    private $columns = [];

    /**
     * @inheritDoc
     * @throws \UnderflowException
     * @throws \InvalidArgumentException
     */
    public function parseMessage(string $rawMessage)
    {
        $len = strlen($rawMessage);
        if ($len < 7) {
            throw new \UnderflowException;
        }

        $columnCount   = unpack('n', substr($rawMessage, 5, 2))[1];
        $columnStart   = 7;
        $this->columns = [];
        for ($i = 0; $i < $columnCount; $i++) {
            $column = new Column();

            $strEnd = strpos($rawMessage, "\0", $columnStart);
            if ($strEnd === false) {
                throw new \InvalidArgumentException;
            }

            $column->name     = substr($rawMessage, $columnStart, $strEnd - $columnStart);
            $pos              = $strEnd + 1;
            $column->tableOid = unpack('N', substr($rawMessage, $pos, 4))[1];
            $pos += 4;
            $column->attrNo = unpack('n', substr($rawMessage, $pos, 2))[1];
            $pos += 2;
            $column->typeOid = unpack('N', substr($rawMessage, $pos, 4))[1];
            $pos += 4;
            $column->dataSize = unpack('n', substr($rawMessage, $pos, 2))[1];
            $pos += 2;
            $column->typeModifier = unpack('N', substr($rawMessage, $pos, 4))[1];
            $pos += 4;
            $column->formatCode = unpack('n', substr($rawMessage, $pos, 2))[1];
            $pos += 2;
            $this->columns[] = $column;
            $columnStart     = $pos;
        }
    }

    /**
     * @inheritDoc
     */
    public static function getMessageIdentifier(): string
    {
        return 'T';
    }

    /**
     * @return \PgAsync\Column[]
     */
    public function getColumns()
    {
        return $this->columns;
    }
}
