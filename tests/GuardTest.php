<?php

declare(strict_types=1);

namespace StateWire\Tests;

use StateWire\StateMachine;
use StateWire\Guard;
use StateWire\Context;
use StateWire\Exceptions\GuardRejectedException;

class GuardTest extends TestCase
{
    public function testPassingGuardAllowsTransition(): void
    {
        $machine = StateMachine::builder('guarded')
            ->initial('draft')
            ->state('published')
            ->transition('draft', 'published', 'publish', [
                fn(Context $ctx) => $ctx->get('is_approved', false) === true,
            ])
            ->build(['is_approved' => true]);

        $this->assertTrue($machine->can('publish'));
        $result = $machine->transition('publish');
        $this->assertTrue($result->successful);
        $this->assertSame('published', $machine->getState());
    }

    public function testFailingGuardBlocksTransition(): void
    {
        $machine = StateMachine::builder('guarded')
            ->initial('draft')
            ->state('published')
            ->transition('draft', 'published', 'publish', [
                Guard::create(
                    fn(Context $ctx) => $ctx->get('is_approved', false) === true,
                    'Document is not yet approved'
                ),
            ])
            ->build(['is_approved' => false]);

        $this->assertFalse($machine->can('publish'));

        $result = $machine->transition('publish');
        $this->assertFalse($result->successful);
        $this->assertSame('Document is not yet approved', $result->failureReason);
        $this->assertSame('draft', $machine->getState());

        $this->assertThrows(
            GuardRejectedException::class,
            fn() => $machine->apply('publish'),
            'Document is not yet approved'
        );
    }

    public function testMultipleGuardsAllMustPass(): void
    {
        $machine = StateMachine::builder('multi_guard')
            ->initial('cart')
            ->state('checkout')
            ->transition('cart', 'checkout', 'proceed', [
                Guard::create(fn(Context $ctx) => $ctx->get('has_items', false), 'Cart is empty'),
                Guard::create(fn(Context $ctx) => $ctx->get('email_verified', false), 'Email unverified'),
            ])
            ->build([
                'has_items' => true,
                'email_verified' => false,
            ]);

        $result = $machine->transition('proceed');
        $this->assertFalse($result->successful);
        $this->assertSame('Email unverified', $result->failureReason);

        // Now verify email and retry
        $machine->getContext()->set('email_verified', true);
        $this->assertTrue($machine->can('proceed'));
        $machine->apply('proceed');
        $this->assertSame('checkout', $machine->getState());
    }

    public function testGuardEvaluatesTransitionPayload(): void
    {
        $machine = StateMachine::builder('payload_guard')
            ->initial('locked')
            ->state('unlocked')
            ->transition('locked', 'unlocked', 'unlock', [
                Guard::create(
                    fn(Context $ctx, array $payload) => ($payload['key'] ?? '') === 'secret-123',
                    'Invalid security key'
                ),
            ])
            ->build();

        $this->assertFalse($machine->can('unlock', ['key' => 'wrong']));
        $this->assertTrue($machine->can('unlock', ['key' => 'secret-123']));

        $res = $machine->transition('unlock', ['key' => 'wrong']);
        $this->assertFalse($res->successful);

        $machine->apply('unlock', ['key' => 'secret-123']);
        $this->assertSame('unlocked', $machine->getState());
    }
}
