<?php

declare(strict_types=1);

namespace StateWire\Tests;

use Throwable;

/**
 * Lightweight, zero-dependency unit testing base class.
 */
abstract class TestCase
{
    private int $assertions = 0;

    public function getAssertionCount(): int
    {
        return $this->assertions;
    }

    public function resetAssertionCount(): void
    {
        $this->assertions = 0;
    }

    protected function assertTrue(bool $condition, string $message = ''): void
    {
        $this->assertions++;
        if (!$condition) {
            throw new \AssertionError($message ?: 'Failed asserting that condition is true.');
        }
    }

    protected function assertFalse(bool $condition, string $message = ''): void
    {
        $this->assertions++;
        if ($condition) {
            throw new \AssertionError($message ?: 'Failed asserting that condition is false.');
        }
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            $msg = $message ?: sprintf(
                "Failed asserting that %s is identical to %s.",
                var_export($actual, true),
                var_export($expected, true)
            );
            throw new \AssertionError($msg);
        }
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected != $actual) {
            $msg = $message ?: sprintf(
                "Failed asserting that %s equals %s.",
                var_export($actual, true),
                var_export($expected, true)
            );
            throw new \AssertionError($msg);
        }
    }

    protected function assertCount(int $expectedCount, countable|array $haystack, string $message = ''): void
    {
        $this->assertions++;
        $actualCount = count($haystack);
        if ($actualCount !== $expectedCount) {
            $msg = $message ?: sprintf("Failed asserting that array count %d equals expected %d.", $actualCount, $expectedCount);
            throw new \AssertionError($msg);
        }
    }

    protected function assertContains(mixed $needle, array $haystack, string $message = ''): void
    {
        $this->assertions++;
        if (!in_array($needle, $haystack, true)) {
            $msg = $message ?: sprintf("Failed asserting that array contains %s.", var_export($needle, true));
            throw new \AssertionError($msg);
        }
    }

    protected function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertions++;
        if (!str_contains($haystack, $needle)) {
            $msg = $message ?: sprintf("Failed asserting that string contains '%s'.", $needle);
            throw new \AssertionError($msg);
        }
    }

    /**
     * @param class-string<Throwable> $expectedException
     */
    protected function assertThrows(string $expectedException, callable $callback, ?string $expectedMessageContains = null): void
    {
        $this->assertions++;
        try {
            $callback();
        } catch (Throwable $e) {
            if (!($e instanceof $expectedException)) {
                throw new \AssertionError(
                    sprintf("Expected exception of type %s, but caught %s.", $expectedException, get_class($e))
                );
            }
            if ($expectedMessageContains !== null && !str_contains($e->getMessage(), $expectedMessageContains)) {
                throw new \AssertionError(
                    sprintf("Caught %s, but message '%s' did not contain '%s'.", $expectedException, $e->getMessage(), $expectedMessageContains)
                );
            }
            return;
        }

        throw new \AssertionError(sprintf("Failed asserting that exception %s was thrown.", $expectedException));
    }
}
