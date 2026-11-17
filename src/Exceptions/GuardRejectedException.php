<?php

declare(strict_types=1);

namespace StateWire\Exceptions;

class GuardRejectedException extends StateWireException
{
    public function __construct(
        public readonly string $currentState,
        public readonly string $targetState,
        public readonly string $event,
        public readonly string $reason = 'Guard rejected the transition'
    ) {
        parent::__construct(
            sprintf(
                "Transition on event '%s' from '%s' to '%s' was rejected by guard: %s.",
                $event,
                $currentState,
                $targetState,
                $reason
            )
        );
    }
}
