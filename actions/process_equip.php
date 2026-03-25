<?php
/**
 * GYMFITT Pro - Title Equipment System
 * ไฟล์สำหรับประมวลผลการ ติดตั้ง และ ถอด ฉายา
 */

session_start();
require_once "config/config.php";

// 1. ตรวจสอบว่าเข้าสู่ระบบหรือยัง
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// --- ส่วนของการติดตั้งฉายา (Equip) ---
if ($action === 'equip' && isset($_GET['title_id'])) {
    $title_id = (int)$_GET['title_id'];

    // 2. ตรวจสอบว่า User คนนี้เป็นเจ้าของฉายานี้จริงหรือไม่ (เช็คจากตาราง user_titles)
    $stmtCheck = $conn->prepare("SELECT ut_id FROM user_titles WHERE user_id = ? AND title_id = ?");
    $stmtCheck->bind_param("ii", $user_id, $title_id);
    $stmtCheck->execute();
    $result = $stmtCheck->get_result();

    if ($result->num_rows > 0) {
        // 3. ถ้าเป็นเจ้าของจริง ให้ทำการ Update ไปที่ตาราง users คอลัมน์ title_active_id
        // หมายเหตุ: การ Update ตรงนี้จะทับค่าเก่าทันที ทำให้ใส่ได้แค่ฉายาเดียว
        $stmtUpdate = $conn->prepare("UPDATE users SET title_active_id = ? WHERE user_id = ?");
        $stmtUpdate->bind_param("ii", $title_id, $user_id);
        
        if ($stmtUpdate->execute()) {
            header("Location: home.php?equip=success");
        } else {
            header("Location: home.php?equip=error");
        }
        $stmtUpdate->close();
    } else {
        // กรณีไม่มีชื่อเป็นเจ้าของฉายา (แอบใส่ ID มาทาง URL)
        header("Location: home.php?equip=not_owned");
    }
    $stmtCheck->close();
} 

// --- ส่วนของการถอดฉายา (Unequip) ---
elseif ($action === 'unequip') {
    // ตั้งค่า title_active_id เป็น NULL เพื่อยกเลิกการแสดงฉายา
    $stmtRemove = $conn->prepare("UPDATE users SET title_active_id = NULL WHERE user_id = ?");
    $stmtRemove->bind_param("i", $user_id);
    
    if ($stmtRemove->execute()) {
        header("Location: home.php?unequip=success");
    } else {
        header("Location: home.php?unequip=error");
    }
    $stmtRemove->close();
} 

// กรณี action ไม่ถูกต้อง
else {
    header("Location: home.php");
}

$conn->close();
exit();
?>