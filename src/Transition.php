<?php

declare(strict_types=1);

namespace StateWire;

/**
 * Definition of a directional state transition triggered by an event.
 */
class Transition
{
    /** @var list<string> */
    public readonly array $fromStates;

    /** @var list<Guard> */
    public readonly array $guards;

    /** @var list<callable(Context, array<string, mixed>, Transition): void> */
    public readonly array $actions;

    /**
     * @param string|list<string> $from Source state(s) or '*' for any state
     * @param string $to Target state
     * @param string $event Trigger event identifier
     * @param list<Guard|callable(Context, array<string, mixed>): bool> $guards
     * @param list<callable(Context, array<string, mixed>, Transition): void> $actions
     * @param string|null $label Optional human-readable description
     */
    public function __construct(
        string|array $from,
        public readonly string $to,
        public readonly string $event,
        array $guards = [],
        array $actions = [],
        public readonly ?string $label = null
    ) {
        $this->fromStates = is_array($from) ? array_values($from) : [$from];

        $normalizedGuards = [];
        foreach ($guards as $guard) {
            if ($guard instanceof Guard) {
                $normalizedGuards[] = $guard;
            } elseif (is_callable($guard)) {
                $normalizedGuards[] = new Guard($guard);
            }
        }
        $this->guards = $normalizedGuards;
        $this->actions = array_values($actions);
    }

    /**
     * Check if this transition applies from the given current state.
     */
    public function matchesFrom(string $currentState): bool
    {
        return in_array('*', $this->fromStates, true) || in_array($currentState, $this->fromStates, true);
    }

    /**
     * Evaluate all guards. Returns [bool $allowed, string|null $failureReason].
     *
     * @param Context $context
     * @param array<string, mixed> $payload
     * @return array{0: bool, 1: ?string}
     */
    public function checkGuards(Context $context, array $payload = []): array
    {
        foreach ($this->guards as $guard) {
            if (!$guard->evaluate($context, $payload)) {
                return [false, $guard->reason];
            }
        }
        return [true, null];
    }
}
