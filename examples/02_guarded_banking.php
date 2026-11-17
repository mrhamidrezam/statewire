<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';

use StateWire\StateMachine;
use StateWire\Guard;
use StateWire\Context;

// Banking withdrawal with strict guards
$machine = StateMachine::builder('AtmSession')
    ->initial('idle')
    ->state('card_inserted')
    ->state('authenticated')
    ->state('dispensing')
    ->finalState('session_closed')
    ->transition('idle', 'card_inserted', 'insert_card')
    ->transition('card_inserted', 'authenticated', 'enter_pin', [
        Guard::create(
            fn(Context $ctx, array $p) => ($p['pin'] ?? '') === $ctx->get('correct_pin'),
            'Invalid PIN entered'
        ),
    ])
    ->transition('authenticated', 'dispensing', 'withdraw', [
        Guard::create(
            fn(Context $ctx, array $p) => ($p['amount'] ?? 0) <= $ctx->get('balance', 0),
            'Insufficient balance in account'
        ),
        Guard::create(
            fn(Context $ctx, array $p) => ($p['amount'] ?? 0) <= $ctx->get('daily_limit', 500),
            'Daily withdrawal limit exceeded'
        ),
    ], [
        function (Context $ctx, array $p): void {
            $ctx->set('balance', $ctx->get('balance') - $p['amount']);
        }
    ])
    ->transition('dispensing', 'idle', 'finish')
    ->transition('*', 'session_closed', 'eject_card')
    ->build([
        'correct_pin' => '4491',
        'balance' => 400.00,
        'daily_limit' => 300.00,
    ]);

echo "=== StateWire Example 02: Guarded Banking Session ===\n";

$machine->apply('insert_card');
echo "Card inserted. State: " . $machine->getState() . "\n";

// Try wrong PIN
$result = $machine->transition('enter_pin', ['pin' => '0000']);
echo "Wrong PIN attempt: " . ($result->successful ? 'Allowed' : "Blocked! [{$result->failureReason}]") . "\n";

// Correct PIN
$machine->apply('enter_pin', ['pin' => '4491']);
echo "Correct PIN entered. State: " . $machine->getState() . "\n";

// Try withdraw above daily limit
$result = $machine->transition('withdraw', ['amount' => 350.00]);
echo "Withdraw \$350 attempt: " . ($result->successful ? 'Allowed' : "Blocked! [{$result->failureReason}]") . "\n";

// Withdraw valid amount ($200)
$machine->apply('withdraw', ['amount' => 200.00]);
echo "Withdraw \$200 succeeded. State: " . $machine->getState() . "\n";
echo "Remaining Balance: \$" . number_format($machine->getContext()->get('balance'), 2) . "\n";
