<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';

use StateWire\StateMachine;

$machine = StateMachine::builder('ArticlePublishing')
    ->initial('draft')
    ->state('review')
    ->state('scheduled')
    ->finalState('published')
    ->finalState('archived')
    ->transition('draft', 'review', 'request_review')
    ->transition('review', 'draft', 'request_changes')
    ->transition('review', 'scheduled', 'schedule')
    ->transition('scheduled', 'published', 'publish')
    ->transition('review', 'published', 'publish_immediately')
    ->transition(['draft', 'published'], 'archived', 'archive')
    ->build();

echo "=== StateWire Example 04: Visual Diagram Exporters ===\n\n";

echo "--- 1. Mermaid stateDiagram-v2 ---\n";
echo $machine->toMermaid() . "\n";

echo "--- 2. PlantUML ---\n";
echo $machine->toPlantUml() . "\n";

echo "--- 3. Graphviz DOT ---\n";
echo $machine->toDot() . "\n";
