<?php

/**
 * Fallback PSR‑4 autoloader for BWPP when Composer has not been run.
 *
 * When you eventually execute `composer install`, Composer’s own autoloader
 * will overwrite this file. Until then, this keeps the plugin functional.
 *
 * @package BWPP
 */

defined('ABSPATH') || exit;

// If Composer is present, do nothing – its autoloader will be required by the
// bootstrap before this fallback ever runs.
if (class_exists('\\Composer\\Autoload\\ClassLoader', false)) {
    return;
}

// -----------------------------------------------------------------------------
// Minimal PSR‑4 autoload for the BWPP\ namespace
// -----------------------------------------------------------------------------

spl_autoload_register(static function (string $class): void {
    $prefix   = 'BWPP\\';
    $base_dir = defined('BWPP_PATH') ? BWPP_PATH . 'includes/' : __DIR__ . '/../includes/';

    // Only handle classes in our namespace.
    $len = strlen($prefix);
    if (0 !== strncmp($prefix, $class, $len)) {
        return;
    }

    // Trim prefix & convert namespace separators to dir separators.
    $relative = substr($class, $len);
    $file     = $base_dir . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
