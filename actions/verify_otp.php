<?php
session_start();
include "config/config.php";

// รับอีเมลจาก URL
$email = isset($_GET['email']) ? $_GET['email'] : '';
$status = "";

if (isset($_POST['verify'])) {
    $user_otp = trim($_POST['otp']);

    // ตรวจสอบรหัสและความถูกต้องของเวลาหมดอายุ
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND otp_code = ? AND otp_expiry > NOW()");
    $stmt->bind_param("ss", $email, $user_otp);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['reset_email'] = $email; // เก็บ session เพื่ออนุญาตให้เปลี่ยนรหัส
        $status = "success";
    } else {
        $status = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยืนยันรหัส OTP - GYMFITT</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #0f0f0f; color: white; font-family: 'Poppins', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: #1a1a1a; padding: 40px; border-radius: 24px; border: 1px solid #333; width: 100%; max-width: 400px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.6); }
        h3 { color: #ef4444; font-size: 26px; margin-bottom: 10px; }
        p { color: #aaa; font-size: 14px; margin-bottom: 30px; }
        input { background: #111; border: 1px solid #444; color: white; padding: 15px; width: 100%; border-radius: 12px; margin-bottom: 20px; text-align: center; font-size: 24px; letter-spacing: 10px; box-sizing: border-box; }
        button { background: #ef4444; color: white; border: none; padding: 15px; width: 100%; border-radius: 12px; cursor: pointer; font-weight: 600; font-size: 16px; transition: 0.3s; }
        button:hover { background: #b91c1c; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="card">
        <h3>ยืนยันรหัส OTP</h3>
        <p>กรอกรหัส 6 หลักที่ส่งไปยังอีเมลของคุณ <br> <b><?php echo htmlspecialchars($email); ?></b></p>
        <form method="POST">
            <input type="text" name="otp" placeholder="000000" maxlength="6" required autofocus>
            <button type="submit" name="verify">ยืนยันรหัส</button>
        </form>
    </div>

    <script>
    <?php if($status == "success"): ?>
        Swal.fire({
            icon: 'success',
            title: 'ยืนยันสำเร็จ!',
            text: 'รหัสถูกต้อง กำลังพาคุณไปตั้งรหัสผ่านใหม่',
            background: '#1a1a1a', color: '#fff', confirmButtonColor: '#ef4444', timer: 2000, showConfirmButton: false
        }).then(() => {
            window.location = 'reset_password.php';
        });
    <?php elseif($status == "error"): ?>
        Swal.fire({
            icon: 'error',
            title: 'รหัสไม่ถูกต้อง',
            text: 'รหัส OTP ผิด หรืออาจจะหมดอายุแล้ว กรุณาลองใหม่',
            background: '#1a1a1a', color: '#fff', confirmButtonColor: '#ef4444'
        });
    <?php endif; ?>
    </script>
</body>
</html>