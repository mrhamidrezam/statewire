<?php

declare(strict_types=1);

namespace StateWire\Tests;

class CliTest extends TestCase
{
    private string $bin;
    private string $exampleJson;

    public function __construct()
    {
        $this->bin = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../bin/statewire');
        $this->exampleJson = escapeshellarg(__DIR__ . '/../examples/workflow.json');
    }

    public function testCliVersion(): void
    {
        $output = shell_exec("{$this->bin} version 2>&1");
        $this->assertStringContains('StateWire version 1.0.0', (string) $output);
    }

    public function testCliHelp(): void
    {
        $output = shell_exec("{$this->bin} help 2>&1");
        $this->assertStringContains('StateWire CLI', (string) $output);
        $this->assertStringContains('Commands:', (string) $output);
    }

    public function testCliInspect(): void
    {
        $output = shell_exec("{$this->bin} inspect {$this->exampleJson} 2>&1");
        $this->assertStringContains('Machine Name    : OrderWorkflow', (string) $output);
        $this->assertStringContains('Initial State   : draft', (string) $output);
        $this->assertStringContains('Total States    : 6', (string) $output);
    }

    public function testCliValidate(): void
    {
        $output = shell_exec("{$this->bin} validate {$this->exampleJson} 2>&1");
        $this->assertStringContains('✔ Topology Valid!', (string) $output);
    }

    public function testCliMermaid(): void
    {
        $output = shell_exec("{$this->bin} mermaid {$this->exampleJson} 2>&1");
        $this->assertStringContains('stateDiagram-v2', (string) $output);
        $this->assertStringContains('[*] --> draft', (string) $output);
    }

    public function testCliSimulateSequence(): void
    {
        $output = shell_exec("{$this->bin} simulate {$this->exampleJson} --events=submit,payment_received,ship,deliver 2>&1");
        $this->assertStringContains("draft ➔ pending_payment", (string) $output);
        $this->assertStringContains("pending_payment ➔ processing", (string) $output);
        $this->assertStringContains("processing ➔ shipped", (string) $output);
        $this->assertStringContains("shipped ➔ delivered", (string) $output);
        $this->assertStringContains("Simulation Completed! Final State: [delivered] (Final State Achieved)", (string) $output);
    }
}
