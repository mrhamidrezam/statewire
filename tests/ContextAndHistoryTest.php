<?php

declare(strict_types=1);

namespace StateWire\Tests;

use StateWire\StateMachine;
use StateWire\Context;

class ContextAndHistoryTest extends TestCase
{
    public function testContextMethodsAndArrayAccess(): void
    {
        $ctx = new Context(['user' => 'alice', 'role' => 'admin']);

        $this->assertSame('alice', $ctx->get('user'));
        $this->assertSame('admin', $ctx['role']);
        $this->assertTrue(isset($ctx['user']));
        $this->assertFalse(isset($ctx['non_existent']));

        $ctx->set('status', 'active');
        $this->assertSame('active', $ctx->get('status'));

        $ctx['age'] = 30;
        $this->assertSame(30, $ctx['age']);

        unset($ctx['role']);
        $this->assertFalse($ctx->has('role'));

        $ctx->merge(['team' => 'core']);
        $this->assertSame('core', $ctx->get('team'));
        $this->assertSame('alice', $ctx->get('user'));
    }

    public function testTransitionHistoryAuditTrail(): void
    {
        $machine = StateMachine::builder('audited')
            ->initial('s1')
            ->state('s2')
            ->state('s3')
            ->transition('s1', 's2', 'step1')
            ->transition('s2', 's3', 'step2')
            ->build(['job_id' => 101]);

        $machine->apply('step1', ['meta' => 'foo']);
        $machine->apply('step2', ['meta' => 'bar']);

        $history = $machine->getHistory();
        $this->assertCount(2, $history);

        $first = $history[0];
        $this->assertSame('step1', $first->event);
        $this->assertSame('s1', $first->fromState);
        $this->assertSame('s2', $first->toState);
        $this->assertSame(['meta' => 'foo'], $first->payload);
        $this->assertTrue($first->durationMs >= 0.0);

        $second = $history[1];
        $this->assertSame('step2', $second->event);
        $this->assertSame('s2', $second->fromState);
        $this->assertSame('s3', $second->toState);
        $this->assertSame(['meta' => 'bar'], $second->payload);

        $serialized = $first->jsonSerialize();
        $this->assertSame('step1', $serialized['event']);
        $this->assertTrue(isset($serialized['iso_time']));
    }
}
