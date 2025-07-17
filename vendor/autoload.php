<?php

/**
 * Lightweight fallback autoloader for BWPP when Composer is not installed.
 *
 * This file mimics the location Composer would generate (vendor/autoload.php)
 * so the bootstrap continues to work. When you eventually run `composer
 * install`, Composer’s real autoloader will overwrite this file.
 *
 * @package BWPP
 */

// Abort if BWPP_PATH isn’t defined (should be set in bootstrap).
if (! defined('BWPP_PATH')) {
    return;
}

spl_autoload_register(static function (string $class): void {
    // Only autoload our namespace.
    $prefix = 'BWPP\\';
    $len    = strlen($prefix);

    if (0 !== strncmp($prefix, $class, $len)) {
        return; // Different vendor – ignore.
    }

    // Strip namespace prefix, convert to path.
    $relative = substr($class, $len);
    $file     = BWPP_PATH . 'includes/' . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
