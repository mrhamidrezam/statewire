<?php

declare(strict_types=1);

namespace StateWire\Exceptions;

class InvalidTransitionException extends StateWireException
{
    /**
     * @param list<string> $allowedEvents
     */
    public function __construct(
        public readonly string $currentState,
        public readonly string $attemptedEvent,
        public readonly array $allowedEvents = [],
        string $message = ''
    ) {
        if ($message === '') {
            $allowedStr = empty($allowedEvents) ? 'none' : implode(', ', $allowedEvents);
            $message = sprintf(
                "Cannot apply event '%s' from state '%s'. Allowed events in this state: [%s].",
                $attemptedEvent,
                $currentState,
                $allowedStr
            );
        }

        parent::__construct($message);
    }
}
