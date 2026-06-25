<?php
// Filtered firmware list endpoint for modellbahn-displays.de
//
// Returns a JSON array of the device.json contents of every firmware
// directory that matches the requested project / display / controller.
//
// Firmware directories are named  prj-disp-ctrl-version  and live next to
// this script (same layout as index.php).
//
// Usage:  list.php?prj=tankstellenanzeige&disp=h0&ctrl=universal

header('Content-Type: application/json; charset=utf-8');
// CORS (Access-Control-Allow-Origin) wird zentral in der .htaccess gesetzt.

// Directory that holds the firmware folders. On the server this is the
// firmware/ directory this script lives in.
$FIRMWARE_DIR = __DIR__;

// Whitelist GET parameters: lowercase, only [a-z0-9]. This neutralises path
// traversal, the dash separator and glob metacharacters in one step.
function clean_param($name) {
    $value = isset($_GET[$name]) ? (string) $_GET[$name] : '';
    return preg_replace('/[^a-z0-9]/', '', strtolower($value));
}

$prj  = clean_param('prj');
$disp = clean_param('disp');
$ctrl = clean_param('ctrl');

// All three parts are required to build a meaningful filter.
if ($prj === '' || $disp === '' || $ctrl === '') {
    echo json_encode([]);
    exit;
}

$prefix = $prj . '-' . $disp . '-' . $ctrl . '-';

$devices = [];

foreach (glob($FIRMWARE_DIR . '/' . $prefix . '*', GLOB_ONLYDIR) as $dir) {
    $deviceJsonPath = $dir . '/device.json';
    if (!is_file($deviceJsonPath)) {
        // No device.json -> skip this directory entirely.
        continue;
    }

    $raw = file_get_contents($deviceJsonPath);
    if ($raw === false) {
        continue;
    }

    $device = json_decode($raw, true);
    if ($device === null && json_last_error() !== JSON_ERROR_NONE) {
        // Broken device.json -> skip rather than emit invalid data.
        continue;
    }

    $devices[] = $device;
}

// Newest firmware first.
usort($devices, function ($a, $b) {
    $va = isset($a['version']) ? (string) $a['version'] : '0';
    $vb = isset($b['version']) ? (string) $b['version'] : '0';
    return version_compare($vb, $va);
});

echo json_encode($devices, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
