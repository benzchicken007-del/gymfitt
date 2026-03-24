<?php
include "config/config.php";

if(isset($_POST['register'])){

    if(!isset($_POST['agree'])){
        echo "<script>alert('กรุณายอมรับเงื่อนไขก่อนสมัครสมาชิก');</script>";
    } else {

        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // 1. ตรวจสอบว่ารหัสผ่านตรงกันหรือไม่
        if($password !== $confirm_password){
            echo "<script>alert('รหัสผ่านไม่ตรงกัน กรุณาตรวจสอบอีกครั้ง');</script>";
        } else {
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = "member";
            $level = 1;
            $exp = 0;

            // 2. ตรวจสอบว่ามี Username หรือ Email ซ้ำหรือไม่
            $check = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
            $check->bind_param("ss", $username, $email);
            $check->execute();
            $res = $check->get_result();

            if($res->num_rows > 0){
                 echo "<script>alert('Username หรือ Email นี้ถูกใช้ไปแล้ว');</script>";
            } else {
                // 3. บันทึกข้อมูลลงฐานข้อมูล
                $stmt = $conn->prepare("INSERT INTO users (username,password,email,role,level,exp) VALUES (?,?,?,?,?,?)");
                $stmt->bind_param("ssssii",$username,$hashed_password,$email,$role,$level,$exp);

                if($stmt->execute()){
                    echo '
                    <div class="success-overlay">
                        <div class="success-box">
                            <div class="checkmark-circle">
                                <div class="checkmark"></div>
                            </div>
                            <h3>ยินดีต้อนรับสู่ทีม!</h3>
                            <p>สมัครสมาชิกสำเร็จ กำลังเข้าสู่ระบบ...</p>
                        </div>
                    </div>

                    <style>
                    .success-overlay{
                        position:fixed; inset:0; background:rgba(0,0,0,0.9);
                        backdrop-filter:blur(10px); display:flex;
                        align-items:center; justify-content:center; z-index:9999;
                    }
                    .success-box{
                        background:#1a1a1a; padding:50px; border-radius:30px;
                        text-align:center; border: 1px solid #ef4444;
                        box-shadow: 0 0 50px rgba(239,68,68,0.2); color:white;
                    }
                    .checkmark-circle {
                        width: 80px; height: 80px; border-radius: 50%;
                        border: 4px solid #22c55e; margin: 0 auto 20px;
                        display: flex; align-items: center; justify-content: center;
                    }
                    .checkmark {
                        width: 20px; height: 40px; border-right: 4px solid #22c55e;
                        border-bottom: 4px solid #22c55e; transform: rotate(45deg);
                        margin-bottom: 10px;
                    }
                    </style>

                    <script>
                        setTimeout(function(){ window.location="login.php"; }, 2500);
                    </script>';
                } else {
                    echo "<script>alert('เกิดข้อผิดพลาดในการสมัคร');</script>";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>JOIN THE FORCE - GYMFITT</title>

<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600;700&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* บังคับธีม ดำ-แดง (Next-Gen UI) */
:root {
    --primary: #ef4444;
    --primary-hover: #b91c1c;
    --bg-dark: #09090b;
    --card-glass: rgba(26, 26, 26, 0.8);
    --border-glass: rgba(255, 255, 255, 0.1);
}

body {
    background: var(--bg-dark);
    background-image: 
        radial-gradient(circle at 10% 20%, rgba(239, 68, 68, 0.1) 0%, transparent 40%),
        radial-gradient(circle at 90% 80%, rgba(239, 68, 68, 0.05) 0%, transparent 40%);
    color: white;
    margin: 0;
    font-family: 'Poppins', sans-serif;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
}

.card {
    background: var(--card-glass);
    backdrop-filter: blur(12px);
    border: 1px solid var(--border-glass);
    padding: 50px 40px;
    border-radius: 35px;
    width: 100%;
    max-width: 440px;
    text-align: center;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8);
}

h2 {
    color: white;
    font-family: 'Orbitron', sans-serif;
    margin-bottom: 10px;
    letter-spacing: 3px;
    font-size: 28px;
}

.subtitle {
    color: #888;
    font-size: 13px;
    margin-bottom: 35px;
    display: block;
}

/* Input Styles with Icons */
.input-group {
    position: relative;
    margin-bottom: 20px;
}

.input-group i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #555;
    font-size: 18px;
    transition: 0.3s;
}

input[type="text"], input[type="email"], input[type="password"] {
    background: rgba(0, 0, 0, 0.4) !important;
    border: 1px solid var(--border-glass) !important;
    color: white !important;
    padding: 15px 15px 15px 45px;
    width: 100%;
    border-radius: 12px;
    box-sizing: border-box;
    font-family: 'Poppins';
    font-size: 15px;
    transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

input:focus {
    border-color: var(--primary) !important;
    background: rgba(0, 0, 0, 0.6) !important;
    outline: none;
    box-shadow: 0 0 15px rgba(239, 68, 68, 0.2);
}

input:focus + i {
    color: var(--primary);
}

/* Checkbox Modern */
.agree-box {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 12px;
    margin: 25px 5px;
    font-size: 13px;
    color: #aaa;
}

.btn-primary {
    background: var(--primary) !important;
    color: white;
    border: none;
    padding: 16px;
    width: 100%;
    border-radius: 12px;
    font-weight: 700;
    font-family: 'Orbitron', sans-serif;
    cursor: pointer;
    transition: 0.4s;
    font-size: 15px;
    text-transform: uppercase;
    letter-spacing: 2px;
    box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3);
}

.btn-primary:hover {
    background: var(--primary-hover) !important;
    transform: translateY(-3px);
    box-shadow: 0 15px 30px rgba(239, 68, 68, 0.5);
}

/* Back Button */
.back-nav {
    position: fixed;
    top: 30px;
    left: 30px;
}

.btn-back-circle {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
    background: var(--card-glass);
    border: 1px solid var(--border-glass);
    border-radius: 18px;
    color: var(--primary);
    text-decoration: none;
    font-size: 20px;
    transition: 0.3s;
}

.btn-back-circle:hover {
    background: var(--primary);
    color: white;
    transform: scale(1.1) rotate(-5deg);
}

.footer-links {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.link-item {
    color: #666;
    text-decoration: none;
    font-size: 14px;
    transition: 0.3s;
}

.link-item b { color: var(--primary); }
.link-item:hover { color: #aaa; }

</style>
</head>
<body>

<div class="back-nav">
    <a href="index.php" class="btn-back-circle"><i class="fa-solid fa-arrow-left"></i></a>
</div>

<div class="card">
    <h2>SIGN UP</h2>
    <span class="subtitle">สร้างบัญชีนักรบ GYMFITT ของคุณ</span>

    <form method="POST">
        <div class="input-group">
            <i class="fa-solid fa-user-ninja"></i>
            <input type="text" name="username" placeholder="Username" required>
        </div>

        <div class="input-group">
            <i class="fa-solid fa-envelope"></i>
            <input type="email" name="email" placeholder="Email Address" required>
        </div>
        
        <div class="input-group">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="password" placeholder="Password" required>
        </div>

        <div class="input-group">
            <i class="fa-solid fa-shield-halved"></i>
            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
        </div>

        <div class="agree-box">
            <input type="checkbox" name="agree" id="agree" required>
            <label for="agree">ฉันยอมรับเงื่อนไขและข้อกำหนดการใช้งาน</label>
        </div>

        <button type="submit" class="btn-primary" name="register">START JOURNEY</button>
    </form>

    <div class="footer-links">
        <a href="login.php" class="link-item">มีบัญชีอยู่แล้ว? <b>เข้าสู่ระบบ</b></a>
    </div>
</div>

</body>
</html>