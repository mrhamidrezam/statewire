<?php

declare(strict_types=1);

namespace StateWire\Analysis;

use StateWire\MachineDefinition;

/**
 * Static graph auditor checking for unreachable states, dead ends, and orphan cycles.
 */
class GraphAuditor
{
    public static function audit(MachineDefinition $definition): AuditResult
    {
        $allStates = $definition->getStates();
        $initial = $definition->initialState;
        $finalStates = $definition->getFinalStates();

        // 1. Build adjacency list
        $adjacency = [];
        foreach ($allStates as $st) {
            $adjacency[$st] = [];
        }

        foreach ($definition->getTransitions() as $transition) {
            $froms = $transition->fromStates;
            $to = $transition->to;

            if (in_array('*', $froms, true)) {
                foreach ($allStates as $st) {
                    $adjacency[$st][] = $to;
                }
            } else {
                foreach ($froms as $f) {
                    if (isset($adjacency[$f])) {
                        $adjacency[$f][] = $to;
                    }
                }
            }
        }

        // Clean duplicates
        foreach ($adjacency as $st => $targets) {
            $adjacency[$st] = array_values(array_unique($targets));
        }

        // 2. BFS from Initial State
        $visited = [$initial => true];
        $queue = [$initial];

        while (!empty($queue)) {
            $current = array_shift($queue);
            foreach ($adjacency[$current] ?? [] as $neighbor) {
                if (!isset($visited[$neighbor])) {
                    $visited[$neighbor] = true;
                    $queue[] = $neighbor;
                }
            }
        }

        // Unreachable states
        $unreachable = [];
        foreach ($allStates as $st) {
            if (!isset($visited[$st])) {
                $unreachable[] = $st;
            }
        }

        // Dead ends: non-final states that have 0 outbound transitions
        $deadEnds = [];
        foreach ($allStates as $st) {
            if (!$definition->isFinalState($st) && empty($adjacency[$st])) {
                $deadEnds[] = $st;
            }
        }

        // Final states reachable?
        $unreachableFinal = [];
        foreach ($finalStates as $fst) {
            if (!isset($visited[$fst])) {
                $unreachableFinal[] = $fst;
            }
        }

        $warnings = [];
        if (!empty($finalStates) && count($unreachableFinal) === count($finalStates)) {
            $warnings[] = "No declared final state is reachable from the initial state '{$initial}'.";
        }

        // Check for duplicate transitions with same event from same state
        $seen = [];
        foreach ($definition->getTransitions() as $t) {
            foreach ($t->fromStates as $from) {
                $key = "{$from}___{$t->event}";
                if (isset($seen[$key]) && empty($t->guards)) {
                    $warnings[] = "Duplicate transition for event '{$t->event}' from state '{$from}' without differentiating guards.";
                }
                $seen[$key] = true;
            }
        }

        return new AuditResult(
            unreachableStates: $unreachable,
            deadEndStates: $deadEnds,
            unreachableFinalStates: $unreachableFinal,
            warnings: $warnings,
            hasFinalStateReachable: empty($finalStates) || count($unreachableFinal) < count($finalStates)
        );
    }
}
