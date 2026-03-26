<?php
session_start();

// ถอยกลับ 1 โฟลเดอร์เพื่อเรียก config
include "../config/config.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "not_logged_in"]);
    exit();
}

$user_id = $_SESSION['user_id'];

// รับค่าทั้งหมดจากฝั่งหน้าจอ
$mission_id = isset($_POST['mission_id']) ? (int)$_POST['mission_id'] : 0;
$exercise_id = isset($_POST['exercise_id']) ? (int)$_POST['exercise_id'] : 0;
$accuracy = isset($_POST['accuracy']) ? (int)$_POST['accuracy'] : 0;
$passed = isset($_POST['passed']) ? filter_var($_POST['passed'], FILTER_VALIDATE_BOOLEAN) : false;
// ถ้าไม่ได้ทำสำเร็จจริงให้เด้งออก
if (!$passed) {
    echo json_encode(["error" => "not_passed"]);
    exit();
}

$exp_gain = 0;
$token_gain = 0; // เพิ่มตัวแปรสำหรับเก็บโทเคนที่ได้

// แยกการให้รางวัลระหว่าง "ทำภารกิจ" กับ "ฝึกซ้อมอิสระ"
if ($mission_id > 0) {
    // ดึงทั้ง exp_reward และ token_reward
    $stmt = $conn->prepare("SELECT exp_reward, token_reward FROM missions WHERE mission_id=?");
    $stmt->bind_param("i", $mission_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $mission = $result->fetch_assoc();
    $stmt->close();

    if ($mission) {
        $exp_gain = (int)$mission['exp_reward'];
        $token_gain = (int)$mission['token_reward']; // เก็บค่าโทเคน
    }
} else {
    // รางวัลโบนัสสำหรับโหมดฝึกซ้อมฟรี
    $exp_gain = 5;
    $token_gain = 0; // โหมดฝึกซ้อมอิสระไม่ได้โทเคน (หรือปรับได้ตามต้องการ)
}

/* ===== เพิ่มประวัติ (ครอบคลุมทั้ง 2 โหมด) ===== */
// บันทึกข้อมูลประวัติ (อิงคอลัมน์ตามไฟล์ home.php)
$stmt = $conn->prepare("
    INSERT INTO workout_history (user_id, mission_id, exercise_id, accuracy, workout_date)
    VALUES (?, ?, ?, ?, NOW())
");

// รองรับการ Fallback ในกรณีที่ฐานข้อมูลของคุณใช้ชื่อคอลัมน์ created_at แทน workout_date
if ($stmt) {
    $stmt->bind_param("iiii", $user_id, $mission_id, $exercise_id, $accuracy);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare("
        INSERT INTO workout_history (user_id, mission_id, exercise_id, accuracy, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    if ($stmt) {
        $stmt->bind_param("iiii", $user_id, $mission_id, $exercise_id, $accuracy);
        $stmt->execute();
        $stmt->close();
    }
}

/* ===== เพิ่ม EXP และ TOKENS ให้กับ User ===== */
if ($exp_gain > 0 || $token_gain > 0) {
    // อัปเดตทั้ง exp และ tokens ในคำสั่งเดียว
    $stmt = $conn->prepare("UPDATE users SET exp = exp + ?, tokens = tokens + ? WHERE user_id = ?");
    $stmt->bind_param("iii", $exp_gain, $token_gain, $user_id);
    $stmt->execute();
    $stmt->close();
}

/* ===== ดึง EXP ล่าสุดเพื่อส่งกลับไปคำนวณ Level ===== */
$stmt = $conn->prepare("SELECT exp FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

$current_exp = $row['exp'];
$level = floor($current_exp / 100) + 1;

// เตรียมรายละเอียดสำหรับแสดงในป๊อปอัพ
$title = ($mission_id > 0) ? "ภารกิจสำเร็จ! 🎉" : "ฝึกซ้อมสำเร็จ! 👏";
$message = ($mission_id > 0)
    ? "เยี่ยมมาก! คุณทำภารกิจสำเร็จ ได้รับ $exp_gain EXP และ $token_gain Tokens 🪙"
    : "การฝึกซ้อมอิสระของคุณยอดเยี่ยมมาก ได้รับโบนัส $exp_gain EXP";

// ส่ง success => true กลับไปให้ Javascript
echo json_encode([
    "success" => true,
    "exp" => $exp_gain,
    "tokens" => $token_gain,
    "level" => $level,
    "mode" => ($mission_id > 0) ? "mission" : "free_practice",
    "title" => $title,
    "message" => $message,
    "redirect" => "home.php"
]);
