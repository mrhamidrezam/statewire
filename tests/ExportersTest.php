<?php

declare(strict_types=1);

namespace StateWire\Tests;

use StateWire\StateMachine;

class ExportersTest extends TestCase
{
    public function testMermaidDiagramExport(): void
    {
        $machine = StateMachine::builder('workflow')
            ->initial('draft')
            ->state('review')
            ->finalState('published')
            ->transition('draft', 'review', 'submit', label: 'Submit draft')
            ->transition('review', 'published', 'approve')
            ->build();

        $mermaid = $machine->toMermaid();

        $this->assertStringContains('stateDiagram-v2', $mermaid);
        $this->assertStringContains('[*] --> draft', $mermaid);
        $this->assertStringContains('draft --> review : Submit draft', $mermaid);
        $this->assertStringContains('review --> published : approve', $mermaid);
        $this->assertStringContains('published --> [*]', $mermaid);
        $this->assertStringContains('class draft activeState', $mermaid);
    }

    public function testPlantUmlExport(): void
    {
        $machine = StateMachine::builder('order')
            ->initial('created')
            ->finalState('shipped')
            ->transition('created', 'shipped', 'dispatch')
            ->build();

        $plantUml = $machine->toPlantUml();

        $this->assertStringContains('@startuml', $plantUml);
        $this->assertStringContains('@enduml', $plantUml);
        $this->assertStringContains('[*] --> created', $plantUml);
        $this->assertStringContains('created --> shipped : dispatch', $plantUml);
        $this->assertStringContains('shipped --> [*]', $plantUml);
    }

    public function testDotExport(): void
    {
        $machine = StateMachine::builder('graph')
            ->initial('s1')
            ->finalState('s2')
            ->transition('s1', 's2', 'hop')
            ->build();

        $dot = $machine->toDot();

        $this->assertStringContains('digraph StateMachine', $dot);
        $this->assertStringContains('__start -> s1;', $dot);
        $this->assertStringContains('s2 [shape=doublecircle];', $dot);
        $this->assertStringContains('s1 -> s2 [label="hop"];', $dot);
    }
}
