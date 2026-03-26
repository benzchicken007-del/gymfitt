<?php
// 1. ป้องกัน session_start ซ้ำ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. ตั้งค่า Timezone (สำคัญมาก! หากไม่ตั้ง ระบบนับจำนวนครั้งภารกิจ "วันนี้" จะเพี้ยน)
date_default_timezone_set("Asia/Bangkok");

// 3. ตั้งค่า Database อ้างอิงตาม Control Panel
$host = "localhost";
$user = "root";
$pass = "";
$db   = "gymfitt";

// 4. สร้างการเชื่อมต่อ
$conn = new mysqli($host, $user, $pass, $db);

// 5. ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    // หากอยู่บนโฮสต์จริง แนะนำให้บันทึก log แทนการ die ออกหน้าจอเพื่อความปลอดภัย
    error_log("Database Connection Failed: " . $conn->connect_error);
    die("ขออภัย ระบบขัดข้องชั่วคราว กรุณาลองใหม่ภายหลัง");
}

// 6. ตั้งค่า charset ให้รองรับภาษาไทย (ต้องมีบรรทัดนี้เสมอเพื่อป้องกันภาษาต่างดาว)
$conn->set_charset("utf8mb4");
