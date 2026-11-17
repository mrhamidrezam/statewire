<?php

declare(strict_types=1);

namespace StateWire;

use JsonSerializable;

/**
 * Result object returned by non-throwing transition attempts.
 */
class TransitionResult implements JsonSerializable
{
    public function __construct(
        public readonly bool $successful,
        public readonly string $event,
        public readonly string $fromState,
        public readonly string $toState,
        public readonly ?TransitionRecord $record = null,
        public readonly ?string $failureReason = null
    ) {
    }

    public static function ok(
        string $event,
        string $from,
        string $to,
        TransitionRecord $record
    ): self {
        return new self(
            successful: true,
            event: $event,
            fromState: $from,
            toState: $to,
            record: $record,
            failureReason: null
        );
    }

    public static function fail(
        string $event,
        string $from,
        string $attemptedTo = '',
        string $reason = 'Transition failed'
    ): self {
        return new self(
            successful: false,
            event: $event,
            fromState: $from,
            toState: $attemptedTo,
            record: null,
            failureReason: $reason
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'successful' => $this->successful,
            'event' => $this->event,
            'from' => $this->fromState,
            'to' => $this->toState,
            'failure_reason' => $this->failureReason,
            'record' => $this->record?->jsonSerialize(),
        ];
    }
}
