<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';

use StateWire\StateMachine;
use StateWire\Context;

// 1. Build an order fulfillment state machine
$machine = StateMachine::builder('EcommerceOrder')
    ->initial('draft')
    ->state('pending_payment')
    ->state('processing')
    ->finalState('shipped')
    ->finalState('cancelled')
    ->transition('draft', 'pending_payment', 'submit', [
        fn(Context $ctx) => count($ctx->get('items', [])) > 0,
    ])
    ->transition('pending_payment', 'processing', 'pay', [
        fn(Context $ctx, array $payload) => ($payload['amount'] ?? 0) >= $ctx->get('total', 0),
    ])
    ->transition('processing', 'shipped', 'ship')
    ->transition(['draft', 'pending_payment'], 'cancelled', 'cancel')
    ->onEnter('processing', function (Context $ctx, array $payload): void {
        echo "  [Hook] Notification sent: Payment of \${$payload['amount']} confirmed for order #{$ctx->get('order_id')}!\n";
    })
    ->onEnter('shipped', function (Context $ctx): void {
        echo "  [Hook] Dispatch alert: Order #{$ctx->get('order_id')} is en route!\n";
    })
    ->build([
        'order_id' => 9842,
        'items' => ['Mechanical Keyboard', 'USB-C Cable'],
        'total' => 149.99,
    ]);

echo "=== StateWire Example 01: Order Fulfillment ===\n";
echo "Initial State: " . $machine->getState() . "\n";

// Transition 1: Submit draft
echo "\n1. Submitting draft order...\n";
$machine->apply('submit');
echo "State: " . $machine->getState() . "\n";

// Transition 2: Pay for order with payload
echo "\n2. Submitting payment (\$150.00)...\n";
$machine->apply('pay', ['amount' => 150.00, 'gateway' => 'stripe']);
echo "State: " . $machine->getState() . "\n";

// Transition 3: Warehouse ships
echo "\n3. Warehouse marks shipped...\n";
$machine->apply('ship');
echo "State: " . $machine->getState() . " (Is final: " . ($machine->isFinal() ? 'Yes' : 'No') . ")\n";

echo "\nTransition Audit Log:\n";
foreach ($machine->getHistory() as $record) {
    echo sprintf(
        "  • Event '%s': %s -> %s (time: %.3f ms)\n",
        $record->event,
        $record->fromState,
        $record->toState,
        $record->durationMs
    );
}
