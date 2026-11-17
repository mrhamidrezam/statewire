<?php

declare(strict_types=1);

namespace StateWire\Exporters;

use StateWire\MachineDefinition;

/**
 * Exports state machine definition to Graphviz DOT format.
 */
class DotExporter
{
    public static function export(MachineDefinition $definition, ?string $currentState = null): string
    {
        $lines = [
            "digraph StateMachine {",
            "    rankdir=LR;",
            "    node [shape=circle, fontname=\"Helvetica\", fontsize=10];",
            "    edge [fontname=\"Helvetica\", fontsize=9];",
            "    __start [shape=point, width=0.2];",
            "    __start -> " . self::escape($definition->initialState) . ";",
        ];

        // Format final states with double circle
        foreach ($definition->getFinalStates() as $finalState) {
            $lines[] = "    " . self::escape($finalState) . " [shape=doublecircle];";
        }

        // Highlight current state
        if ($currentState !== null && $definition->hasState($currentState)) {
            $lines[] = "    " . self::escape($currentState) . " [style=filled, fillcolor=\"#bbf7d0\", color=\"#15803d\", penwidth=2];";
        }

        $allStates = $definition->getStates();
        foreach ($definition->getTransitions() as $t) {
            $label = $t->event;
            if ($t->label !== null) {
                $label = $t->label;
            }
            if (!empty($t->guards)) {
                $label .= " [G]";
            }

            foreach ($t->fromStates as $from) {
                if ($from === '*') {
                    foreach ($allStates as $st) {
                        if ($st !== $t->to) {
                            $lines[] = sprintf('    %s -> %s [label="%s"];', self::escape($st), self::escape($t->to), addslashes($label));
                        }
                    }
                } else {
                    $lines[] = sprintf('    %s -> %s [label="%s"];', self::escape($from), self::escape($t->to), addslashes($label));
                }
            }
        }

        $lines[] = "}";
        return implode("\n", $lines) . "\n";
    }

    private static function escape(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $name) ?: 'state';
    }
}
