<?php

declare(strict_types=1);

namespace StateWire;

use JsonSerializable;

/**
 * Historical audit entry of an executed transition.
 */
class TransitionRecord implements JsonSerializable
{
    /**
     * @param string $event
     * @param string $fromState
     * @param string $toState
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $contextSnapshot
     * @param float $timestamp
     * @param float $durationMs
     */
    public function __construct(
        public readonly string $event,
        public readonly string $fromState,
        public readonly string $toState,
        public readonly array $payload,
        public readonly array $contextSnapshot,
        public readonly float $timestamp,
        public readonly float $durationMs
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'event' => $this->event,
            'from' => $this->fromState,
            'to' => $this->toState,
            'payload' => $this->payload,
            'context' => $this->contextSnapshot,
            'timestamp' => $this->timestamp,
            'iso_time' => date('Y-m-d\TH:i:s.u\Z', (int) $this->timestamp),
            'duration_ms' => round($this->durationMs, 3),
        ];
    }
}
