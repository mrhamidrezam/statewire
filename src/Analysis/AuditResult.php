<?php

declare(strict_types=1);

namespace StateWire\Analysis;

use JsonSerializable;

/**
 * Result of static topological analysis of a state machine graph.
 */
class AuditResult implements JsonSerializable
{
    /**
     * @param list<string> $unreachableStates
     * @param list<string> $deadEndStates Non-final states with no outbound transitions
     * @param list<string> $unreachableFinalStates
     * @param list<string> $warnings
     * @param bool $hasFinalStateReachable
     */
    public function __construct(
        public readonly array $unreachableStates = [],
        public readonly array $deadEndStates = [],
        public readonly array $unreachableFinalStates = [],
        public readonly array $warnings = [],
        public readonly bool $hasFinalStateReachable = true
    ) {
    }

    public function isValid(): bool
    {
        return empty($this->unreachableStates)
            && empty($this->deadEndStates)
            && empty($this->unreachableFinalStates)
            && empty($this->warnings);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'valid' => $this->isValid(),
            'unreachable_states' => $this->unreachableStates,
            'dead_end_states' => $this->deadEndStates,
            'unreachable_final_states' => $this->unreachableFinalStates,
            'warnings' => $this->warnings,
            'has_final_state_reachable' => $this->hasFinalStateReachable,
        ];
    }
}
