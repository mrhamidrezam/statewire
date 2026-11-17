<?php

declare(strict_types=1);

/**
 * StateWire Standalone Autoloader (Zero-dependency PSR-4 loader).
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'StateWire\\';
    $baseDir = __DIR__ . DIRECTORY_SEPARATOR;

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
