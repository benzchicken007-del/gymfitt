<?php
session_start();

/* ลบค่าทั้งหมดใน session */
$_SESSION = array();

/* ทำลาย session */
session_destroy();

/* ลบ cookie session (ถ้ามี) */
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

/* แก้ไขจาก login.php เป็น ../login.php เพื่อถอยไปโฟลเดอร์หลัก */
header("Location: ../login.php");
exit();
?>