<?php

declare(strict_types=1);

namespace StateWire\Exporters;

use StateWire\MachineDefinition;

/**
 * Exports state machine definition to PlantUML state diagram format.
 */
class PlantUmlExporter
{
    public static function export(MachineDefinition $definition, ?string $currentState = null): string
    {
        $lines = [
            "@startuml",
            "skinparam state {",
            "  BackgroundColor LightSkyBlue",
            "  BorderColor DarkSlateGray",
            "  ArrowColor Navy",
            "}",
            "[*] --> " . self::escape($definition->initialState),
        ];

        $allStates = $definition->getStates();

        foreach ($definition->getTransitions() as $t) {
            $label = $t->event;
            if ($t->label !== null) {
                $label = $t->label;
            }
            if (!empty($t->guards)) {
                $label .= " [guard]";
            }

            foreach ($t->fromStates as $from) {
                if ($from === '*') {
                    foreach ($allStates as $st) {
                        if ($st !== $t->to) {
                            $lines[] = sprintf("%s --> %s : %s", self::escape($st), self::escape($t->to), $label);
                        }
                    }
                } else {
                    $lines[] = sprintf("%s --> %s : %s", self::escape($from), self::escape($t->to), $label);
                }
            }
        }

        foreach ($definition->getFinalStates() as $finalState) {
            $lines[] = self::escape($finalState) . " --> [*]";
        }

        if ($currentState !== null && $definition->hasState($currentState)) {
            $lines[] = "state " . self::escape($currentState) . " #YellowGreen";
        }

        $lines[] = "@enduml";
        return implode("\n", $lines) . "\n";
    }

    private static function escape(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $name) ?: 'state';
    }
}
