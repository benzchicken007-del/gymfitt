<?php
session_start();
include "config/config.php";

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])){
    echo json_encode(["error"=>"not_logged_in"]);
    exit();
}

$user_id = $_SESSION['user_id'];
$mission_id = (int)$_POST['mission_id'];
$passed = isset($_POST['passed']) ? (bool)$_POST['passed'] : false;

if($mission_id <= 0){
    echo json_encode(["error"=>"invalid_mission"]);
    exit();
}

if(!$passed){
    echo json_encode(["error"=>"not_passed"]);
    exit();
}

/* ===== ดึง EXP จาก missions ===== */
$stmt = $conn->prepare("SELECT exp_reward FROM missions WHERE id=?");
$stmt->bind_param("i",$mission_id);
$stmt->execute();
$result = $stmt->get_result();
$mission = $result->fetch_assoc();
$stmt->close();

if(!$mission){
    echo json_encode(["error"=>"mission_not_found"]);
    exit();
}

$exp_gain = (int)$mission['exp_reward'];

/* ===== เพิ่มประวัติ ===== */
$stmt = $conn->prepare("
INSERT INTO workout_history (user_id, mission_id, created_at)
VALUES (?, ?, NOW())
");
$stmt->bind_param("ii", $user_id, $mission_id);
$stmt->execute();
$stmt->close();

/* ===== เพิ่ม EXP แบบปลอดภัย ===== */
$stmt = $conn->prepare("UPDATE users SET exp = exp + ? WHERE id = ?");
$stmt->bind_param("ii", $exp_gain, $user_id);
$stmt->execute();
$stmt->close();

/* ===== ดึง EXP ล่าสุด ===== */
$stmt = $conn->prepare("SELECT exp FROM users WHERE id = ?");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

$current_exp = $row['exp'];
$level = floor($current_exp / 100) + 1;

echo json_encode([
    "exp"=>$exp_gain,
    "level"=>$level
]);
