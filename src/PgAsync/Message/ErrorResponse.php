<?php

namespace PgAsync\Message;

class ErrorResponse implements ParserInterface
{
    use ParserTrait;

    private $errorMessages = [];

    /**
     * @inheritDoc
     */
    public function parseMessage(string $rawMessage)
    {
        $rawMsgs = substr($rawMessage, 5);
        $parts   = explode("\0", $rawMsgs);

        foreach ($parts as $part) {
            if (strlen($part) < 2) {
                break;
            }
            $fieldType = $part[0];

            switch ($fieldType) {
                case 'S'://Severity: the field contents are ERROR, FATAL, or PANIC (in an error message), or WARNING, NOTICE, DEBUG, INFO, or LOG (in a notice message), or a localized translation of one of these. Always present.
                case 'C'://Code: the SQLSTATE code for the error (see Appendix A). Not localizable. Always present.
                case 'M'://Message: the primary human-readable error message. This should be accurate but terse (typically one line). Always present.
                case 'D'://Detail: an optional secondary error message carrying more detail about the problem. Might run to multiple lines.
                case 'H'://Hint: an optional suggestion what to do about the problem. This is intended to differ from Detail in that it offers advice (potentially inappropriate) rather than hard facts. Might run to multiple lines.
                case 'P'://Position: the field value is a decimal ASCII integer, indicating an error cursor position as an index into the original query string. The first character has index 1, and positions are measured in characters not bytes.
                case 'p'://Internal position: this is defined the same as the P field, but it is used when the cursor position refers to an internally generated command rather than the one submitted by the client. The q field will always appear when this field appears.
                case 'q'://Internal query: the text of a failed internally-generated command. This could be, for example, a SQL query issued by a PL/pgSQL function.
                case 'W'://Where: an indication of the context in which the error occurred. Presently this includes a call stack traceback of active procedural language functions and internally-generated queries. The trace is one entry per line, most recent first.
                case 'F'://File: the file name of the source-code location where the error was reported.
                case 'L'://Line: the line number of the source-code location where the error was reported.
                case 'R'://Routine: the name of the source-code routine reporting the error.
            }

            $msg = substr($part, 1);

            $this->errorMessages[] = [
                'type'    => $fieldType,
                'message' => $msg
            ];
        }
    }

    /**
     * @inheritDoc
     */
    public static function getMessageIdentifier(): string
    {
        return 'E';
    }

    /**
     * @return array
     */
    public function getErrorMessages()
    {
        return $this->errorMessages;
    }

    public function getSeverity(): string
    {
        $severity = $this->getErrorMessagesOfType('S');

        return count($severity) > 0 ? array_pop($severity) : null;
    }

    public function getMessage()
    {
        $message = $this->getErrorMessagesOfType('M');

        return count($message) > 0 ? array_pop($message) : null;
    }

    private function getErrorMessagesOfType($type):array
    {
        return array_map(function ($x) {
            return $x['message'];
        }, array_filter($this->getErrorMessages(), function ($x) use ($type) {
            return $x['type'] === $type;
        }));
    }

    /**
     * @inheritDoc
     */
    public function __toString()
    {
        return $this->getSeverity() . ': ' . $this->getMessage();
    }
}
