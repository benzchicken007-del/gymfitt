<?php
session_start();
// 1. เชื่อมต่อฐานข้อมูล
include "config/config.php";

// 2. ตรวจสอบความปลอดภัย: ป้องกันการเข้าหน้านี้โดยตรงหากยังไม่ได้ผ่านการยืนยัน OTP
if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$status = "";

if (isset($_POST['update_password'])) {
    $email = $_SESSION['reset_email'];
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // 3. ตรวจสอบว่ารหัสผ่านทั้งสองช่องตรงกันหรือไม่
    if ($new_password !== $confirm_password) {
        $status = "mismatch";
    } else {
        // 4. เข้ารหัสผ่านใหม่ (Password Hashing) ก่อนบันทึกลงฐานข้อมูลเพื่อความปลอดภัย
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // 5. อัปเดตรหัสผ่านใหม่ลงตาราง users และล้างค่า OTP เดิมทิ้ง
        $update = $conn->prepare("UPDATE users SET password = ?, otp_code = NULL, otp_expiry = NULL WHERE email = ?");
        $update->bind_param("ss", $hashed_password, $email);

        if ($update->execute()) {
            // ล้าง Session การรีเซ็ตทั้งหมดเมื่อทำรายการสำเร็จ
            session_destroy();
            $status = "success";
        } else {
            $status = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งรหัสผ่านใหม่ - GYMFITT</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #0f0f0f; color: white; font-family: 'Poppins', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: #1a1a1a; padding: 40px; border-radius: 24px; border: 1px solid #333; width: 100%; max-width: 400px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.6); }
        h3 { color: #ef4444; font-size: 26px; margin-bottom: 10px; font-weight: 600; }
        p { color: #aaa; font-size: 14px; margin-bottom: 30px; }
        .input-group { text-align: left; margin-bottom: 20px; }
        label { display: block; color: #eee; font-size: 13px; margin-bottom: 8px; margin-left: 5px; }
        input { background: #111; border: 1px solid #333; color: white; padding: 15px; width: 100%; border-radius: 12px; box-sizing: border-box; font-size: 15px; transition: 0.3s; }
        input:focus { border-color: #ef4444; outline: none; background: #161616; }
        button { background: #ef4444; color: white; border: none; padding: 16px; width: 100%; border-radius: 12px; cursor: pointer; font-weight: 600; font-size: 16px; transition: 0.3s; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3); }
        button:hover { background: #b91c1c; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="card">
        <h3>ตั้งรหัสผ่านใหม่</h3>
        <p>กำหนดรหัสผ่านใหม่สำหรับบัญชี <br> <span style="color: #eee;"><?php echo htmlspecialchars($_SESSION['reset_email']); ?></span></p>
        
        <form method="POST">
            <div class="input-group">
                <label>รหัสผ่านใหม่</label>
                <input type="password" name="password" placeholder="อย่างน้อย 6 ตัวอักษร" minlength="6" required autofocus>
            </div>
            <div class="input-group">
                <label>ยืนยันรหัสผ่านใหม่</label>
                <input type="password" name="confirm_password" placeholder="กรอกรหัสผ่านอีกครั้ง" minlength="6" required>
            </div>
            <button type="submit" name="update_password">บันทึกรหัสผ่านใหม่</button>
        </form>
    </div>

    <script>
    // ส่วนควบคุมการแสดงผลแจ้งเตือน (Popup)
    <?php if($status == "success"): ?>
        Swal.fire({
            icon: 'success',
            title: 'เปลี่ยนรหัสผ่านสำเร็จ!',
            text: 'คุณสามารถใช้รหัสผ่านใหม่เข้าสู่ระบบได้ทันที',
            background: '#1a1a1a', color: '#fff', confirmButtonColor: '#ef4444'
        }).then(() => {
            window.location = 'login.php'; // นำทางไปยังหน้า Login
        });
    <?php elseif($status == "mismatch"): ?>
        Swal.fire({
            icon: 'warning',
            title: 'รหัสไม่ตรงกัน',
            text: 'กรุณากรอกรหัสผ่านทั้งสองช่องให้ตรงกัน',
            background: '#1a1a1a', color: '#fff', confirmButtonColor: '#ef4444'
        });
    <?php elseif($status == "error"): ?>
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง',
            background: '#1a1a1a', color: '#fff', confirmButtonColor: '#ef4444'
        });
    <?php endif; ?>
    </script>
</body>
</html>