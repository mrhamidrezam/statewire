<?php

declare(strict_types=1);

namespace StateWire\Tests;

use StateWire\StateMachine;

class SerializationTest extends TestCase
{
    public function testJsonSerializationAndDeserialization(): void
    {
        $original = StateMachine::builder('payment_pipeline')
            ->initial('unpaid')
            ->state('processing')
            ->finalState('settled')
            ->transition('unpaid', 'processing', 'authorize')
            ->transition('processing', 'settled', 'capture')
            ->build(['invoice_id' => 'INV-99']);

        $original->apply('authorize');
        $json = $original->toJson();

        $restored = StateMachine::fromJson($json);

        $this->assertSame('processing', $restored->getState());
        $this->assertSame('INV-99', $restored->getContext()->get('invoice_id'));
        $this->assertTrue($restored->can('capture'));
        $this->assertFalse($restored->can('authorize'));

        $restored->apply('capture');
        $this->assertSame('settled', $restored->getState());
        $this->assertTrue($restored->isFinal());
    }

    public function testFromArrayInstantiation(): void
    {
        $schema = [
            'name' => 'ticket_flow',
            'initial_state' => 'open',
            'states' => ['open', 'investigating', 'resolved'],
            'final_states' => ['resolved'],
            'transitions' => [
                ['from' => 'open', 'to' => 'investigating', 'event' => 'assign'],
                ['from' => 'investigating', 'to' => 'resolved', 'event' => 'resolve'],
            ],
        ];

        $machine = StateMachine::fromArray($schema, ['priority' => 'high']);

        $this->assertSame('open', $machine->getState());
        $this->assertSame('high', $machine->getContext()->get('priority'));
        $this->assertTrue($machine->can('assign'));

        $machine->apply('assign');
        $this->assertSame('investigating', $machine->getState());
    }
}
