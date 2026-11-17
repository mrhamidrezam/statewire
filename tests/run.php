<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';
require_once __DIR__ . '/TestCase.php';

$testFiles = glob(__DIR__ . '/*Test.php');
if (empty($testFiles)) {
    echo "No test files found.\n";
    exit(1);
}

$startTime = microtime(true);
$totalTests = 0;
$totalAssertions = 0;
$failedTests = 0;
$failures = [];

echo "\n=== StateWire Test Suite ===\n\n";

foreach ($testFiles as $file) {
    require_once $file;
    $filename = basename($file);
    echo $filename . "\n";

    $classes = get_declared_classes();
    $testClasses = array_filter($classes, static function ($cls) {
        return is_subclass_of($cls, StateWire\Tests\TestCase::class);
    });

    foreach ($testClasses as $cls) {
        $reflection = new ReflectionClass($cls);
        if ($reflection->isAbstract() || $reflection->getFileName() !== realpath($file)) {
            continue;
        }

        $instance = new $cls();
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $m) {
            if (!str_starts_with($m->getName(), 'test')) {
                continue;
            }

            $totalTests++;
            $methodName = $m->getName();

            try {
                $instance->resetAssertionCount();
                $instance->{$methodName}();
                $totalAssertions += $instance->getAssertionCount();
                echo "  ✔ {$methodName}\n";
            } catch (Throwable $e) {
                $failedTests++;
                $totalAssertions += $instance->getAssertionCount();
                echo "  ✖ {$methodName} (FAILED)\n";
                $failures[] = [
                    'class' => $cls,
                    'method' => $methodName,
                    'file' => $file,
                    'line' => $e->getLine(),
                    'message' => $e->getMessage(),
                ];
            }
        }
    }
    echo "\n";
}

$totalTime = round(microtime(true) - $startTime, 3);
echo str_repeat('─', 50) . "\n";

if ($failedTests === 0) {
    echo "OK ({$totalTests} tests, {$totalAssertions} assertions, {$totalTime} s)\n\n";
    exit(0);
} else {
    echo "FAILURES! (Tests: {$totalTests}, Assertions: {$totalAssertions}, Failures: {$failedTests}, Time: {$totalTime} s)\n\n";
    foreach ($failures as $f) {
        echo "{$f['class']}::{$f['method']} (Line {$f['line']})\n";
        echo "  " . $f['message'] . "\n\n";
    }
    exit(1);
}
