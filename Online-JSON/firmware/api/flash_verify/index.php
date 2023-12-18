<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
$json = file_get_contents('php://input');

$data = json_decode($json);

$firmwareFile = file_get_contents("../firmware_list/all/index.json");

$firmwares = json_decode($firmwareFile);

// print_r($data);
// print_r($firmwares);
$out = [];
$out['status'] = "failed";

foreach ($firmwares as $key => $firmware) {
    // print_r($firmware);
    // print $data->firmware_id;
    if ($data->firmware_id == $firmware->id)
    {
        $out['status'] = "success";
        $out['message'] = $firmware->checksum;
        break;
    }
}
print json_encode($out);
exit;

?>