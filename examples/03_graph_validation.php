<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';

use StateWire\StateMachine;

echo "=== StateWire Example 03: Graph Validation & Topology Audit ===\n";

// A faulty machine with unreachable states and dead ends
$faulty = StateMachine::builder('FaultyWorkflow')
    ->initial('start')
    ->state('processing')
    ->state('stuck_state') // dead end (non-final, no outgoing transitions)
    ->state('orphan_state') // unreachable from start
    ->finalState('done')
    ->transition('start', 'processing', 'begin')
    ->transition('processing', 'stuck_state', 'halt')
    ->transition('orphan_state', 'done', 'resolve')
    ->build();

$audit = $faulty->validate();

echo "Auditing FaultyWorkflow:\n";
echo "Is Valid: " . ($audit->isValid() ? 'YES' : 'NO') . "\n";
echo "Unreachable States: " . implode(', ', $audit->unreachableStates) . "\n";
echo "Dead-end States: " . implode(', ', $audit->deadEndStates) . "\n";
echo "Unreachable Final States: " . implode(', ', $audit->unreachableFinalStates) . "\n";
foreach ($audit->warnings as $w) {
    echo "Warning: {$w}\n";
}
