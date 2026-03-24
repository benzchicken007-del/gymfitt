<?php
session_start();
date_default_timezone_set("Asia/Bangkok");
include "config/config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = (int)$_SESSION['user_id'];
$accuracy = isset($_GET['accuracy']) ? (int)$_GET['accuracy'] : 0;
$today_start = date('Y-m-d 00:00:00');
$today_end   = date('Y-m-d 23:59:59');

$reward_xp = 0;
$reward_token = 0;
$is_locked = 'false';

$conn->begin_transaction();
try {
    // กรณีเล่นโหมดภารกิจหลัก (Missions)
    if (isset($_GET['mission_id'])) {
        $mission_id = (int)$_GET['mission_id'];
        $stmtM = $conn->prepare("SELECT exp_reward, token_reward, daily_limit FROM missions WHERE mission_id = ?");
        $stmtM->bind_param("i", $mission_id);
        $stmtM->execute();
        $mission = $stmtM->get_result()->fetch_assoc();

        if ($mission) {
            $daily_limit = (int)$mission['daily_limit'];

            $stmtCheck = $conn->prepare("SELECT COUNT(*) as play_count FROM workout_history WHERE user_id = ? AND mission_id = ? AND workout_date BETWEEN ? AND ?");
            $stmtCheck->bind_param("iiss", $user_id, $mission_id, $today_start, $today_end);
            $stmtCheck->execute();
            $play_count_before = $stmtCheck->get_result()->fetch_assoc()['play_count'];

            if ($play_count_before < $daily_limit) {
                $reward_xp = (int)$mission['exp_reward'];
                $reward_token = (int)$mission['token_reward'];

                // บันทึกประวัติ
                $now_ts = date("Y-m-d H:i:s");
                $upLog = $conn->prepare("INSERT INTO workout_history (user_id, mission_id, accuracy, workout_date) VALUES (?, ?, ?, ?)");
                $upLog->bind_param("iiis", $user_id, $mission_id, $accuracy, $now_ts);
                $upLog->execute();

                if (($play_count_before + 1) >= $daily_limit) {
                    $is_locked = 'true';
                    $conn->query("UPDATE users SET mission_count = mission_count + 1 WHERE user_id = $user_id");
                }
            } else {
                header("Location: home.php?status=limit_reached");
                exit();
            }
        }
    }
    // กรณีเล่นโหมดอิสระ (Exercise / Free Mode)
    else if (isset($_GET['exercise_id'])) {
        $exercise_id = (int)$_GET['exercise_id'];
        $reps = isset($_GET['reps']) ? (int)$_GET['reps'] : 10;

        // ให้โบนัส EXP นิดหน่อยตามจำนวนครั้งที่เล่น (ไม่มีให้ Token ในโหมดฝึกอิสระ)
        $reward_xp = 10 + floor($reps / 2);

        $now_ts = date("Y-m-d H:i:s");
        // สังเกตว่าเราเซฟลง mission_id เป็น 0 สำหรับโหมดฝึกฟรี และใส่ exercise_id แทน
        $upLog = $conn->prepare("INSERT INTO workout_history (user_id, mission_id, exercise_id, accuracy, workout_date) VALUES (?, 0, ?, ?, ?)");
        $upLog->bind_param("iiis", $user_id, $exercise_id, $accuracy, $now_ts);
        $upLog->execute();
    }

    // อัปเดต EXP และ Token ให้ User
    if ($reward_xp > 0 || $reward_token > 0) {
        $upUser = $conn->prepare("UPDATE users SET exp = exp + ?, tokens = tokens + ? WHERE user_id = ?");
        $upUser->bind_param("iii", $reward_xp, $reward_token, $user_id);
        $upUser->execute();
    }

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    die("Error: " . $e->getMessage());
}

header("Location: home.php?status=completed&xp=" . $reward_xp . "&token=" . $reward_token . "&is_locked=" . $is_locked);
exit();
