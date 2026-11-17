<?php

declare(strict_types=1);

namespace StateWire;

/**
 * Fluent builder for creating StateMachine instances.
 */
class MachineBuilder
{
    private string $initialState = '';
    /** @var array<string, bool> */
    private array $states = [];
    /** @var list<Transition> */
    private array $transitions = [];

    /** @var array<string, list<callable(Context, array<string, mixed>): void>> */
    private array $enterCallbacks = [];
    /** @var array<string, list<callable(Context, array<string, mixed>): void>> */
    private array $exitCallbacks = [];

    /** @var list<callable(string, string, string, Context, array<string, mixed>): void> */
    private array $beforeHooks = [];
    /** @var list<callable(string, string, string, Context, array<string, mixed>): void> */
    private array $afterHooks = [];

    public function __construct(private readonly string $name = 'default')
    {
    }

    public function initial(string $state): self
    {
        $this->initialState = $state;
        $this->states[$state] = $this->states[$state] ?? false;
        return $this;
    }

    public function state(string $state): self
    {
        if (!isset($this->states[$state])) {
            $this->states[$state] = false;
        }
        return $this;
    }

    public function finalState(string $state): self
    {
        $this->states[$state] = true;
        return $this;
    }

    /**
     * @param string|list<string> $from
     * @param string $to
     * @param string $event
     * @param list<Guard|callable(Context, array<string, mixed>): bool> $guards
     * @param list<callable(Context, array<string, mixed>, Transition): void> $actions
     * @param string|null $label
     */
    public function transition(
        string|array $from,
        string $to,
        string $event,
        array $guards = [],
        array $actions = [],
        ?string $label = null
    ): self {
        $this->transitions[] = new Transition(
            from: $from,
            to: $to,
            event: $event,
            guards: $guards,
            actions: $actions,
            label: $label
        );
        return $this;
    }

    /**
     * Register hook executed when entering a specific state.
     *
     * @param callable(Context, array<string, mixed>): void $callback
     */
    public function onEnter(string $state, callable $callback): self
    {
        $this->enterCallbacks[$state][] = $callback;
        return $this;
    }

    /**
     * Register hook executed when exiting a specific state.
     *
     * @param callable(Context, array<string, mixed>): void $callback
     */
    public function onExit(string $state, callable $callback): self
    {
        $this->exitCallbacks[$state][] = $callback;
        return $this;
    }

    /**
     * Global hook executed before any state transition begins.
     *
     * @param callable(string $event, string $from, string $to, Context $ctx, array<string, mixed> $payload): void $hook
     */
    public function beforeTransition(callable $hook): self
    {
        $this->beforeHooks[] = $hook;
        return $this;
    }

    /**
     * Global hook executed after any state transition completes.
     *
     * @param callable(string $event, string $from, string $to, Context $ctx, array<string, mixed> $payload): void $hook
     */
    public function afterTransition(callable $hook): self
    {
        $this->afterHooks[] = $hook;
        return $this;
    }

    /**
     * Build the configured StateMachine instance.
     *
     * @param array<string, mixed>|Context $context
     */
    public function build(array|Context $context = []): StateMachine
    {
        $initial = $this->initialState;
        if ($initial === '') {
            $keys = array_keys($this->states);
            $initial = !empty($keys) ? $keys[0] : 'initial';
        }

        $allStates = array_keys($this->states);
        $finalStates = [];
        foreach ($this->states as $s => $isFinal) {
            if ($isFinal) {
                $finalStates[] = $s;
            }
        }

        $definition = new MachineDefinition(
            name: $this->name,
            initialState: $initial,
            states: $allStates,
            finalStates: $finalStates,
            transitions: $this->transitions
        );

        return new StateMachine(
            definition: $definition,
            context: $context,
            enterCallbacks: $this->enterCallbacks,
            exitCallbacks: $this->exitCallbacks,
            beforeHooks: $this->beforeHooks,
            afterHooks: $this->afterHooks
        );
    }
}
