<?php

declare(strict_types=1);

namespace StateWire;

/**
 * Encapsulates a boolean conditional check that permits or rejects a state transition.
 */
class Guard
{
    /** @var callable(Context, array<string, mixed>): bool */
    private $predicate;

    /**
     * @param callable(Context, array<string, mixed>): bool $predicate
     */
    public function __construct(
        callable $predicate,
        public readonly string $reason = 'Condition not satisfied'
    ) {
        $this->predicate = $predicate;
    }

    public static function create(callable $predicate, string $reason = 'Condition not satisfied'): self
    {
        return new self($predicate, $reason);
    }

    /**
     * Evaluate the guard condition.
     *
     * @param Context $context
     * @param array<string, mixed> $payload
     */
    public function evaluate(Context $context, array $payload = []): bool
    {
        return (bool) ($this->predicate)($context, $payload);
    }
}
