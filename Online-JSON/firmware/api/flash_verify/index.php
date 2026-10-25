<?php
// Pre-flash check of the MBD Flasher.
//
// The flasher POSTs {"firmware_id": ...} and expects
//   {"status": "success", "message": "<checksum of firmware.bin>"}
// for a firmware that is still offered, otherwise {"status": "failed"}.
//
// The firmware is looked up in the device.json files of the firmware
// directories, with the same rules as api/firmware_list/all/index.php
// (device.json + firmware.bin must exist).

header('Content-Type: application/json; charset=utf-8');

// firmware/api/flash_verify -> firmware/
$FIRMWARE_DIR = dirname(__DIR__, 2);

$data = json_decode(file_get_contents('php://input'), true);
$firmwareId = is_array($data) && isset($data['firmware_id']) ? $data['firmware_id'] : null;

$out = ['status' => 'failed'];

if ($firmwareId !== null) {
    foreach (glob($FIRMWARE_DIR . '/*', GLOB_ONLYDIR) as $path) {
        if (!is_file($path . '/firmware.bin')) {
            continue;
        }

        $raw = @file_get_contents($path . '/device.json');
        $fw = $raw === false ? null : json_decode($raw, true);
        if (!is_array($fw) || !isset($fw['id'], $fw['checksum'])) {
            continue;
        }

        if ($fw['id'] == $firmwareId) {
            $out['status'] = 'success';
            $out['message'] = $fw['checksum'];
            break;
        }
    }
}

echo json_encode($out);
