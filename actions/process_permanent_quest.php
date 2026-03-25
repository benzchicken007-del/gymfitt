<?php
// ฟังก์ชันคำนวณ EXP ตามแรงค์
function calculateRankBonus($base_exp, $rank) {
    switch ($rank) {
        case 'Gold': return $base_exp * 2.0;   // โกลด์ได้ EXP x2
        case 'Silver': return $base_exp * 1.5; // ซิลเวอร์ได้ EXP x1.5
        case 'Bronze': return $base_exp * 1.2; // บรอนซ์ได้ EXP x1.2
        default: return $base_exp;
    }
}

// Logic ตรวจสอบ Streak และอัปเกรดแรงค์
function updateStreakAndRank($conn, $user_id, $mission_id) {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    // ดึงข้อมูล Streak ปัจจุบัน
    $stmt = $conn->prepare("SELECT * FROM user_permanent_streaks WHERE user_id = ? AND mission_id = ?");
    $stmt->bind_param("ii", $user_id, $mission_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if (!$data) {
        // ถ้ายังไม่เคยทำเลย ให้เริ่มนับ 1
        $ins = $conn->prepare("INSERT INTO user_permanent_streaks (user_id, mission_id, current_streak, last_completed_date, rank_level) VALUES (?, ?, 1, ?, 'None')");
        $ins->bind_param("iis", $user_id, $mission_id, $today);
        $ins->execute();
        return 'None';
    }

    if ($data['last_completed_date'] == $today) return $data['rank_level']; // วันนี้ทำไปแล้ว

    $new_streak = ($data['last_completed_date'] == $yesterday) ? $data['current_streak'] + 1 : 1;
    
    // คำนวณแรงค์ใหม่
    $new_rank = 'None';
    if ($new_streak >= 90) $new_rank = 'Gold';
    elseif ($new_streak >= 60) $new_rank = 'Silver';
    elseif ($new_streak >= 30) $new_rank = 'Bronze';

    $upd = $conn->prepare("UPDATE user_permanent_streaks SET current_streak = ?, last_completed_date = ?, rank_level = ? WHERE id = ?");
    $upd->bind_param("issi", $new_streak, $today, $new_rank, $data['id']);
    $upd->execute();

    return $new_rank;
}