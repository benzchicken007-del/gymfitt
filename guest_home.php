<?php
session_start();
date_default_timezone_set("Asia/Bangkok");
include "config/config.php";

$conn->set_charset("utf8mb4");

// ดึงข้อมูลท่าออกกำลังกายทั้งหมด
$ex_query = $conn->query("SELECT * FROM exercises ORDER BY exercise_id DESC");

if (!isset($_SESSION['theme'])) $_SESSION['theme'] = "dark";
if (isset($_POST['toggleTheme'])) $_SESSION['theme'] = ($_SESSION['theme'] == "dark") ? "light" : "dark";
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GYMFITT - Guest Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #ef4444;
            --bg-dark: #0f0f0f;
            --card-dark: #1a1a1a;
            --border: rgba(255, 255, 255, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: var(--bg-dark);
            color: #fff;
            min-height: 100vh;
            overflow-x: hidden;
        }

        body.light-mode {
            background: #f8fafc;
            color: #1e293b;
            --card-dark: #fff;
            --border: rgba(0, 0, 0, 0.1);
        }

        header {
            padding: 20px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid var(--border);
        }

        body.light-mode header {
            background: rgba(255, 255, 255, 0.8);
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: 2px;
        }

        .dashboard {
            text-align: center;
            padding: 60px 20px 20px;
        }

        .dashboard h1 {
            font-size: clamp(2rem, 4vw, 3rem);
            margin-bottom: 10px;
        }

        .dashboard p {
            color: #94a3b8;
            font-size: 16px;
            margin-bottom: 40px;
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            padding: 0 5% 50px;
        }

        .ex-card {
            background: var(--card-dark);
            border-radius: 24px;
            border: 1px solid var(--border);
            overflow: hidden;
            transition: 0.3s;
        }

        .ex-card:hover {
            transform: translateY(-10px);
            border-color: var(--primary);
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.2);
        }

        .ex-media {
            height: 200px;
            width: 100%;
            background: #000;
        }

        .ex-media img,
        .ex-media video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .ex-info {
            padding: 20px;
        }

        .ex-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
        }

        .ex-info p {
            font-size: 13px;
            opacity: 0.6;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .btn-play {
            display: block;
            width: 100%;
            text-align: center;
            background: var(--primary);
            color: white;
            padding: 12px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }

        .btn-play:hover {
            background: #dc2626;
        }

        .btn-login {
            border: 1px solid var(--border);
            padding: 8px 15px;
            border-radius: 10px;
            color: inherit;
            text-decoration: none;
            font-size: 14px;
        }
    </style>
</head>

<body class="<?= ($_SESSION['theme'] == "light") ? 'light-mode' : '' ?>">

    <header>
        <div class="logo">GYMFITT</div>
        <div style="display:flex; gap:10px; align-items:center;">
            <form method="POST"><button type="submit" name="toggleTheme" class="btn-login" style="background:none; cursor:pointer;"><i class="fa-solid fa-moon"></i></button></form>
            <a href="login.php" class="btn-login">เข้าสู่ระบบสมาชิก</a>
        </div>
    </header>

    <div class="dashboard">
        <h1>โหมดผู้ใช้ทั่วไป (Guest) 🏋️‍♂️</h1>
        <p>เลือกท่าออกกำลังกายอิสระ (ไม่จำกัดจำนวน ไม่มีการบันทึกประวัติ)</p>
    </div>

    <div class="grid-container">
        <?php if ($ex_query->num_rows > 0): while ($ex = $ex_query->fetch_assoc()): ?>
                <div class="ex-card">
                    <div class="ex-media">
                        <?php if (!empty($ex['exercise_image'])): ?>
                            <img src="uploads/<?= htmlspecialchars($ex['exercise_image']) ?>" alt="Cover">
                        <?php else: ?>
                            <div style="height:100%; display:flex; align-items:center; justify-content:center; color:#555;"><i class="fa-solid fa-dumbbell fa-3x"></i></div>
                        <?php endif; ?>
                    </div>
                    <div class="ex-info">
                        <h3><?= htmlspecialchars($ex['exercise_name']) ?></h3>
                        <p><?= htmlspecialchars($ex['exercise_detail']) ?></p>
                        <a href="play_exercise.php?id=<?= $ex['exercise_id'] ?>&guest=1" class="btn-play">เริ่มฝึกซ้อม</a>
                    </div>
                </div>
            <?php endwhile;
        else: ?>
            <p style="text-align:center; grid-column:1/-1; opacity:0.5;">ยังไม่มีท่าออกกำลังกายในระบบ</p>
        <?php endif; ?>
    </div>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('status') === 'completed') {
            Swal.fire({
                title: 'ฝึกซ้อมเสร็จสิ้น!',
                text: 'เยี่ยมมาก! สมัครสมาชิกเพื่อบันทึกสถิติและรับรางวัลสิ',
                icon: 'success',
                confirmButtonColor: '#ef4444'
            });
        }
    </script>
</body>

</html>