<?php


namespace PgAsync\Message;

use Rx\Subject\Subject;

class Query implements CommandInterface {
    use CommandTrait;

    protected $queryString = "";
    protected $rows = [];
    protected $columns = [];
    protected $columnNames = [];

    function __construct($queryString)
    {
        $this->queryString = $queryString;

        $this->subject = new Subject();
    }

    public function encodedMessage() {
        return "Q" . Message::prependLengthInt32($this->queryString . "\0");
    }

    /**
     * Add Column information (from T)
     *
     * @param $columns
     */
    public function addColumns($columns) {
        $this->columns = $columns;
        $this->columnNames = array_map(function ($column) { return $column->name; }, $this->columns);
    }

    public function addRow($row) {
        $this->rows = $row;
        $row = array_combine($this->columnNames, $row);
        $this->subject->onNext($row);
    }
}