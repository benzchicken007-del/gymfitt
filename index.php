<?php 
include "config/config.php"; 
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>GYMFITT</title>

<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/index.css">

</head>

<body>

<header>
    <div class="logo">GYMFITT</div>
    <div>

        <?php if(isset($_SESSION['user_id'])): ?>
            <!-- Member -->
            <a href="home.php" class="btn-primary">แดชบอร์ด</a>
            <a href="logout.php" class="btn-outline">ออกจากระบบ</a>

        <?php elseif(isset($_SESSION['guest'])): ?>
            <!-- Guest -->
            <a href="guest_home.php" class="btn-primary">แดชบอร์ดผู้ใช้ทั่วไป</a>
            <a href="logout.php" class="btn-outline">ออกจากระบบ</a>

        <?php else: ?>
            <!-- ยังไม่ล็อกอิน -->
            <a href="login.php" class="btn-outline">เข้าสู่ระบบ</a>
            <a href="register.php" class="btn-primary">สมัครสมาชิก</a>
        <?php endif; ?>

    </div>
</header>

<section class="hero">
    <div class="hero-content">
        <h1>TRAIN LIKE A GAME</h1>
        <p>ยกระดับร่างกายของคุณด้วยระบบ AI และภารกิจสะสม EXP</p>

        <?php if(!isset($_SESSION['user_id']) && !isset($_SESSION['guest'])): ?>
            <!-- ยังไม่ล็อกอิน -->
            <a href="guest.php" class="btn-main">เข้าสู่ระบบผู้ใช้ทั่วไป</a>

        <?php elseif(isset($_SESSION['guest'])): ?>
            <!-- เป็น Guest แล้ว -->
            <a href="guest_home.php" class="btn-main">เข้าสู่แดชบอร์ดผู้ใช้ทั่วไป</a>

        <?php else: ?>
            <!-- เป็น Member -->
            <a href="home.php" class="btn-main">เข้าสู่แดชบอร์ด</a>
        <?php endif; ?>

    </div>
</section>

</body>
</html>
