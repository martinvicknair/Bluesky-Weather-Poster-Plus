<?php

/**
 * File: vendor/autoload.php
 * Fallback PSR‑4 autoloader for BWPP when Composer has not been run yet.
 * When you later execute `composer install`, Composer will overwrite this file
 * with its own generated autoloader.
 */

defined('ABSPATH') || exit;

// If Composer’s ClassLoader is already present, bail—this file is only a stub.
if (class_exists('\\Composer\\Autoload\\ClassLoader', false)) {
    return;
}

// -----------------------------------------------------------------------------
// Minimal PSR‑4 autoload for the BWPP\ namespace
// -----------------------------------------------------------------------------

spl_autoload_register(static function (string $class): void {
    $prefix   = 'BWPP\\';
    $base_dir = defined('BWPP_PATH') ? BWPP_PATH . 'includes/' : __DIR__ . '/../includes/';

    // Only handle classes in our own namespace.
    $len = strlen($prefix);
    if (0 !== strncmp($prefix, $class, $len)) {
        return;
    }

    // Remove namespace prefix, convert namespace separators to directory slashes.
    $relative = substr($class, $len);
    $file     = $base_dir . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
