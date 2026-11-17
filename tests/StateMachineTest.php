<?php

declare(strict_types=1);

namespace StateWire\Tests;

use StateWire\StateMachine;
use StateWire\Exceptions\InvalidTransitionException;
use StateWire\Exceptions\UndefinedStateException;

enum OrderState: string
{
    case Draft = 'draft';
    case Paid = 'paid';
    case Shipped = 'shipped';
}

enum OrderEvent: string
{
    case Pay = 'pay';
    case Ship = 'ship';
}

class StateMachineTest extends TestCase
{
    public function testInitialStateAndFinalStateDetection(): void
    {
        $machine = StateMachine::builder('test')
            ->initial('draft')
            ->state('in_progress')
            ->finalState('completed')
            ->transition('draft', 'in_progress', 'start')
            ->transition('in_progress', 'completed', 'finish')
            ->build();

        $this->assertSame('draft', $machine->getState());
        $this->assertTrue($machine->is('draft'));
        $this->assertFalse($machine->isFinal());

        $machine->apply('start');
        $this->assertSame('in_progress', $machine->getState());
        $this->assertFalse($machine->isFinal());

        $machine->apply('finish');
        $this->assertSame('completed', $machine->getState());
        $this->assertTrue($machine->isFinal());
    }

    public function testWildcardTransitions(): void
    {
        $machine = StateMachine::builder('cancellable')
            ->initial('draft')
            ->state('review')
            ->state('approved')
            ->finalState('cancelled')
            ->transition('draft', 'review', 'submit')
            ->transition('review', 'approved', 'approve')
            ->transition('*', 'cancelled', 'cancel')
            ->build();

        $this->assertTrue($machine->can('cancel'));
        $machine->apply('submit');
        $this->assertSame('review', $machine->getState());
        $this->assertTrue($machine->can('cancel'));

        $machine->apply('cancel');
        $this->assertSame('cancelled', $machine->getState());
    }

    public function testMultiSourceTransitions(): void
    {
        $machine = StateMachine::builder('multi')
            ->initial('step_a')
            ->state('step_b')
            ->state('step_c')
            ->transition(['step_a', 'step_b'], 'step_c', 'jump')
            ->build();

        $this->assertTrue($machine->can('jump'));
        $machine->apply('jump');
        $this->assertSame('step_c', $machine->getState());
    }

    public function testCanMethodDetectsAvailableEvents(): void
    {
        $machine = StateMachine::builder('events')
            ->initial('a')
            ->state('b')
            ->transition('a', 'b', 'go')
            ->build();

        $this->assertTrue($machine->can('go'));
        $this->assertFalse($machine->can('stop'));

        $available = $machine->getAvailableEvents();
        $this->assertContains('go', $available);
        $this->assertCount(1, $available);
    }

    public function testApplyThrowsOnInvalidTransition(): void
    {
        $machine = StateMachine::builder('err')
            ->initial('a')
            ->state('b')
            ->transition('a', 'b', 'next')
            ->build();

        $this->assertThrows(
            InvalidTransitionException::class,
            fn() => $machine->apply('invalid_event'),
            "Cannot apply event 'invalid_event' from state 'a'"
        );
    }

    public function testUndefinedInitialStateThrows(): void
    {
        $builder = StateMachine::builder('bad');
        $this->assertThrows(
            UndefinedStateException::class,
            function () use ($builder) {
                new StateMachine(
                    definition: $builder->build()->getDefinition(),
                    currentState: 'non_existent_state'
                );
            }
        );
    }

    public function testPhpBackedEnumSupport(): void
    {
        $machine = StateMachine::builder('enums')
            ->initial(OrderState::Draft->value)
            ->state(OrderState::Paid->value)
            ->finalState(OrderState::Shipped->value)
            ->transition(OrderState::Draft->value, OrderState::Paid->value, OrderEvent::Pay->value)
            ->transition(OrderState::Paid->value, OrderState::Shipped->value, OrderEvent::Ship->value)
            ->build();

        $this->assertTrue($machine->is(OrderState::Draft));
        $this->assertTrue($machine->can(OrderEvent::Pay));

        $machine->apply(OrderEvent::Pay);
        $this->assertTrue($machine->is(OrderState::Paid));

        $machine->apply(OrderEvent::Ship);
        $this->assertTrue($machine->is(OrderState::Shipped));
        $this->assertTrue($machine->isFinal());
    }
}
