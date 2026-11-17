<?php

declare(strict_types=1);

namespace StateWire\Tests;

use StateWire\StateMachine;
use StateWire\Context;

class HookLifecycleTest extends TestCase
{
    public function testCompleteLifecycleHookExecutionOrder(): void
    {
        $log = [];

        $machine = StateMachine::builder('lifecycle')
            ->initial('idle')
            ->state('running')
            ->finalState('done')
            ->transition('idle', 'running', 'start', actions: [
                function () use (&$log) {
                    $log[] = 'action:idle->running';
                }
            ])
            ->beforeTransition(function ($event, $from, $to) use (&$log) {
                $log[] = "before:{$event}({$from}->{$to})";
            })
            ->afterTransition(function ($event, $from, $to) use (&$log) {
                $log[] = "after:{$event}({$from}->{$to})";
            })
            ->onExit('idle', function () use (&$log) {
                $log[] = 'exit:idle';
            })
            ->onEnter('running', function () use (&$log) {
                $log[] = 'enter:running';
            })
            ->build();

        $machine->apply('start');

        $expected = [
            'before:start(idle->running)',
            'exit:idle',
            'action:idle->running',
            'enter:running',
            'after:start(idle->running)',
        ];

        $this->assertSame($expected, $log);
        $this->assertSame('running', $machine->getState());
    }

    public function testActionMutatesContext(): void
    {
        $machine = StateMachine::builder('counter')
            ->initial('zero')
            ->state('one')
            ->transition('zero', 'one', 'increment', actions: [
                function (Context $ctx, array $payload): void {
                    $val = $ctx->get('count', 0);
                    $step = $payload['step'] ?? 1;
                    $ctx->set('count', $val + $step);
                }
            ])
            ->build(['count' => 10]);

        $machine->apply('increment', ['step' => 5]);

        $this->assertSame(15, $machine->getContext()->get('count'));
    }
}
