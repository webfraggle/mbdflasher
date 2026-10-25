<?php
// Firmware catalog for the MBD Flasher (replaces the former static index.json,
// kept as index-old.json for rollback).
//
// Every firmware directory in firmware/ is scanned. A directory is listed
// when it contains a valid device.json (same format as one entry of the old
// index.json) and its firmware.bin. The output is a JSON array of all
// device.json contents. api/flash_verify/ applies the same rules.
//
// Usage:  https://www.modellbahn-displays.de/firmware/api/firmware_list/all/

header('Content-Type: application/json; charset=utf-8');

// firmware/api/firmware_list/all -> firmware/
$FIRMWARE_DIR = dirname(__DIR__, 3);

$firmwares = [];

foreach (glob($FIRMWARE_DIR . '/*', GLOB_ONLYDIR) as $path) {
    if (!is_file($path . '/firmware.bin')) {
        // No binary -> the flasher could not download anything.
        continue;
    }

    $raw = @file_get_contents($path . '/device.json');
    $fw = $raw === false ? null : json_decode($raw, true);
    if (!is_array($fw) || !isset($fw['id'], $fw['project_id'], $fw['family_id'], $fw['version'])) {
        // Missing or broken device.json -> skip this directory.
        continue;
    }

    $firmwares[] = $fw;
}

// Oldest first, like the chronologically grown index.json.
usort($firmwares, function ($a, $b) {
    return version_compare((string) $a['version'], (string) $b['version'])
        ?: ($a['id'] <=> $b['id']);
});

echo json_encode($firmwares, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
