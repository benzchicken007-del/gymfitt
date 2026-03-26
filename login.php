<?php
session_start();
include "config/config.php";

$error = "";

if (isset($_POST['login'])) {

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    /* =========================
       1️⃣ ตรวจสอบ USER ก่อน
    ==========================*/
    $stmt = $conn->prepare("SELECT * FROM users WHERE username=? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['level'] = $user['level'];
        $_SESSION['exp'] = $user['exp'];

        header("Location: home.php");
        exit();
    }

    /* =========================
       2️⃣ ถ้าไม่ใช่ USER → ตรวจ ADMIN
    ==========================*/
    $stmt = $conn->prepare("SELECT * FROM admins WHERE username=? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    $stmt->close();

    if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);

        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];

        header("Location: admin/dashboard.php");
        exit();
    }

    $error = "Username หรือ Password ไม่ถูกต้อง";
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GYMFITT</title>

    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600;700&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* บังคับใช้ธีม ดำ-แดง เสมอ */
        :root {
            --primary: #ef4444;
            --primary-glow: rgba(239, 68, 68, 0.4);
            --bg-dark: #09090b;
            --card-glass: rgba(24, 24, 27, 0.8);
            --border-glass: rgba(255, 255, 255, 0.1);
        }

        body {
            background: var(--bg-dark);
            /* พื้นหลังมีแสงเรืองๆ แบบ Sci-fi */
            background-image: radial-gradient(circle at center, #1c1c1c 0%, #09090b 100%);
            color: white;
            margin: 0;
            font-family: 'Poppins', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            overflow: hidden;
        }

        /* ตกแต่ง Background เพิ่มเติม */
        body::before {
            content: "";
            position: absolute;
            top: -10%;
            left: -10%;
            width: 300px;
            height: 300px;
            background: var(--primary);
            filter: blur(150px);
            opacity: 0.15;
            z-index: -1;
        }

        .card {
            background: var(--card-glass);
            backdrop-filter: blur(15px);
            border: 1px solid var(--border-glass);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            padding: 50px 40px;
            border-radius: 30px;
            width: 100%;
            max-width: 400px;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .logo-icon {
            font-size: 40px;
            color: var(--primary);
            margin-bottom: 10px;
            text-shadow: 0 0 15px var(--primary-glow);
        }

        h2 {
            color: white;
            font-family: 'Orbitron', sans-serif;
            margin-bottom: 30px;
            letter-spacing: 2px;
            font-size: 24px;
        }

        h2 span {
            color: var(--primary);
        }

        .input-container {
            position: relative;
            margin-bottom: 20px;
        }

        .input-container i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 18px;
        }

        input {
            background: rgba(0, 0, 0, 0.3) !important;
            border: 1px solid var(--border-glass) !important;
            color: white !important;
            padding: 14px 14px 14px 45px;
            width: 100%;
            border-radius: 12px;
            box-sizing: border-box;
            transition: 0.3s;
            font-size: 15px;
        }

        input:focus {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.15);
            outline: none;
        }

        .btn-primary {
            background: var(--primary) !important;
            color: white;
            border: none;
            padding: 15px;
            width: 100%;
            border-radius: 12px;
            font-weight: 700;
            font-family: 'Orbitron', sans-serif;
            cursor: pointer;
            transition: 0.4s;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 8px 15px rgba(239, 68, 68, 0.3);
        }

        .btn-primary:hover {
            background: #dc2626 !important;
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(239, 68, 68, 0.5);
        }

        /* ปุ่มย้อนกลับมุมซ้ายบน */
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
            border-radius: 15px;
            color: var(--primary);
            text-decoration: none;
            font-size: 20px;
            transition: 0.3s;
            backdrop-filter: blur(10px);
        }

        .btn-back-circle:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.1) rotate(-10deg);
        }

        .forgot-link {
            display: block;
            text-align: right;
            margin-top: -10px;
            margin-bottom: 25px;
            color: #888;
            text-decoration: none;
            font-size: 13px;
            transition: 0.3s;
        }

        .forgot-link:hover {
            color: var(--primary);
        }

        .register-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-glass);
        }

        .link-register {
            color: #888;
            text-decoration: none;
            font-size: 14px;
        }

        .link-register b {
            color: var(--primary);
        }

        .link-register:hover b {
            text-decoration: underline;
        }

        .error-box {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid var(--primary);
            color: white;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            animation: shake 0.4s ease-in-out;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-8px);
            }

            50% {
                transform: translateX(8px);
            }

            75% {
                transform: translateX(-8px);
            }
        }
    </style>

</head>

<body>

    <div class="back-nav">
        <a href="index.php" class="btn-back-circle"><i class="fa-solid fa-chevron-left"></i></a>
    </div>

    <div class="card">
        <div class="logo-icon"><i class="fa-solid fa-dumbbell"></i></div>
        <h2>GYM<span>FITT</span></h2>

        <?php if ($error != "") { ?>
            <div class="error-box">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
            </div>
        <?php } ?>

        <form method="POST">
            <div class="input-container">
                <i class="fa-solid fa-user"></i>
                <input type="text" name="username" placeholder="ชื่อผู้ใช้งาน" required>
            </div>

            <div class="input-container">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="password" placeholder="รหัสผ่าน" required>
            </div>

            <a href="forgot_password.php" class="forgot-link">ลืมรหัสผ่าน?</a>

            <button type="submit" class="btn-primary" name="login">ยืนยันตัวตน</button>
        </form>

        <div class="register-section">
            <a href="register.php" class="link-register">
                ยังไม่มีบัญชี? <b>สมัครสมาชิกเลย</b>
            </a>
        </div>
    </div>

</body>

</html>