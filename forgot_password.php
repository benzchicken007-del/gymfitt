<?php
// 1. ปิดการแสดง Error เมื่อระบบเสถียรแล้ว
ini_set('display_errors', 0);
error_reporting(0);

session_start();
// 2. ตั้งค่าเขตเวลาประเทศไทย
date_default_timezone_set("Asia/Bangkok");

// 3. เชื่อมต่อฐานข้อมูล
include "config/config.php";

// 4. นำเข้าไฟล์ PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$path = 'libs/PHPMailer/src/';
if (file_exists($path . 'PHPMailer.php')) {
    require $path . 'Exception.php';
    require $path . 'PHPMailer.php';
    require $path . 'SMTP.php';
} else {
    die("Error: ไม่พบไฟล์ PHPMailer ในระบบ");
}

$status = ""; 

if(isset($_POST['send_otp'])){
    $email = trim($_POST['email']);
    
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0){
        $otp = rand(100000, 999999);
        $expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));
        
        $update = $conn->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE email = ?");
        $update->bind_param("sss", $otp, $expiry, $email);
        
        if($update->execute()){
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com'; 
                $mail->SMTPAuth   = true;
                $mail->Username   = 'worawan2203za@gmail.com'; 
                $mail->Password   = 'qzqq xvgi xtrz lxge'; 
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587; 
                $mail->CharSet    = 'UTF-8';

                $mail->setFrom('worawan2203za@gmail.com', 'GYMFITT System');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'รหัส OTP สำหรับกู้คืนรหัสผ่าน - GYMFITT';
                $mail->Body    = "
                    <div style='font-family: sans-serif; padding: 20px; text-align: center; background: #0f0f0f; color: white;'>
                        <div style='background: #1a1a1a; padding: 40px; border-radius: 20px; display: inline-block; border: 1px solid #333;'>
                            <h2 style='color: #ef4444; margin-bottom: 10px;'>GYMFITT SECURITY</h2>
                            <p style='color: #aaa;'>รหัส OTP ของคุณสำหรับการตั้งรหัสผ่านใหม่คือ</p>
                            <h1 style='letter-spacing: 15px; font-size: 50px; color: #ffffff; margin: 30px 0; text-shadow: 0 0 10px rgba(239,68,68,0.5);'>$otp</h1>
                            <p style='color: #ef4444; font-weight: bold;'>รหัสนี้จะหมดอายุภายใน 15 นาที</p>
                            <hr style='border: 0; border-top: 1px solid #333; margin: 30px 0;'>
                            <p style='color: #666; font-size: 12px;'>หากคุณไม่ได้ร้องขอรหัสนี้ โปรดเพิกเฉยต่อข้อความนี้</p>
                        </div>
                    </div>";

                $mail->send();
                $status = "success";
            } catch (Exception $e) {
                $status = "mail_error";
            }
        }
    } else {
        $status = "not_found";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน - GYMFITT</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #ef4444;
            --primary-glow: rgba(239, 68, 68, 0.4);
            --bg-dark: #09090b;
            --card-glass: rgba(24, 24, 27, 0.8);
            --border-glass: rgba(255, 255, 255, 0.1);
        }

        body { 
            background: var(--bg-dark); 
            background-image: radial-gradient(circle at center, #1c1c1c 0%, #09090b 100%);
            color: white; 
            font-family: 'Poppins', sans-serif; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
            overflow: hidden; 
        }

        /* เอฟเฟกต์แสงไฟพื้นหลัง */
        .bg-glow-1 { position: absolute; top: -10%; left: -10%; width: 400px; height: 400px; background: rgba(239, 68, 68, 0.15); filter: blur(120px); border-radius: 50%; z-index: 0; }
        .bg-glow-2 { position: absolute; bottom: -10%; right: -10%; width: 400px; height: 400px; background: rgba(239, 68, 68, 0.1); filter: blur(120px); border-radius: 50%; z-index: 0; }

        .card { 
            background: var(--card-glass); 
            backdrop-filter: blur(15px);
            padding: 50px 40px; 
            border-radius: 30px; 
            border: 1px solid var(--border-glass); 
            width: 100%; 
            max-width: 400px; 
            text-align: center; 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7); 
            position: relative; 
            z-index: 1; 
        }

        .icon-box {
            width: 70px;
            height: 70px;
            background: rgba(239, 68, 68, 0.1);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: var(--primary);
            font-size: 30px;
            border: 1px solid rgba(239, 68, 68, 0.2);
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.1);
        }

        h3 { 
            color: white; 
            font-family: 'Orbitron', sans-serif;
            font-size: 24px; 
            margin-bottom: 10px; 
            letter-spacing: 1px;
        }

        p { color: #888; font-size: 14px; margin-bottom: 35px; line-height: 1.6; }

        .input-group { position: relative; margin-bottom: 25px; }
        .input-group i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #666; }

        input { 
            background: rgba(0, 0, 0, 0.3); 
            border: 1px solid var(--border-glass); 
            color: white; 
            padding: 16px 16px 16px 45px; 
            width: 100%; 
            border-radius: 15px; 
            box-sizing: border-box; 
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            font-size: 15px; 
        }

        input:focus { 
            border-color: var(--primary); 
            outline: none; 
            background: rgba(239, 68, 68, 0.05); 
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.2); 
        }

        button { 
            background: var(--primary); 
            color: white; 
            border: none; 
            padding: 16px; 
            width: 100%; 
            border-radius: 15px; 
            cursor: pointer; 
            font-weight: 700; 
            font-family: 'Orbitron', sans-serif;
            font-size: 15px; 
            transition: 0.4s; 
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3); 
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        button:hover { 
            background: #dc2626; 
            transform: translateY(-3px); 
            box-shadow: 0 12px 25px rgba(239, 68, 68, 0.4); 
        }

        .back-link { 
            color: #666; 
            font-size: 13px; 
            text-decoration: none; 
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 30px; 
            transition: 0.3s; 
        }

        .back-link:hover { color: var(--primary); }
    </style>
</head>
<body>
    <div class="bg-glow-1"></div>
    <div class="bg-glow-2"></div>

    <div class="card">
        <div class="icon-box">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <h3>PASSWORD RECOVERY</h3>
        <p>ระบุอีเมลที่ลงทะเบียนไว้ <br>เพื่อรับรหัส OTP สำหรับตั้งรหัสผ่านใหม่</p>
        
        <form method="POST">
            <div class="input-group">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" name="email" placeholder="Email Address" required autofocus>
            </div>
            <button type="submit" name="send_otp">
                <i class="fa-solid fa-paper-plane" style="margin-right: 8px;"></i> ขอรหัสผ่านใช้ครั้งเดียว
            </button>
        </form>
        
        <a href="login.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> กลับไปยังหน้าเข้าสู่ระบบ
        </a>
    </div>

    <script>
    const swalConfig = {
        background: '#1a1a1a',
        color: '#fff',
        confirmButtonColor: '#ef4444',
        customClass: {
            popup: 'animated fadeInDown faster'
        }
    };

    <?php if($status == "success"): ?>
        Swal.fire({
            ...swalConfig,
            icon: 'success',
            title: 'ส่งรหัสสำเร็จ!',
            text: 'ระบบได้ส่งรหัส OTP ไปยังอีเมลเรียบร้อยแล้ว',
            timer: 3000,
            timerProgressBar: true
        }).then(() => {
            window.location = 'verify_otp.php?email=<?php echo urlencode($email); ?>';
        });
    <?php elseif($status == "not_found"): ?>
        Swal.fire({
            ...swalConfig,
            icon: 'error',
            title: 'ไม่พบข้อมูล',
            text: 'ขออภัย ไม่พบอีเมลนี้ในระบบของเรา'
        });
    <?php elseif($status == "mail_error"): ?>
        Swal.fire({
            ...swalConfig,
            icon: 'warning',
            title: 'เกิดข้อผิดพลาด',
            text: 'ไม่สามารถส่งอีเมลได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง'
        });
    <?php endif; ?>
    </script>
</body>
</html>