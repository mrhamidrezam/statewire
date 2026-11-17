<?php

declare(strict_types=1);

namespace StateWire\Tests;

use StateWire\StateMachine;
use StateWire\Analysis\GraphAuditor;

class GraphAuditorTest extends TestCase
{
    public function testValidGraphPassesAudit(): void
    {
        $machine = StateMachine::builder('valid')
            ->initial('draft')
            ->state('published')
            ->finalState('archived')
            ->transition('draft', 'published', 'publish')
            ->transition('published', 'archived', 'archive')
            ->build();

        $result = $machine->validate();

        $this->assertTrue($result->isValid());
        $this->assertCount(0, $result->unreachableStates);
        $this->assertCount(0, $result->deadEndStates);
        $this->assertTrue($result->hasFinalStateReachable);
    }

    public function testDetectsUnreachableAndDeadEndStates(): void
    {
        $machine = StateMachine::builder('broken')
            ->initial('init')
            ->state('stuck') // non-final with no outbound transitions = dead end
            ->state('ghost') // unreachable from init
            ->finalState('goal')
            ->transition('init', 'stuck', 'proceed')
            ->transition('ghost', 'goal', 'finish')
            ->build();

        $result = $machine->validate();

        $this->assertFalse($result->isValid());
        $this->assertContains('ghost', $result->unreachableStates);
        $this->assertContains('goal', $result->unreachableStates);
        $this->assertContains('stuck', $result->deadEndStates);
        $this->assertContains('goal', $result->unreachableFinalStates);
        $this->assertFalse($result->hasFinalStateReachable);
    }

    public function testDuplicateTransitionDetection(): void
    {
        $machine = StateMachine::builder('dupe')
            ->initial('a')
            ->state('b')
            ->state('c')
            ->transition('a', 'b', 'trigger')
            ->transition('a', 'c', 'trigger') // Duplicate trigger on same state with no guard!
            ->build();

        $result = $machine->validate();
        $this->assertFalse($result->isValid());
        $this->assertCount(1, $result->warnings);
        $this->assertStringContains("Duplicate transition for event 'trigger'", $result->warnings[0]);
    }
}
