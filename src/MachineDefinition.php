<?php

declare(strict_types=1);

namespace StateWire;

use StateWire\Exceptions\UndefinedStateException;

/**
 * Structural definition and graph topology of a state machine.
 */
class MachineDefinition
{
    /** @var array<string, bool> state name => isFinal */
    private array $states = [];

    /** @var list<Transition> */
    private array $transitions = [];

    /**
     * @param string $name
     * @param string $initialState
     * @param list<string> $states
     * @param list<string> $finalStates
     * @param list<Transition> $transitions
     */
    public function __construct(
        public readonly string $name,
        public readonly string $initialState,
        array $states = [],
        array $finalStates = [],
        array $transitions = []
    ) {
        $this->addState($initialState);

        foreach ($states as $st) {
            $this->addState($st);
        }

        foreach ($finalStates as $fst) {
            $this->addState($fst, true);
        }

        foreach ($transitions as $tr) {
            $this->addTransition($tr);
        }
    }

    public function addState(string $state, bool $isFinal = false): self
    {
        $this->states[$state] = $isFinal;
        return $this;
    }

    public function addTransition(Transition $transition): self
    {
        // Auto-register destination and source states (if not wildcard)
        if ($transition->to !== '*') {
            if (!isset($this->states[$transition->to])) {
                $this->states[$transition->to] = false;
            }
        }

        foreach ($transition->fromStates as $from) {
            if ($from !== '*' && !isset($this->states[$from])) {
                $this->states[$from] = false;
            }
        }

        $this->transitions[] = $transition;
        return $this;
    }

    public function hasState(string $state): bool
    {
        return isset($this->states[$state]);
    }

    public function isFinalState(string $state): bool
    {
        return $this->states[$state] ?? false;
    }

    /**
     * @return list<string>
     */
    public function getStates(): array
    {
        return array_keys($this->states);
    }

    /**
     * @return list<string>
     */
    public function getFinalStates(): array
    {
        $final = [];
        foreach ($this->states as $st => $isFinal) {
            if ($isFinal) {
                $final[] = $st;
            }
        }
        return $final;
    }

    /**
     * @return list<Transition>
     */
    public function getTransitions(): array
    {
        return $this->transitions;
    }

    /**
     * @return list<Transition>
     */
    public function getTransitionsFrom(string $currentState): array
    {
        $matched = [];
        foreach ($this->transitions as $t) {
            if ($t->matchesFrom($currentState)) {
                $matched[] = $t;
            }
        }
        return $matched;
    }

    /**
     * @return list<string>
     */
    public function getAvailableEvents(string $currentState): array
    {
        $events = [];
        foreach ($this->getTransitionsFrom($currentState) as $t) {
            if (!in_array($t->event, $events, true)) {
                $events[] = $t->event;
            }
        }
        return $events;
    }

    /**
     * Find transition for current state and event.
     */
    public function findTransition(string $currentState, string $event): ?Transition
    {
        foreach ($this->transitions as $t) {
            if ($t->event === $event && $t->matchesFrom($currentState)) {
                return $t;
            }
        }
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $transitions = [];
        foreach ($this->transitions as $t) {
            $transitions[] = [
                'from' => count($t->fromStates) === 1 ? $t->fromStates[0] : $t->fromStates,
                'to' => $t->to,
                'event' => $t->event,
                'label' => $t->label,
                'has_guards' => count($t->guards) > 0,
            ];
        }

        return [
            'name' => $this->name,
            'initial_state' => $this->initialState,
            'states' => $this->getStates(),
            'final_states' => $this->getFinalStates(),
            'transitions' => $transitions,
        ];
    }
}
