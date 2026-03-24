<?php
session_start();
date_default_timezone_set("Asia/Bangkok");
include "config/config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

if (isset($_POST['clear_history'])) {
    $delStmt = $conn->prepare("DELETE FROM workout_history WHERE user_id = ?");
    $delStmt->bind_param("i", $user_id);
    if ($delStmt->execute()) {
        header("Location: home.php?status=history_cleared");
        exit();
    }
}

$stmtUser = $conn->prepare("SELECT u.*, t.title_id as active_id, t.title_name, t.title_color FROM users u LEFT JOIN shop_titles t ON u.title_active_id = t.title_id WHERE u.user_id=?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();

$full_name = !empty($user['full_name']) ? $user['full_name'] : $user['username'];
$gender    = $user['gender'] ?? 'ชาย';
$weight    = (float)($user['weight'] ?? 0);
$height    = (float)($user['height'] ?? 0);
$current_exp = (int)($user['exp'] ?? 0);
$current_level = (int)($user['level'] ?? 0);
$profile_pic = !empty($user['profile_image']) ? $user['profile_image'] : "";
$tokens = (int)($user['tokens'] ?? 0);
$active_title = $user['title_name'] ?? "";
$active_title_color = $user['title_color'] ?? "#888";
$current_title_id = $user['active_id'] ?? 0;

function getRequiredExp($level)
{
    if ($level <= 0) return 40;
    return floor(40 * pow(1.15, $level));
}

// ระบบ Auto Level Up
$temp_level = $current_level;
$temp_exp = $current_exp;
$level_up_happened = false;
while (true) {
    $needed = getRequiredExp($temp_level);
    if ($temp_exp >= $needed) {
        $temp_exp -= $needed;
        $temp_level++;
        $level_up_happened = true;
    } else {
        break;
    }
}
if ($level_up_happened) {
    $updateStmt = $conn->prepare("UPDATE users SET exp = ?, level = ? WHERE user_id = ?");
    $updateStmt->bind_param("iii", $temp_exp, $temp_level, $user_id);
    $updateStmt->execute();
    $current_exp = $temp_exp;
    $current_level = $temp_level;
}

$required_exp = getRequiredExp($current_level);
$level_percent = ($current_exp / $required_exp) * 100;
if ($level_percent > 100) $level_percent = 100;

/* ================= ACCOUNT STREAK & RANK LOGIC ================= */
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$checkToday = $conn->prepare("SELECT COUNT(*) as count FROM workout_history WHERE user_id = ? AND DATE(workout_date) = ?");
$checkToday->bind_param("is", $user_id, $today);
$checkToday->execute();
$hasPlayedToday = $checkToday->get_result()->fetch_assoc()['count'] > 0;

$lastDate = $user['last_workout_date'] ?? null;
$streak = (int)($user['current_streak'] ?? 0);
if ($hasPlayedToday && $lastDate != $today) {
    if ($lastDate == $yesterday) {
        $streak++;
    } elseif ($lastDate == null || $lastDate < $yesterday) {
        $streak = 1;
    }
    $new_rank = 'Member';
    if ($streak >= 90) $new_rank = 'Gold';
    elseif ($streak >= 60) $new_rank = 'Silver';
    elseif ($streak >= 30) $new_rank = 'Bronze';
    $updRank = $conn->prepare("UPDATE users SET current_streak = ?, last_workout_date = ?, account_rank = ? WHERE user_id = ?");
    $updRank->bind_param("issi", $streak, $today, $new_rank, $user_id);
    $updRank->execute();
    $user['account_rank'] = $new_rank;
    $user['current_streak'] = $streak;
}

$rank_name = $user['account_rank'] ?? 'Member';
$rank_color = "#888";
$rank_icon = "👤";
if ($rank_name == 'Bronze') {
    $rank_color = "#cd7f32";
    $rank_icon = "🥉";
}
if ($rank_name == 'Silver') {
    $rank_color = "#94a3b8";
    $rank_icon = "🥈";
}
if ($rank_name == 'Gold') {
    $rank_color = "#fbbf24";
    $rank_icon = "🥇";
}

// คำนวณ BMI
$bmi = 0;
$bmi_text = "รอข้อมูล";
$bmi_color = "#888";
if ($weight > 0 && $height > 0) {
    $height_m = $height / 100;
    $bmi = number_format($weight / ($height_m * $height_m), 1);
    if ($bmi < 18.5) {
        $bmi_text = "น้ำหนักน้อย";
        $bmi_color = "#38bdf8";
    } else if ($bmi < 23) {
        $bmi_text = "สมส่วน";
        $bmi_color = "#22c55e";
    } else if ($bmi < 25) {
        $bmi_text = "น้ำหนักเกิน";
        $bmi_color = "#f97316";
    } else {
        $bmi_text = "เริ่มอ้วน";
        $bmi_color = "#ef4444";
    }
}

/* ===== LOAD MISSIONS, SHOP & OWNED TITLES ===== */
$today_str = date('Y-m-d');
$missions_query = "SELECT m.*, (SELECT COUNT(*) FROM workout_history wh WHERE wh.mission_id = m.mission_id AND wh.user_id = ? AND DATE(wh.workout_date) = ?) as today_play_count FROM missions m ORDER BY m.mission_id DESC";
$stmtMissions = $conn->prepare($missions_query);
$stmtMissions->bind_param("is", $user_id, $today_str);
$stmtMissions->execute();
$missions = $stmtMissions->get_result();

// ดึงฉายาที่ยังไม่ได้ซื้อ (สำหรับ Shop)
$shop_query = "SELECT * FROM shop_titles WHERE title_id NOT IN (SELECT title_id FROM user_titles WHERE user_id = ?) ORDER BY price ASC";
$stmtShop = $conn->prepare($shop_query);
$stmtShop->bind_param("i", $user_id);
$stmtShop->execute();
$shop_items = $stmtShop->get_result();

// ดึงฉายาที่ซื้อไปแล้ว (สำหรับ Inventory)
$owned_query = "SELECT st.* FROM user_titles ut JOIN shop_titles st ON ut.title_id = st.title_id WHERE ut.user_id = ? ORDER BY st.title_name ASC";
$stmtOwned = $conn->prepare($owned_query);
$stmtOwned->bind_param("i", $user_id);
$stmtOwned->execute();
$owned_titles = $stmtOwned->get_result();

// [แก้ไขแล้ว] ระบบ History: ดึงชื่อและรูปภาพให้ถูกตาราง ระหว่าง Mission และ Exercise
$history_query = "
    SELECT 
        wh.*, 
        IF(wh.mission_id > 0, m.mission_name, e.exercise_name) as display_name, 
        IF(wh.mission_id > 0, m.mission_image, e.exercise_image) as display_image 
    FROM workout_history wh 
    LEFT JOIN missions m ON wh.mission_id = m.mission_id 
    LEFT JOIN exercises e ON wh.exercise_id = e.exercise_id 
    WHERE wh.user_id = ? 
    ORDER BY wh.workout_date DESC LIMIT 10";
$stmtHist = $conn->prepare($history_query);
$stmtHist->bind_param("i", $user_id);
$stmtHist->execute();
$history_list = $stmtHist->get_result();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GYMFITT - Next Gen Fitness</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Orbitron:wght@600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #ef4444;
            --primary-glow: rgba(239, 68, 68, 0.4);
            --bg: #050505;
            --card: #121212;
            --border: rgba(255, 255, 255, 0.1);
            --text: #ffffff;
            --glass: rgba(255, 255, 255, 0.03);
            --rank-color: <?= $rank_color ?>;
            --transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body.light-mode {
            --bg: #f0f2f5;
            --card: #ffffff;
            --border: rgba(0, 0, 0, 0.05);
            --text: #1a1a1a;
            --primary: #3b82f6;
            --primary-glow: rgba(59, 130, 246, 0.2);
            --glass: rgba(0, 0, 0, 0.02);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg);
            color: var(--text);
            transition: background var(--transition);
            overflow-x: hidden;
        }

        /* Background Animation */
        .bg-glow {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 50% -20%, var(--primary-glow), transparent 60%);
            pointer-events: none;
            z-index: -1;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 5%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(20px);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--border);
        }

        .logo {
            font-family: 'Orbitron', sans-serif;
            font-weight: 600;
            font-size: 24px;
            color: var(--primary);
            letter-spacing: 2px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .token-display {
            background: var(--glass);
            border: 1px solid #fbbf24;
            padding: 5px 15px;
            border-radius: 50px;
            color: #fbbf24;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: 0.3s;
        }

        .token-display:hover {
            background: rgba(251, 191, 36, 0.1);
            transform: scale(1.05);
        }

        .hamburger {
            cursor: pointer;
            font-size: 26px;
            color: var(--text);
            opacity: 0.8;
            transition: 0.3s;
        }

        .hamburger:hover {
            color: var(--primary);
            opacity: 1;
        }

        /* Sidebar Glassmorphism */
        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(6px);
            display: none;
            z-index: 1001;
        }

        .side-nav {
            position: fixed;
            top: 0;
            right: -400px;
            width: 380px;
            height: 100%;
            background: var(--card);
            z-index: 1002;
            transition: var(--transition);
            padding: 40px 30px;
            border-left: 1px solid var(--border);
            box-shadow: -10px 0 30px rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .side-nav.active {
            right: 0;
        }

        .profile-img-circle {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, var(--primary), #f97316);
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 700;
            overflow: hidden;
            box-shadow: 0 0 20px var(--primary-glow);
            border: 3px solid var(--card);
            cursor: pointer;
            transition: 0.3s;
        }

        .profile-img-circle:hover {
            transform: scale(1.05);
            filter: brightness(1.1);
        }

        .profile-img-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-title-tag {
            display: block;
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            margin-top: -10px;
            margin-bottom: 10px;
        }

        .rank-badge {
            text-align: center;
            margin-bottom: 15px;
        }

        .rank-badge span {
            background: var(--rank-color);
            color: #fff;
            padding: 4px 15px;
            border-radius: 100px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stats-grid-mini {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-pill {
            background: var(--glass);
            border: 1px solid var(--border);
            padding: 15px;
            border-radius: 20px;
            text-align: center;
        }

        .stat-pill i {
            color: var(--primary);
            font-size: 20px;
            margin-bottom: 8px;
            display: block;
        }

        .stat-pill small {
            font-size: 10px;
            opacity: 0.5;
            text-transform: uppercase;
        }

        .stat-pill b {
            display: block;
            font-size: 16px;
            margin-top: 2px;
        }

        /* History Section */
        .history-scroll-box {
            max-height: 250px;
            overflow-y: auto;
            padding-right: 5px;
        }

        .history-item {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--glass);
            padding: 10px;
            border-radius: 18px;
            margin-bottom: 10px;
            border: 1px solid var(--border);
            cursor: pointer;
            transition: 0.3s;
        }

        .history-item:hover {
            background: rgba(255, 255, 255, 0.06);
            transform: scale(1.02);
        }

        .history-img {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            overflow: hidden;
            background: #000;
            flex-shrink: 0;
        }

        .history-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .accuracy-text {
            font-size: 11px;
            font-weight: 700;
            color: #22c55e;
        }

        /* Welcome Section */
        .welcome-section {
            padding: 60px 5% 40px;
            text-align: center;
        }

        .welcome-section h2 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .xp-wrapper {
            max-width: 400px;
            margin: 20px auto;
        }

        .xp-bar {
            width: 100%;
            height: 12px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            border: 1px solid var(--border);
            overflow: hidden;
            position: relative;
        }

        .xp-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), #f97316);
            box-shadow: 0 0 15px var(--primary);
            transition: 1.5s ease-out;
        }

        /* Mission Grid */
        .mission-section {
            padding: 20px 5% 100px;
        }

        .mission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .m-card {
            background: var(--card);
            border-radius: 32px;
            border: 1px solid var(--border);
            overflow: hidden;
            transition: var(--transition);
            position: relative;
            cursor: pointer;
        }

        .m-card:hover {
            transform: translateY(-12px);
            border-color: var(--primary);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }

        .m-media {
            width: 100%;
            height: 200px;
            background: #000;
            position: relative;
            overflow: hidden;
        }

        .m-media img,
        .m-media video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: 0.5s;
        }

        .m-card:hover .m-media img {
            transform: scale(1.1);
        }

        .m-badge-xp {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--primary);
            color: #fff;
            padding: 6px 15px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
            z-index: 5;
        }

        .m-badge-token {
            position: absolute;
            top: 50px;
            left: 15px;
            background: #fbbf24;
            color: #000;
            padding: 4px 12px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 800;
            z-index: 5;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }

        .m-badge-limit {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            color: #fff;
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            z-index: 5;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .m-badge-limit b {
            color: var(--primary);
        }

        .m-info {
            padding: 25px;
        }

        .m-info h4 {
            font-size: 20px;
            margin-bottom: 5px;
        }

        .m-info p {
            font-size: 13px;
            opacity: 0.6;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Modal & Shop Styles */
        .modal-mission,
        .modal-shop,
        .modal-profile,
        .modal-inventory {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(10px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        .modal-content {
            background: var(--card);
            border: 1px solid var(--border);
            width: 90%;
            max-width: 480px;
            border-radius: 35px;
            overflow: hidden;
            animation: zoomIn 0.3s ease;
        }

        .modal-shop .modal-content,
        .modal-inventory .modal-content {
            max-width: 900px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
        }

        @keyframes zoomIn {
            from {
                transform: scale(0.9);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .swal2-container {
            z-index: 99999 !important;
        }

        .shop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
            padding: 30px;
            overflow-y: auto;
            flex-grow: 1;
        }

        .shop-grid::-webkit-scrollbar {
            width: 5px;
        }

        .shop-grid::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }

        .shop-grid::-webkit-scrollbar-track {
            background: transparent;
        }

        .shop-card {
            background: var(--glass);
            border: 1px solid var(--border);
            border-radius: 25px;
            padding: 25px;
            text-align: center;
            transition: 0.3s;
            position: relative;
            overflow: hidden;
        }

        .shop-card:hover {
            border-color: #fbbf24;
            transform: translateY(-5px);
        }

        .shop-card .rarity {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.5;
            margin-bottom: 10px;
            display: block;
        }

        .shop-card h4 {
            font-size: 18px;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .shop-card .price {
            color: #fbbf24;
            font-weight: 700;
            margin-bottom: 20px;
            font-size: 16px;
        }

        .profile-modal-img {
            width: 100%;
            height: 350px;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .profile-modal-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-modal-img i {
            font-size: 100px;
            opacity: 0.1;
        }

        .btn-main {
            width: 100%;
            padding: 15px;
            border-radius: 18px;
            border: none;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #f97316);
            color: #fff;
            box-shadow: 0 10px 20px var(--primary-glow);
        }

        .btn-primary:hover {
            transform: scale(1.02);
            filter: brightness(1.1);
        }

        .btn-ghost {
            background: transparent;
            color: var(--text);
            opacity: 0.6;
            border: 1px solid var(--border);
        }

        .btn-shop-buy {
            background: #fbbf24;
            color: #000;
            padding: 10px;
            border-radius: 12px;
            border: none;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
        }

        .btn-equipped {
            background: #22c55e;
            color: #fff;
            cursor: default;
        }

        .btn-equip {
            background: var(--primary);
            color: #fff;
        }

        .btn-unequip {
            background: #333;
            color: #fff;
        }

        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 10px;
        }
    </style>
</head>

<body>

    <div class="bg-glow"></div>

    <header>
        <div class="logo">GYMFITT</div>
        <div class="header-right">
            <div class="token-display" onclick="openShop()">
                <i class="fa-solid fa-coins"></i> <span><?= number_format($tokens) ?></span>
            </div>
            <div class="hamburger" onclick="toggleNav()"><i class="fa-solid fa-bars-staggered"></i></div>
        </div>
    </header>

    <div class="overlay" id="overlay" onclick="toggleNav()"></div>

    <div class="side-nav" id="sideNav">
        <div class="profile-section" style="margin-bottom: 25px;">
            <div class="profile-img-circle" onclick="openProfileModal()">
                <?php if ($profile_pic): ?><img src="<?= $profile_pic ?>"><?php else: ?><div style="color:white;"><?= mb_substr($full_name, 0, 1) ?></div><?php endif; ?>
            </div>
            <?php if ($active_title): ?>
                <span class="user-title-tag" style="color: <?= $active_title_color ?>;">[ <?= $active_title ?> ]</span>
            <?php endif; ?>
            <div class="rank-badge"><span><?= $rank_icon ?> <?= $rank_name ?></span></div>
            <h2 style="text-align:center; font-size: 20px;"><?= htmlspecialchars($full_name) ?></h2>
        </div>

        <div class="stats-grid-mini">
            <div class="stat-pill"><i class="fa-solid fa-fire-flame-curved"></i><small>Streak</small><b><?= $streak ?> วัน</b></div>
            <div class="stat-pill"><i class="fa-solid fa-weight-scale"></i><small>BMI</small><b><?= $bmi ?></b></div>
        </div>

        <div style="flex:1; overflow:hidden; display:flex; flex-direction:column;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h4 style="font-size: 14px; opacity: 0.8;">🕒 ประวัติล่าสุด</h4>
                <form method="POST" onsubmit="return confirm('ล้างประวัติทั้งหมด?')">
                    <button type="submit" name="clear_history" style="background:none; border:none; color:var(--primary); font-size:11px; cursor:pointer;">ล้างข้อมูล</button>
                </form>
            </div>
            <div class="history-scroll-box">
                <?php if ($history_list->num_rows > 0): ?>
                    <?php while ($h = $history_list->fetch_assoc()): ?>
                        <div class="history-item" onclick='openHistoryPopup(<?= json_encode($h) ?>)'>
                            <div class="history-img"><img src="uploads/<?= $h['display_image'] ?>" onerror="this.src='assets/no-image.png'"></div>
                            <div style="flex:1">
                                <h5 style="font-size:12px; margin-bottom:2px;"><?= htmlspecialchars($h['display_name']) ?></h5>
                                <small style="font-size:10px; opacity:0.5;"><?= date('d M, H:i', strtotime($h['workout_date'])) ?></small>
                            </div>
                            <span class="accuracy-text"><?= $h['accuracy'] ?>%</span>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align:center; opacity:0.4; font-size:12px; margin-top:20px;">ไม่มีข้อมูล</p>
                <?php endif; ?>
            </div>
        </div>

        <div style="margin-top: 20px;">
            <button class="btn-main btn-primary" style="margin-bottom:10px;" onclick="window.location='edit_profile.php'"><i class="fa-solid fa-user-gear"></i> โปรไฟล์</button>
            <button class="btn-main btn-ghost" onclick="toggleTheme()"><i class="fa-solid fa-palette"></i> ธีมสี</button>
            <a href="logout.php" style="display:block; text-align:center; color:var(--primary); text-decoration:none; font-size:12px; margin-top:15px; font-weight:700;">ออกจากระบบ</a>
        </div>
    </div>

    <main>
        <div class="welcome-section">
            <h2>สวัสดีคุณ <?= explode(' ', $full_name)[0] ?> 👋</h2>
            <div class="xp-wrapper">
                <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:8px; font-weight:600;">
                    <span>LVL <?= $current_level ?></span>
                    <span style="opacity:0.6;"><?= $current_exp ?> / <?= $required_exp ?> XP</span>
                </div>
                <div class="xp-bar">
                    <div class="xp-bar-fill" style="width: <?= $level_percent ?>%"></div>
                </div>
            </div>
        </div>

        <div class="mission-section">
            <h3 style="margin-bottom: 25px; display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-circle-play" style="color:var(--primary);"></i> ภารกิจประจำวัน (Missions)</h3>
            <div class="mission-grid">
                <?php while ($m = $missions->fetch_assoc()):
                    $limit = isset($m['daily_limit']) ? (int)$m['daily_limit'] : 1;
                    $done = (int)$m['today_play_count'];
                    $is_locked = ($done >= $limit);
                ?>
                    <div class="m-card" onclick="openMissionModal(<?= htmlspecialchars(json_encode($m)) ?>)">
                        <div class="m-badge-xp">+<?= $m['exp_reward'] ?> XP</div>
                        <?php if (!empty($m['token_reward']) && $m['token_reward'] > 0): ?>
                            <div class="m-badge-token" style="top:50px;"><i class="fa-solid fa-coins"></i> +<?= number_format($m['token_reward']) ?></div>
                        <?php endif; ?>
                        <div class="m-badge-limit"><i class="fa-solid fa-check-double"></i> <b><?= $done ?></b> / <?= $limit ?></div>
                        <div class="m-media">
                            <?php $file = $m['mission_image'];
                            $filePath = "uploads/" . $file;
                            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                            if (!empty($file) && file_exists($filePath)): if (in_array($ext, ['mp4', 'webm'])): ?>
                                    <video src="<?= $filePath ?>" autoplay muted loop playsinline></video>
                                <?php else: ?><img src="<?= $filePath ?>" style="<?= $is_locked ? 'filter: grayscale(1) opacity(0.4);' : '' ?>"><?php endif; ?>
                            <?php else: ?><div style="height:100%; display:flex; align-items:center; justify-content:center; background:#222; color:#444;"><i class="fa-solid fa-image fa-3x"></i></div><?php endif; ?>
                            <?php if ($is_locked): ?><div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.6); z-index:10; backdrop-filter:blur(3px);"><i class="fa-solid fa-lock fa-3x" style="color:var(--primary); opacity:0.7;"></i></div><?php endif; ?>
                        </div>
                        <div class="m-info">
                            <h4><?= htmlspecialchars($m['mission_name']) ?></h4>
                            <p><?= htmlspecialchars($m['mission_detail'] ?? 'ภารกิจทดสอบความแข็งแกร่ง') ?></p>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-size:12px; opacity:0.5;"><i class="fa-solid fa-arrows-rotate"></i> จัดเซ็ตเรียบร้อย</span>
                                <span style="color:<?= $is_locked ? '#555' : 'var(--primary)' ?>; font-weight:700; font-size:13px;"><?= $is_locked ? 'LOCKED' : 'START →' ?></span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <h3 style="margin-top: 50px; margin-bottom: 25px; display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-dumbbell" style="color:#38bdf8;"></i> โหมดฝึกอิสระ (Free Training)</h3>
            <div class="mission-grid">
                <?php
                $ex_query = $conn->query("SELECT * FROM exercises ORDER BY exercise_id DESC");
                while ($ex = $ex_query->fetch_assoc()):
                ?>
                    <div class="m-card" onclick="window.location.href='play_exercise.php?id=<?= $ex['exercise_id'] ?>'">
                        <div class="m-badge-xp" style="background:#38bdf8;">+โบนัส XP</div>
                        <div class="m-media">
                            <?php if (!empty($ex['exercise_image'])): ?>
                                <img src="uploads/<?= htmlspecialchars($ex['exercise_image']) ?>">
                            <?php else: ?>
                                <div style="height:100%; display:flex; align-items:center; justify-content:center; background:#222; color:#444;"><i class="fa-solid fa-person-walking fa-3x"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="m-info">
                            <h4><?= htmlspecialchars($ex['exercise_name']) ?></h4>
                            <p><?= htmlspecialchars($ex['exercise_detail']) ?></p>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-size:12px; opacity:0.5;">ฝึกซ้อมอิสระ ไม่จำกัดครั้ง</span>
                                <span style="color:#38bdf8; font-weight:700; font-size:13px;">PRACTICE →</span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </main>

    <div class="modal-profile" id="profileModal">
        <div class="modal-content" style="max-width: 400px; border-radius: 40px;">
            <div class="profile-modal-img">
                <?php if ($profile_pic): ?>
                    <img src="<?= $profile_pic ?>" alt="Profile">
                <?php else: ?>
                    <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </div>
            <div style="padding: 30px; text-align: center;">
                <h2 style="font-size: 24px; margin-bottom: 5px;"><?= htmlspecialchars($full_name) ?></h2>
                <?php if ($active_title): ?>
                    <p style="color: <?= $active_title_color ?>; font-weight: 700; font-family: 'Orbitron'; font-size: 14px; margin-bottom: 15px;"><?= $active_title ?></p>
                <?php endif; ?>

                <div style="display: flex; justify-content: center; gap: 15px; margin-bottom: 25px;">
                    <div style="background: var(--glass); padding: 10px 20px; border-radius: 15px; border: 1px solid var(--border);">
                        <small style="display: block; opacity: 0.5; font-size: 10px;">LEVEL</small>
                        <b style="font-size: 18px; color: var(--primary);"><?= $current_level ?></b>
                    </div>
                    <div style="background: var(--glass); padding: 10px 20px; border-radius: 15px; border: 1px solid var(--border);">
                        <small style="display: block; opacity: 0.5; font-size: 10px;">TOKENS</small>
                        <b style="font-size: 18px; color: #fbbf24;"><?= number_format($tokens) ?></b>
                    </div>
                </div>

                <button class="btn-main btn-ghost" style="margin-bottom: 10px;" onclick="openInventory()"><i class="fa-solid fa-award"></i> คลังฉายาของฉัน</button>
                <button class="btn-main btn-primary" onclick="closeProfileModal()">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <div class="modal-inventory" id="inventoryModal">
        <div class="modal-content">
            <div style="padding: 30px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                <h2 style="font-family: 'Orbitron'; color: var(--primary);"><i class="fa-solid fa-award"></i> MY TITLES</h2>
                <button class="hamburger" onclick="closeInventory()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="shop-grid">
                <?php if ($owned_titles->num_rows > 0): ?>
                    <?php while ($ot = $owned_titles->fetch_assoc()):
                        $is_active = ($ot['title_id'] == $current_title_id);
                    ?>
                        <div class="shop-card" style="border-color: <?= $is_active ? 'var(--primary)' : 'var(--border)' ?>;">
                            <span class="rarity"><?= $ot['rarity'] ?></span>
                            <h4 style="color: <?= $ot['title_color'] ?>;"><?= htmlspecialchars($ot['title_name']) ?></h4>
                            <?php if ($is_active): ?>
                                <button class="btn-shop-buy btn-equipped">ใช้งานอยู่</button>
                                <a href="process_equip.php?action=unequip" style="display:block; margin-top:10px; font-size:12px; color:#666; text-decoration:none;">ยกเลิกการใช้งาน</a>
                            <?php else: ?>
                                <button class="btn-shop-buy btn-equip" onclick="location.href='process_equip.php?action=equip&title_id=<?= $ot['title_id'] ?>'">กดใช้งาน</button>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 50px;">
                        <p style="opacity: 0.5;">คุณยังไม่มีฉายาในครอบครอง</p>
                        <button class="btn-main btn-primary" style="width:auto; margin-top:20px; padding: 10px 30px;" onclick="openShop()">ไปที่ร้านค้า</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal-shop" id="shopModal">
        <div class="modal-content">
            <div style="padding: 30px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                <h2 style="font-family: 'Orbitron'; color: #fbbf24;"><i class="fa-solid fa-store"></i> TITLE SHOP</h2>
                <button class="hamburger" onclick="closeShop()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="shop-grid">
                <?php if ($shop_items->num_rows > 0): ?>
                    <?php while ($item = $shop_items->fetch_assoc()): ?>
                        <div class="shop-card">
                            <span class="rarity"><?= $item['rarity'] ?></span>
                            <h4 style="color: <?= $item['title_color'] ?>;"><?= htmlspecialchars($item['title_name']) ?></h4>
                            <div class="price"><i class="fa-solid fa-coins"></i> <?= number_format($item['price']) ?></div>
                            <button class="btn-shop-buy" onclick="confirmBuyTitle(<?= $item['title_id'] ?>, '<?= $item['title_name'] ?>', <?= $item['price'] ?>)">ซื้อฉายา</button>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 50px;">
                        <p style="opacity: 0.5;">คุณมีฉายาทุกอย่างในร้านค้าแล้ว</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal-mission" id="missionModal">
        <div class="modal-content">
            <div id="modalMedia" style="height:250px; background:#000;"></div>
            <div style="padding: 30px;">
                <h2 id="modalTitle" style="color: var(--primary); margin-bottom: 10px;"></h2>
                <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:15px;">
                    <div style="background:var(--glass); border:1px solid var(--border); padding:8px 15px; border-radius:12px; font-size:12px;">
                        🎯 โหมด: <b id="modalMaxSets"></b>
                    </div>
                    <div style="background:var(--glass); border:1px solid var(--border); padding:8px 15px; border-radius:12px; font-size:12px;">
                        📊 วันนี้ทำไปแล้ว: <b id="modalProgress"></b>
                    </div>
                    <div style="background:rgba(251, 191, 36, 0.1); border:1px solid #fbbf24; padding:8px 15px; border-radius:12px; font-size:12px; color:#fbbf24;">
                        <i class="fa-solid fa-coins"></i> รางวัล: <b id="modalTokenReward"></b>
                    </div>
                </div>
                <p id="modalDesc" style="font-size: 14px; opacity: 0.7; line-height: 1.6; margin-bottom: 25px;"></p>
                <div id="modalButtonContainer"></div>
                <button class="btn-main btn-ghost" style="margin-top:12px;" onclick="closeMissionModal()">ย้อนกลับ</button>
            </div>
        </div>
    </div>

    <div class="modal-mission" id="historyModal">
        <div class="modal-content">
            <div id="historyModalMedia" style="height:220px; background:#000;"></div>
            <div style="padding: 30px; text-align: center;">
                <h2 id="historyModalTitle" style="color: var(--primary); font-size: 20px; margin-bottom: 5px;"></h2>
                <p style="font-size:12px; opacity:0.5; margin-bottom: 20px;">สำเร็จเมื่อ: <span id="historyModalDate"></span></p>
                <div style="background:var(--glass); border:1px solid var(--border); padding: 15px; border-radius: 20px; display:inline-block; min-width: 150px;">
                    <div style="font-size:11px; opacity:0.5; text-transform:uppercase;">Precision</div>
                    <div id="historyModalAccuracy" style="font-size:32px; font-weight:700; color:#22c55e;"></div>
                </div>
                <button class="btn-main btn-primary" style="margin-top:25px;" onclick="closeHistoryModal()">ปิด</button>
            </div>
        </div>
    </div>

    <script>
        function toggleNav() {
            const nav = document.getElementById("sideNav");
            const over = document.getElementById("overlay");
            nav.classList.toggle("active");
            over.style.display = nav.classList.contains("active") ? "block" : "none";
        }

        function openShop() {
            closeInventory();
            document.getElementById('shopModal').style.display = 'flex';
        }

        function closeShop() {
            document.getElementById('shopModal').style.display = 'none';
        }

        function openInventory() {
            closeProfileModal();
            document.getElementById('inventoryModal').style.display = 'flex';
        }

        function closeInventory() {
            document.getElementById('inventoryModal').style.display = 'none';
        }

        function openProfileModal() {
            document.getElementById('profileModal').style.display = 'flex';
        }

        function closeProfileModal() {
            document.getElementById('profileModal').style.display = 'none';
        }

        function confirmBuyTitle(id, name, price) {
            const userTokens = <?= $tokens ?>;
            if (userTokens < price) {
                Swal.fire({
                    title: 'Token ไม่พอ!',
                    text: 'ออกกำลังกายสะสมเพิ่มก่อนนะ',
                    icon: 'error',
                    background: '#121212',
                    color: '#fff'
                });
                return;
            }
            Swal.fire({
                title: 'ยืนยันการซื้อ?',
                html: `คุณต้องการซื้อฉายา <b style="color:#fbbf24">${name}</b><br>ราคา ${price} Tokens ใช่หรือไม่?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#fbbf24',
                confirmButtonText: 'ยืนยันการซื้อ',
                cancelButtonText: 'ถอยตั้งหลัก',
                background: '#121212',
                color: '#fff'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `process_buy_title.php?title_id=${id}`;
                }
            });
        }

        function openMissionModal(m) {
            document.getElementById('modalTitle').innerText = m.mission_name;
            document.getElementById('modalDesc').innerText = m.mission_detail || 'ไม่มีรายละเอียดเพิ่มเติม';

            // [แก้ไขแล้ว] ไม่ต้องเรียก max_sets ที่ไม่มีแล้วในฐานข้อมูล ให้ใช้ข้อความแทน
            document.getElementById('modalMaxSets').innerText = 'ภารกิจจัดเซ็ต';

            document.getElementById('modalTokenReward').innerText = (m.token_reward || 0) + ' Tokens';
            const limit = m.daily_limit ? parseInt(m.daily_limit) : 1;
            const done = parseInt(m.today_play_count);
            document.getElementById('modalProgress').innerText = `${done} / ${limit} รอบ`;
            const media = document.getElementById('modalMedia');

            // [แก้ไขแล้ว] เรียกใช้ mission_image ตามตารางใหม่
            if (m.mission_image) {
                let path = "uploads/" + m.mission_image;
                let ext = m.mission_image.split('.').pop().toLowerCase();
                media.innerHTML = (['mp4', 'webm'].includes(ext)) ? `<video src="${path}" autoplay muted loop style='width:100%; height:100%; object-fit:cover;'></video>` : `<img src='${path}' style='width:100%; height:100%; object-fit:cover;'>`;
            } else {
                media.innerHTML = '';
            }

            const isLocked = (done >= limit);
            document.getElementById('modalButtonContainer').innerHTML = isLocked ? `<button class="btn-main" disabled style="background:#333; color:#666;">🔒 สิทธิ์วันนี้เต็มแล้ว (${done}/${limit})</button>` : `<button class="btn-main btn-primary" onclick="location.href='play_mission.php?mission_id=${m.mission_id}'">START TRAINING</button>`;
            document.getElementById('missionModal').style.display = 'flex';
        }

        function openHistoryPopup(h) {
            // [แก้ไขแล้ว] ดึงชื่อและรูปตามตัวแปรแบบใหม่
            document.getElementById('historyModalTitle').innerText = h.display_name;
            document.getElementById('historyModalDate').innerText = h.workout_date;
            document.getElementById('historyModalAccuracy').innerText = h.accuracy + '%';

            const media = document.getElementById('historyModalMedia');
            if (h.display_image) {
                let path = "uploads/" + h.display_image;
                let ext = h.display_image.split('.').pop().toLowerCase();
                media.innerHTML = (['mp4', 'webm'].includes(ext)) ? `<video src="${path}" autoplay muted loop style='width:100%; height:100%; object-fit:cover;'></video>` : `<img src='${path}' style='width:100%; height:100%; object-fit:cover;'>`;
            } else {
                media.innerHTML = '';
            }
            document.getElementById('historyModal').style.display = 'flex';
        }

        function closeMissionModal() {
            document.getElementById('missionModal').style.display = 'none';
        }

        function closeHistoryModal() {
            document.getElementById('historyModal').style.display = 'none';
        }

        function toggleTheme() {
            document.body.classList.toggle('light-mode');
            localStorage.setItem('gymfitt-theme', document.body.classList.contains('light-mode') ? 'light' : 'dark');
        }
        (function() {
            if (localStorage.getItem('gymfitt-theme') === 'light') document.body.classList.add('light-mode');
        })();

        window.onclick = (e) => {
            if (e.target == document.getElementById('missionModal')) closeMissionModal();
            if (e.target == document.getElementById('historyModal')) closeHistoryModal();
            if (e.target == document.getElementById('shopModal')) closeShop();
            if (e.target == document.getElementById('profileModal')) closeProfileModal();
            if (e.target == document.getElementById('inventoryModal')) closeInventory();
        }

        // ตรวจสอบสถานะต่างๆ จาก URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('buy_status') === 'success') {
            Swal.fire({
                title: 'ซื้อสำเร็จ!',
                text: 'ฉายาใหม่ถูกเพิ่มในคลังแล้ว',
                icon: 'success',
                background: '#121212',
                color: '#fff',
                timer: 2000,
                showConfirmButton: false
            });
        }
        if (urlParams.get('equip') === 'success') {
            Swal.fire({
                title: 'สำเร็จ!',
                text: 'ติดตั้งฉายาเรียบร้อยแล้ว',
                icon: 'success',
                background: '#121212',
                color: '#fff',
                timer: 1500,
                showConfirmButton: false
            });
        }
        if (urlParams.get('unequip') === 'success') {
            Swal.fire({
                title: 'สำเร็จ!',
                text: 'ถอดฉายาเรียบร้อยแล้ว',
                icon: 'success',
                background: '#121212',
                color: '#fff',
                timer: 1500,
                showConfirmButton: false
            });
        }
    </script>

</body>

</html>