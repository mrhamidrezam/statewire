<?php

declare(strict_types=1);

namespace StateWire;

use BackedEnum;
use StateWire\Analysis\AuditResult;
use StateWire\Analysis\GraphAuditor;
use StateWire\Exceptions\GuardRejectedException;
use StateWire\Exceptions\InvalidTransitionException;
use StateWire\Exceptions\UndefinedStateException;
use StateWire\Exporters\DotExporter;
use StateWire\Exporters\MermaidExporter;
use StateWire\Exporters\PlantUmlExporter;

/**
 * High-performance, zero-dependency Finite State Machine engine for PHP 8.2+.
 */
class StateMachine
{
    private string $currentState;
    private Context $context;

    /** @var list<TransitionRecord> */
    private array $history = [];

    /**
     * @param MachineDefinition $definition
     * @param array<string, mixed>|Context $context
     * @param string|null $currentState
     * @param array<string, list<callable(Context, array<string, mixed>): void>> $enterCallbacks
     * @param array<string, list<callable(Context, array<string, mixed>): void>> $exitCallbacks
     * @param list<callable(string, string, string, Context, array<string, mixed>): void> $beforeHooks
     * @param list<callable(string, string, string, Context, array<string, mixed>): void> $afterHooks
     */
    public function __construct(
        public readonly MachineDefinition $definition,
        array|Context $context = [],
        ?string $currentState = null,
        private readonly array $enterCallbacks = [],
        private readonly array $exitCallbacks = [],
        private readonly array $beforeHooks = [],
        private readonly array $afterHooks = []
    ) {
        $this->context = $context instanceof Context ? $context : new Context($context);
        $this->currentState = $currentState ?? $definition->initialState;

        if (!$this->definition->hasState($this->currentState)) {
            throw new UndefinedStateException($this->currentState);
        }
    }

    public static function builder(string $name = 'machine'): MachineBuilder
    {
        return new MachineBuilder($name);
    }

    public function getState(): string
    {
        return $this->currentState;
    }

    public function is(string|BackedEnum $state): bool
    {
        $target = $state instanceof BackedEnum ? (string) $state->value : $state;
        return $this->currentState === $target;
    }

    public function isFinal(): bool
    {
        return $this->definition->isFinalState($this->currentState);
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function setContext(array|Context $context): self
    {
        $this->context = $context instanceof Context ? $context : new Context($context);
        return $this;
    }

    public function getDefinition(): MachineDefinition
    {
        return $this->definition;
    }

    /**
     * @return list<TransitionRecord>
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    /**
     * Check if an event can be applied from the current state (valid transition + passing guards).
     *
     * @param string|BackedEnum $event
     * @param array<string, mixed> $payload
     */
    public function can(string|BackedEnum $event, array $payload = []): bool
    {
        $eventName = $event instanceof BackedEnum ? (string) $event->value : $event;
        $transition = $this->definition->findTransition($this->currentState, $eventName);

        if ($transition === null) {
            return false;
        }

        [$allowed] = $transition->checkGuards($this->context, $payload);
        return $allowed;
    }

    /**
     * @return list<Transition>
     */
    public function getAvailableTransitions(): array
    {
        return $this->definition->getTransitionsFrom($this->currentState);
    }

    /**
     * @return list<string>
     */
    public function getAvailableEvents(): array
    {
        return $this->definition->getAvailableEvents($this->currentState);
    }

    /**
     * Attempt a transition without throwing exceptions.
     *
     * @param string|BackedEnum $event
     * @param array<string, mixed> $payload
     */
    public function transition(string|BackedEnum $event, array $payload = []): TransitionResult
    {
        $eventName = $event instanceof BackedEnum ? (string) $event->value : $event;
        $startTime = microtime(true);

        $transition = $this->definition->findTransition($this->currentState, $eventName);
        if ($transition === null) {
            return TransitionResult::fail(
                event: $eventName,
                from: $this->currentState,
                attemptedTo: '',
                reason: sprintf(
                    "Transition on event '%s' is not defined from state '%s'.",
                    $eventName,
                    $this->currentState
                )
            );
        }

        [$allowed, $guardReason] = $transition->checkGuards($this->context, $payload);
        if (!$allowed) {
            return TransitionResult::fail(
                event: $eventName,
                from: $this->currentState,
                attemptedTo: $transition->to,
                reason: $guardReason ?? 'Guard condition failed'
            );
        }

        $fromState = $this->currentState;
        $toState = $transition->to;

        // 1. Before hooks
        foreach ($this->beforeHooks as $hook) {
            $hook($eventName, $fromState, $toState, $this->context, $payload);
        }

        // 2. Exit state hooks
        if (isset($this->exitCallbacks[$fromState])) {
            foreach ($this->exitCallbacks[$fromState] as $cb) {
                $cb($this->context, $payload);
            }
        }

        // 3. Transition actions
        foreach ($transition->actions as $action) {
            $action($this->context, $payload, $transition);
        }

        // 4. Update current state
        $this->currentState = $toState;

        // 5. Enter target state hooks
        if (isset($this->enterCallbacks[$toState])) {
            foreach ($this->enterCallbacks[$toState] as $cb) {
                $cb($this->context, $payload);
            }
        }

        // 6. After hooks
        foreach ($this->afterHooks as $hook) {
            $hook($eventName, $fromState, $toState, $this->context, $payload);
        }

        $durationMs = (microtime(true) - $startTime) * 1000;
        $record = new TransitionRecord(
            event: $eventName,
            fromState: $fromState,
            toState: $toState,
            payload: $payload,
            contextSnapshot: $this->context->all(),
            timestamp: $startTime,
            durationMs: $durationMs
        );

        $this->history[] = $record;

        return TransitionResult::ok($eventName, $fromState, $toState, $record);
    }

    /**
     * Apply an event transition, throwing on invalid transition or rejected guard.
     *
     * @param string|BackedEnum $event
     * @param array<string, mixed> $payload
     * @throws InvalidTransitionException
     * @throws GuardRejectedException
     */
    public function apply(string|BackedEnum $event, array $payload = []): self
    {
        $eventName = $event instanceof BackedEnum ? (string) $event->value : $event;
        $transition = $this->definition->findTransition($this->currentState, $eventName);

        if ($transition === null) {
            throw new InvalidTransitionException(
                currentState: $this->currentState,
                attemptedEvent: $eventName,
                allowedEvents: $this->getAvailableEvents()
            );
        }

        [$allowed, $guardReason] = $transition->checkGuards($this->context, $payload);
        if (!$allowed) {
            throw new GuardRejectedException(
                currentState: $this->currentState,
                targetState: $transition->to,
                event: $eventName,
                reason: $guardReason ?? 'Guard condition failed'
            );
        }

        $result = $this->transition($event, $payload);
        if (!$result->successful) {
            throw new InvalidTransitionException(
                currentState: $this->currentState,
                attemptedEvent: $eventName,
                allowedEvents: $this->getAvailableEvents(),
                message: $result->failureReason ?? 'Transition failed'
            );
        }

        return $this;
    }

    /**
     * Run static topological analysis on the state machine graph.
     */
    public function validate(): AuditResult
    {
        return GraphAuditor::audit($this->definition);
    }

    /**
     * Generate Mermaid state diagram.
     */
    public function toMermaid(): string
    {
        return MermaidExporter::export($this->definition, $this->currentState);
    }

    /**
     * Generate PlantUML state diagram.
     */
    public function toPlantUml(): string
    {
        return PlantUmlExporter::export($this->definition, $this->currentState);
    }

    /**
     * Generate Graphviz DOT format.
     */
    public function toDot(): string
    {
        return DotExporter::export($this->definition, $this->currentState);
    }

    /**
     * Serialize machine definition and state to JSON.
     */
    public function toJson(int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): string
    {
        $data = [
            'state' => $this->currentState,
            'context' => $this->context->all(),
            'definition' => $this->definition->toArray(),
            'history' => array_map(static fn($r) => $r->jsonSerialize(), $this->history),
        ];

        return (string) json_encode($data, $flags);
    }

    /**
     * Instantiate a StateMachine from an array schema.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed>|Context $context
     */
    public static function fromArray(array $data, array|Context $context = []): self
    {
        $name = (string) ($data['name'] ?? 'machine');
        $initial = (string) ($data['initial_state'] ?? $data['initial'] ?? 'initial');
        $states = (array) ($data['states'] ?? []);
        $finalStates = (array) ($data['final_states'] ?? []);

        $builder = self::builder($name)->initial($initial);

        foreach ($states as $st) {
            $builder->state((string) $st);
        }

        foreach ($finalStates as $fst) {
            $builder->finalState((string) $fst);
        }

        foreach ($data['transitions'] ?? [] as $t) {
            $builder->transition(
                from: $t['from'],
                to: (string) $t['to'],
                event: (string) $t['event'],
                label: $t['label'] ?? null
            );
        }

        $machine = $builder->build($context);

        if (isset($data['state']) && $data['state'] !== $initial) {
            $prop = new \ReflectionProperty(self::class, 'currentState');
            $prop->setAccessible(true);
            $prop->setValue($machine, (string) $data['state']);
        }

        return $machine;
    }

    /**
     * Instantiate a StateMachine from a JSON string.
     *
     * @param array<string, mixed>|Context $context
     */
    public static function fromJson(string $json, array|Context $context = []): self
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid JSON payload provided.');
        }

        // Support both direct definition or wrapper with definition key
        if (isset($data['definition']) && is_array($data['definition'])) {
            $defData = $data['definition'];
            if (isset($data['state'])) {
                $defData['state'] = $data['state'];
            }
            if (isset($data['context']) && empty($context)) {
                $context = $data['context'];
            }
            return self::fromArray($defData, $context);
        }

        return self::fromArray($data, $context);
    }
}
