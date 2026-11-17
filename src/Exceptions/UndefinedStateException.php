<?php

declare(strict_types=1);

namespace StateWire\Exceptions;

class UndefinedStateException extends StateWireException
{
    public function __construct(
        public readonly string $state,
        string $message = ''
    ) {
        if ($message === '') {
            $message = sprintf("State '%s' is not defined in the state machine.", $state);
        }
        parent::__construct($message);
    }
}
