<?php

declare(strict_types=1);

namespace StateWire\Exporters;

use StateWire\MachineDefinition;

/**
 * Exports state machine definition to Mermaid stateDiagram-v2 format.
 */
class MermaidExporter
{
    public static function export(MachineDefinition $definition, ?string $currentState = null): string
    {
        $lines = ["stateDiagram-v2"];

        // Initial state pointer
        $lines[] = "    [*] --> " . self::escape($definition->initialState);

        $transitions = $definition->getTransitions();
        $allStates = $definition->getStates();

        foreach ($transitions as $t) {
            $eventLabel = $t->event;
            if ($t->label !== null) {
                $eventLabel = $t->label;
            }
            if (!empty($t->guards)) {
                $eventLabel .= " [guarded]";
            }

            foreach ($t->fromStates as $from) {
                if ($from === '*') {
                    foreach ($allStates as $st) {
                        if ($st !== $t->to) {
                            $lines[] = sprintf("    %s --> %s : %s", self::escape($st), self::escape($t->to), $eventLabel);
                        }
                    }
                } else {
                    $lines[] = sprintf("    %s --> %s : %s", self::escape($from), self::escape($t->to), $eventLabel);
                }
            }
        }

        // Final states
        foreach ($definition->getFinalStates() as $finalState) {
            $lines[] = "    " . self::escape($finalState) . " --> [*]";
        }

        // Active state styling if requested
        if ($currentState !== null && $definition->hasState($currentState)) {
            $lines[] = "    classDef activeState fill:#22c55e,stroke:#15803d,stroke-width:2px,color:#ffffff,font-weight:bold;";
            $lines[] = "    class " . self::escape($currentState) . " activeState";
        }

        return implode("\n", $lines) . "\n";
    }

    private static function escape(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $name) ?: 'state';
    }
}
