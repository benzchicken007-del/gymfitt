<?php
include "config/config.php";

$data = json_decode(file_get_contents("php://input"), true);

$userAngles = $data['user'];
$template = $data['template'];

$totalError = 0;

foreach($template as $key => $value){
    $totalError += abs($userAngles[$key] - $value);
}

$avgError = $totalError / count($template);

echo json_encode([
    "error"=>$avgError,
    "pass"=>$avgError < 15
]);
?>
