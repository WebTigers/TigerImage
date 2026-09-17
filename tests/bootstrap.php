<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * PHPUnit bootstrap for TigerImage.
 *
 * TigerImage is a Tiger MODULE: its `Tigerimage_*` classes extend `Tiger_*` bases and normally live
 * inside a Tiger app, resolved by ZF1's module loader. To test them in isolation we load a tiger-core
 * checkout's autoloader (Tiger_*, Zend_*, PHPUnit) and register a small module autoloader.
 *
 * Resolve tiger-core via (first hit wins): $TIGER_CORE_VENDOR → $TIGER_CORE_PATH/vendor → a sibling
 * ../tiger-core checkout, which must have had `composer install` run in it.
 */

error_reporting(E_ALL);

if (!defined('APPLICATION_ENV')) { define('APPLICATION_ENV', getenv('APPLICATION_ENV') ?: 'testing'); }

$moduleRoot = dirname(__DIR__);
if (!defined('APPLICATION_ROOT')) { define('APPLICATION_ROOT', $moduleRoot); }

$candidates = array_filter([
    getenv('TIGER_CORE_VENDOR') ?: null,
    getenv('TIGER_CORE_PATH') ? rtrim(getenv('TIGER_CORE_PATH'), '/') . '/vendor/autoload.php' : null,
    $moduleRoot . '/../tiger-core/vendor/autoload.php',
]);
$coreVendor = '';
foreach ($candidates as $c) { if (is_file($c)) { $coreVendor = $c; break; } }
if ($coreVendor === '') {
    fwrite(STDERR, "\nTigerImage tests need tiger-core's autoloader (Tiger_*/Zend_*/PHPUnit).\n"
        . "Set TIGER_CORE_VENDOR, or place tiger-core as a sibling and run `composer install` in it.\n\n");
    exit(1);
}
require $coreVendor;

$coreRoot = dirname($coreVendor, 2);
if (!defined('TIGER_CORE_PATH')) { define('TIGER_CORE_PATH', $coreRoot); }

set_include_path(implode(PATH_SEPARATOR, array_filter([
    $coreRoot . '/vendor/webtigers/tigerzf/library',
    $coreRoot . '/library',
    get_include_path(),
])));

// Tigerimage_* module autoloader (ZF1 module layout -> class names).
spl_autoload_register(static function ($class) use ($moduleRoot) {
    if (strncmp($class, 'Tigerimage_', 11) !== 0) { return; }
    if (preg_match('/^Tigerimage_Service_(.+)$/', $class, $m)) {
        $rel = 'services/' . str_replace('_', '/', $m[1]) . '.php';
    } elseif (preg_match('/^Tigerimage_Adapter_(.+)$/', $class, $m)) {
        $rel = 'adapters/' . str_replace('_', '/', $m[1]) . '.php';
    } elseif (preg_match('/^Tigerimage_Model_(.+)$/', $class, $m)) {
        $rel = 'models/' . str_replace('_', '/', $m[1]) . '.php';
    } elseif (preg_match('/^Tigerimage_Form_(.+)$/', $class, $m)) {
        $rel = 'forms/' . str_replace('_', '/', $m[1]) . '.php';
    } elseif (preg_match('/^Tigerimage_Plugin_(.+)$/', $class, $m)) {
        $rel = 'plugins/' . str_replace('_', '/', $m[1]) . '.php';
    } elseif (preg_match('/^Tigerimage_(.+)Controller$/', $class, $m)) {
        $rel = 'controllers/' . $m[1] . 'Controller.php';
    } else {
        $rel = str_replace('_', '/', substr($class, 11)) . '.php';
    }
    $file = $moduleRoot . '/' . $rel;
    if (is_file($file)) { require $file; }
});

spl_autoload_register(static function ($class) {
    $prefix = 'TigerImage\\Tests\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) { return; }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) { require $file; }
});
