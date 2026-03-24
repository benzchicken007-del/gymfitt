<?php
/**
 * GYMFITT Pro - Title Processing System
 * ไฟล์สำหรับประมวลผลการเพิ่มฉายาใหม่เข้าร้านค้า
 */

session_start();

// 1. ตรวจสอบสิทธิ์: ต้องเป็นแอดมินที่ล็อกอินแล้วเท่านั้น
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// 2. เชื่อมต่อฐานข้อมูล
require_once "../config/config.php";

// 3. ตั้งค่า Charset ให้รองรับภาษาไทย (ป้องกันภาษาต่างดาว ???? บน Host จริง)
$conn->set_charset("utf8mb4");
$conn->query("SET NAMES utf8mb4");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 4. รับค่าโดยตรง (ไม่ต้องใช้ mysqli_real_escape_string เพราะเราใช้ Prepared Statement ด้านล่างแล้ว)
    // การใช้ escape_string ซ้ำซ้อนกับ bind_param เป็นสาเหตุหลักที่ทำให้ภาษาไทยกลายเป็นเครื่องหมายคำถาม
    $title_name  = trim($_POST['title_name']);
    $rarity      = $_POST['rarity'];
    $price       = intval($_POST['price']); // มั่นใจว่าเป็นตัวเลขแน่นอน
    $title_color = $_POST['title_color'];

    // 5. ตรวจสอบข้อมูลเบื้องต้น
    if (empty($title_name) || $price < 0) {
        header("Location: dashboard.php?error=invalid_data");
        exit();
    }

    // 6. เตรียมคำสั่ง SQL (Prepared Statement) เพื่อความปลอดภัยสูงสุดและรักษาภาษาไทย
    $sql = "INSERT INTO shop_titles (title_name, rarity, price, title_color) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        // "ssis" หมายถึง String, String, Integer, String ตามลำดับตัวแปร
        // ลำดับต้องตรงกับโครงสร้างตาราง: title_name, rarity, price, title_color
        $stmt->bind_param("ssis", $title_name, $rarity, $price, $title_color);
        
        // 7. ทำการบันทึก
        if ($stmt->execute()) {
            // บันทึกสำเร็จ! ส่งกลับไปหน้า Dashboard พร้อมตัวแปรบอกความสำเร็จ
            $stmt->close();
            $conn->close();
            header("Location: dashboard.php?add_title_success=1");
            exit();
        } else {
            // กรณีบันทึกพลาด
            header("Location: dashboard.php?error=database_error");
        }
        $stmt->close();
    } else {
        header("Location: dashboard.php?error=stmt_prepare_failed");
    }

} else {
    // กรณีพยายามเข้าถึงไฟล์นี้โดยตรง
    header("Location: dashboard.php");
}

// ปิดการเชื่อมต่อ
$conn->close();
exit();
?>