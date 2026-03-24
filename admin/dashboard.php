<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

include "../config/config.php";

/* ===== ระบบลบภารกิจ ===== */
$delete_success = false;
if (isset($_GET['delete_mission'])) {
    $id_to_delete = intval($_GET['delete_mission']);
    $stmt = $conn->prepare("DELETE FROM missions WHERE mission_id = ?");
    $stmt->bind_param("i", $id_to_delete);
    if ($stmt->execute()) {
        $delete_success = true;
    }
}

/* ===== ระบบลบท่าออกกำลังกาย ===== */
$delete_ex_success = false;
if (isset($_GET['delete_exercise'])) {
    $id_to_delete = intval($_GET['delete_exercise']);
    $stmt = $conn->prepare("DELETE FROM exercises WHERE exercise_id = ?");
    $stmt->bind_param("i", $id_to_delete);
    if ($stmt->execute()) {
        $delete_ex_success = true;
    }
}

/* ===== ระบบลบฉายา ===== */
$title_delete_success = false;
if (isset($_GET['delete_title'])) {
    $id_to_delete = intval($_GET['delete_title']);
    $stmt = $conn->prepare("DELETE FROM shop_titles WHERE title_id = ?");
    $stmt->bind_param("i", $id_to_delete);
    if ($stmt->execute()) {
        $title_delete_success = true;
    }
}

/* ===== ระบบแก้ไขฉายา ===== */
$title_update_success = false;
if (isset($_POST['update_title'])) {
    $title_id = intval($_POST['title_id']);
    $title_name = $_POST['title_name'];
    $rarity = $_POST['rarity'];
    $price = intval($_POST['price']);
    $title_color = $_POST['title_color'];

    $stmt = $conn->prepare("UPDATE shop_titles SET title_name = ?, rarity = ?, price = ?, title_color = ? WHERE title_id = ?");
    $stmt->bind_param("ssisi", $title_name, $rarity, $price, $title_color, $title_id);
    if ($stmt->execute()) {
        $title_update_success = true;
    }
}

/* ===== ดึงข้อมูลสถิติและตาราง ===== */
$totalUsers = $conn->query("SELECT COUNT(*) as t FROM users")->fetch_assoc()['t'];
$totalMissions = $conn->query("SELECT COUNT(*) as t FROM missions")->fetch_assoc()['t'];
$totalExercises = $conn->query("SELECT COUNT(*) as t FROM exercises")->fetch_assoc()['t'];

$missionList = $conn->query("SELECT * FROM missions ORDER BY mission_id DESC");
$exerciseList = $conn->query("SELECT * FROM exercises ORDER BY exercise_id DESC");

$titlesQuery = $conn->query("SELECT * FROM shop_titles ORDER BY title_id DESC");
$totalTitles = ($titlesQuery) ? $titlesQuery->num_rows : 0;
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GYMFITT Pro Admin | Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #ef4444;
            --primary-glow: rgba(239, 68, 68, 0.4);
            --bg-dark: #09090b;
            --card-dark: #18181b;
            --sidebar-w: 260px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', 'Sarabun', sans-serif;
            background-color: var(--bg-dark);
            color: #fafafa;
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* SIDEBAR */
        .sidebar {
            width: var(--sidebar-w);
            background: var(--card-dark);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 100;
            transition: var(--transition);
        }

        .sidebar-header {
            padding: 30px;
            text-align: center;
        }

        .sidebar-header .logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: 1px;
            text-shadow: 0 0 15px var(--primary-glow);
        }

        .nav-links {
            padding: 20px;
            list-style: none;
            flex-grow: 1;
        }

        .nav-links li {
            margin-bottom: 10px;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #a1a1aa;
            text-decoration: none;
            border-radius: 12px;
            transition: var(--transition);
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: rgba(239, 68, 68, 0.1);
            color: var(--primary);
        }

        .logout-section {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .logout-link {
            color: #f87171;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            border-radius: 12px;
            transition: var(--transition);
            cursor: pointer;
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: var(--sidebar-w);
            flex-grow: 1;
            padding: 40px;
            width: calc(100% - var(--sidebar-w));
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 15px;
            background: var(--card-dark);
            padding: 8px 20px;
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }

        .stat-card {
            background: var(--card-dark);
            padding: 30px;
            border-radius: 24px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.03);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary-glow);
        }

        .stat-card i {
            position: absolute;
            right: -10px;
            bottom: -10px;
            font-size: 80px;
            opacity: 0.05;
            color: var(--primary);
        }

        .stat-card .value {
            font-size: 36px;
            font-weight: 700;
            color: #fff;
        }

        /* TABLE DESIGN */
        .table-container {
            background: var(--card-dark);
            border-radius: 24px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.03);
            margin-bottom: 40px;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .add-btn {
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 18px;
            color: #71717a;
            font-size: 13px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        td {
            padding: 18px;
            font-size: 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        }

        .xp-badge {
            background: rgba(239, 68, 68, 0.1);
            color: var(--primary);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            width: 35px;
            height: 35px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }

        .edit {
            background: rgba(56, 189, 248, 0.1);
            color: #38bdf8;
        }

        .delete {
            background: rgba(239, 68, 68, 0.1);
            color: var(--primary);
        }

        /* MODAL STYLE */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        .modal-content {
            background: var(--card-dark);
            width: 100%;
            max-width: 450px;
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 30px;
            position: relative;
            animation: slideUp 0.3s ease;
        }

        .modal-content.large {
            max-width: 800px;
        }

        @keyframes slideUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #a1a1aa;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            background: #09090b;
            border: 1px solid #27272a;
            padding: 12px 15px;
            border-radius: 12px;
            color: white;
            outline: none;
            transition: 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary);
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            cursor: pointer;
            color: #71717a;
            font-size: 20px;
        }

        /* Title Inventory Design */
        .inventory-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            max-height: 500px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .title-card {
            background: #09090b;
            border-radius: 18px;
            padding: 20px;
            border: 1px solid #27272a;
            text-align: center;
        }

        .rarity-badge {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 50px;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 10px;
            display: inline-block;
        }

        .rarity-Common {
            background: #52525b;
            color: white;
        }

        .rarity-Rare {
            background: #3b82f6;
            color: white;
        }

        .rarity-Epic {
            background: #a855f7;
            color: white;
        }

        .rarity-Legendary {
            background: #eab308;
            color: black;
        }

        .swal2-container {
            z-index: 9999 !important;
        }

        /* LIGHT MODE */
        body.light-mode {
            background-color: #f4f4f5;
            color: #18181b;
        }

        .light-mode .sidebar,
        .light-mode .stat-card,
        .light-mode .table-container,
        .light-mode .modal-content,
        .light-mode .admin-profile,
        .light-mode .title-card {
            background: white;
            border-color: #e4e4e7;
        }

        .light-mode .nav-links a {
            color: #52525b;
        }

        .light-mode .stat-card .value {
            color: #18181b;
        }

        .light-mode td {
            color: #27272a;
            border-bottom-color: #f1f1f4;
        }

        .light-mode .form-control {
            background: #f8f8f9;
            color: #18181b;
            border-color: #d4d4d8;
        }

        .light-mode .modal-content h2 {
            color: #18181b;
        }

        .gymfitt-swal-popup {
            border-radius: 20px !important;
            padding: 20px !important;
        }
    </style>
</head>

<body>

    <nav class="sidebar">
        <div class="sidebar-header">
            <div class="logo">GYMFITT PRO</div>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php" class="active"><i class="fa-solid fa-chart-line"></i> Dashboard</a></li>
            <li><a href="members.php"><i class="fa-solid fa-users"></i> จัดการสมาชิก</a></li>
            <li><a href="#" onclick="toggleTheme()"><i class="fa-solid fa-palette"></i> เปลี่ยนธีม</a></li>
        </ul>
        <div class="logout-section">
            <a href="javascript:void(0)" onclick="confirmLogout()" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a>
        </div>
    </nav>

    <main class="main-content">
        <header class="header-section">
            <div>
                <h1 style="font-weight: 600;">สถิติภาพรวม</h1>
                <p>แผงควบคุมหลักสำหรับแอดมิน <span style="color:var(--primary)">@<?= $_SESSION['admin_username'] ?? 'Admin' ?></span></p>
            </div>
            <div class="admin-profile" onclick="openInventoryModal()" style="cursor:pointer;">
                <i class="fa-solid fa-box-open" style="font-size: 20px; color: #fbbf24;"></i>
                <span style="font-weight: 500; font-size: 14px;">คลังฉายา (<?= $totalTitles ?>)</span>
            </div>
        </header>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>สมาชิกทั้งหมด</h3>
                <div class="value"><?= number_format($totalUsers) ?></div>
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="stat-card">
                <h3>ท่าออกกำลังกาย</h3>
                <div class="value"><?= number_format($totalExercises) ?></div>
                <i class="fa-solid fa-person-walking" style="color: #38bdf8;"></i>
            </div>
            <div class="stat-card">
                <h3>ภารกิจในระบบ</h3>
                <div class="value"><?= number_format($totalMissions) ?></div>
                <i class="fa-solid fa-dumbbell"></i>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h2 style="font-size: 18px; font-weight: 600;"><i class="fa-solid fa-person-walking" style="color: #38bdf8;"></i> คลังท่าออกกำลังกาย (Exercises)</h2>
                <a href="add_exercise.php" class="add-btn" style="background: #38bdf8;">
                    <i class="fa-solid fa-plus"></i> เพิ่มท่าใหม่
                </a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>รูปภาพ</th>
                        <th>ชื่อท่าออกกำลังกาย</th>
                        <th>ความแม่นยำ (AI)</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($exerciseList && $exerciseList->num_rows > 0): ?>
                        <?php while ($row = $exerciseList->fetch_assoc()): ?>
                            <tr>
                                <td style="color: #71717a;">#<?= $row['exercise_id'] ?></td>
                                <td>
                                    <?php if ($row['exercise_image']): ?>
                                        <img src="../uploads/<?= htmlspecialchars($row['exercise_image']) ?>" style="width: 50px; height: 50px; border-radius: 10px; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 50px; height: 50px; background: #27272a; border-radius: 10px; display:flex; align-items:center; justify-content:center; font-size: 10px; color:#71717a;">No IMG</div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight: 600; color: #38bdf8;"><?= htmlspecialchars($row['exercise_name']) ?></td>
                                <td><span class="xp-badge" style="background: rgba(56, 189, 248, 0.1); color: #38bdf8;"><i class="fa-solid fa-crosshairs"></i> <?= $row['required_accuracy'] ?>%</span></td>
                                <td>
                                    <div class="actions">
                                        <a href="edit_exercise.php?id=<?= $row['exercise_id'] ?>" class="action-btn edit" title="แก้ไข"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <button class="action-btn delete" onclick="confirmDeleteExercise(<?= $row['exercise_id'] ?>, '<?= htmlspecialchars(addslashes($row['exercise_name'])) ?>')" title="ลบ"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding: 40px; color: #71717a;">ยังไม่มีท่าออกกำลังกายในระบบ</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h2 style="font-size: 18px; font-weight: 600;"><i class="fa-solid fa-dumbbell" style="color: var(--primary);"></i> จัดการระบบภารกิจ (Missions)</h2>
                <div style="display: flex; gap: 10px;">
                    <button onclick="openInventoryModal()" class="add-btn" style="background: #a855f7;">
                        <i class="fa-solid fa-box"></i> คลังฉายา
                    </button>
                    <button onclick="openTitleModal()" class="add-btn" style="background: #fbbf24; color: black;">
                        <i class="fa-solid fa-plus"></i> เพิ่มฉายา
                    </button>
                    <a href="add_mission.php" class="add-btn">
                        <i class="fa-solid fa-plus"></i> เพิ่มภารกิจ
                    </a>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ชื่อภารกิจ</th>
                        <th>จำกัดต่อวัน</th>
                        <th>รางวัล</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($missionList && $missionList->num_rows > 0): ?>
                        <?php while ($row = $missionList->fetch_assoc()): ?>
                            <tr>
                                <td style="color: #71717a;">#<?= $row['mission_id'] ?></td>
                                <td style="font-weight: 600;"><?= htmlspecialchars($row['mission_name']) ?></td>
                                <td>
                                    <div style="font-size: 13px; opacity: 0.8;"><i class="fa-solid fa-clock"></i> <?= $row['daily_limit'] ?> ครั้ง/วัน</div>
                                </td>
                                <td>
                                    <span class="xp-badge">+<?= number_format($row['exp_reward']) ?> XP</span>
                                    <?php if ($row['token_reward'] > 0): ?>
                                        <span class="xp-badge" style="background: rgba(251, 191, 36, 0.1); color: #fbbf24;">+<?= number_format($row['token_reward']) ?> <i class="fa-solid fa-coins"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="edit_mission.php?id=<?= $row['mission_id'] ?>" class="action-btn edit" title="แก้ไข"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <button class="action-btn delete" onclick="confirmDelete(<?= $row['mission_id'] ?>, '<?= htmlspecialchars(addslashes($row['mission_name'])) ?>')" title="ลบ"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding: 40px; color: #71717a;">ยังไม่มีข้อมูลภารกิจในระบบ</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div id="titleModal" class="modal-backdrop">
        <div class="modal-content">
            <i class="fa-solid fa-xmark close-modal" onclick="closeTitleModal()"></i>
            <h2 style="margin-bottom: 25px;"><i class="fa-solid fa-tags" style="color: #fbbf24;"></i> สร้างฉายาใหม่</h2>
            <form action="process_add_title.php" method="POST">
                <div class="form-group">
                    <label>ชื่อฉายา</label>
                    <input type="text" name="title_name" class="form-control" placeholder="เช่น เทพเจ้าสงคราม" required>
                </div>
                <div class="form-group">
                    <label>ระดับความหายาก</label>
                    <select name="rarity" class="form-control">
                        <option value="Common">Common</option>
                        <option value="Rare">Rare</option>
                        <option value="Epic">Epic</option>
                        <option value="Legendary">Legendary</option>
                    </select>
                </div>
                <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div><label>ราคา (Token)</label><input type="number" name="price" class="form-control" value="100" min="0" required></div>
                    <div><label>สีฉายา</label><input type="color" name="title_color" class="form-control" style="height: 48px; padding: 5px;" value="#ef4444"></div>
                </div>
                <button type="submit" class="add-btn" style="width: 100%; justify-content: center; background: #fbbf24; color: black;">
                    <i class="fa-solid fa-check"></i> บันทึกเข้าร้านค้า
                </button>
            </form>
        </div>
    </div>

    <div id="inventoryModal" class="modal-backdrop">
        <div class="modal-content large">
            <i class="fa-solid fa-xmark close-modal" onclick="closeInventoryModal()"></i>
            <h2 style="margin-bottom: 25px;"><i class="fa-solid fa-box-open" style="color: #a855f7;"></i> คลังฉายาทั้งหมด</h2>
            <div class="inventory-list">
                <?php
                if ($totalTitles > 0):
                    $titlesQuery->data_seek(0);
                    while ($title = $titlesQuery->fetch_assoc()): ?>
                        <div class="title-card">
                            <span class="rarity-badge rarity-<?= $title['rarity'] ?>"><?= $title['rarity'] ?></span>
                            <h3 style="color: <?= $title['title_color'] ?>; margin-bottom: 5px;"><?= htmlspecialchars($title['title_name']) ?></h3>
                            <p style="font-size: 13px; color: #fbbf24; margin-bottom: 15px;"><i class="fa-solid fa-coins"></i> <?= number_format($title['price']) ?> Tokens</p>
                            <div class="actions" style="justify-content: center;">
                                <button onclick="openEditTitle(<?= htmlspecialchars(json_encode($title)) ?>)" class="action-btn edit"><i class="fa-solid fa-pen"></i></button>
                                <button onclick="confirmDeleteTitle(<?= $title['title_id'] ?>, '<?= htmlspecialchars(addslashes($title['title_name'])) ?>')" class="action-btn delete"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        </div>
                    <?php endwhile;
                else: ?>
                    <p style="text-align:center; grid-column: 1/-1;">ยังไม่มีฉายาในคลัง</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="editTitleModal" class="modal-backdrop">
        <div class="modal-content">
            <i class="fa-solid fa-xmark close-modal" onclick="closeEditTitleModal()"></i>
            <h2 style="margin-bottom: 25px;"><i class="fa-solid fa-pen-to-square" style="color: #38bdf8;"></i> แก้ไขข้อมูลฉายา</h2>
            <form action="" method="POST">
                <input type="hidden" name="title_id" id="edit_title_id">
                <div class="form-group">
                    <label>ชื่อฉายา</label>
                    <input type="text" name="title_name" id="edit_title_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>ระดับความหายาก</label>
                    <select name="rarity" id="edit_rarity" class="form-control">
                        <option value="Common">Common</option>
                        <option value="Rare">Rare</option>
                        <option value="Epic">Epic</option>
                        <option value="Legendary">Legendary</option>
                    </select>
                </div>
                <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div><label>ราคา (Token)</label><input type="number" name="price" id="edit_price" class="form-control" required></div>
                    <div><label>สีฉายา</label><input type="color" name="title_color" id="edit_title_color" class="form-control" style="height: 48px; padding: 5px;"></div>
                </div>
                <button type="submit" name="update_title" class="add-btn" style="width: 100%; justify-content: center; background: #38bdf8;">
                    <i class="fa-solid fa-save"></i> อัปเดตข้อมูล
                </button>
            </form>
        </div>
    </div>

    <script>
        function toggleTheme() {
            document.body.classList.toggle('light-mode');
            localStorage.setItem('gymfitt-theme', document.body.classList.contains('light-mode') ? 'light' : 'dark');
        }
        (function() {
            if (localStorage.getItem('gymfitt-theme') === 'light') document.body.classList.add('light-mode');
        })();

        function openTitleModal() {
            document.getElementById('titleModal').style.display = 'flex';
        }

        function closeTitleModal() {
            document.getElementById('titleModal').style.display = 'none';
        }

        function openInventoryModal() {
            document.getElementById('inventoryModal').style.display = 'flex';
        }

        function closeInventoryModal() {
            document.getElementById('inventoryModal').style.display = 'none';
        }

        function openEditTitle(data) {
            document.getElementById('edit_title_id').value = data.title_id;
            document.getElementById('edit_title_name').value = data.title_name;
            document.getElementById('edit_rarity').value = data.rarity;
            document.getElementById('edit_price').value = data.price;
            document.getElementById('edit_title_color').value = data.title_color;
            document.getElementById('editTitleModal').style.display = 'flex';
        }

        function closeEditTitleModal() {
            document.getElementById('editTitleModal').style.display = 'none';
        }

        window.onclick = function(e) {
            if (e.target.className === 'modal-backdrop') {
                closeTitleModal();
                closeInventoryModal();
                closeEditTitleModal();
            }
        }

        function getSwalConfig() {
            const isL = document.body.classList.contains('light-mode');
            return {
                background: isL ? '#fff' : '#18181b',
                color: isL ? '#18181b' : '#fafafa',
                confirmButtonColor: '#ef4444',
                customClass: {
                    popup: 'gymfitt-swal-popup'
                }
            };
        }

        function confirmDelete(id, name) {
            Swal.fire({
                ...getSwalConfig(),
                title: 'ลบภารกิจ?',
                text: `ต้องการลบ "${name}" ใช่หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ใช่, ลบเลย',
                cancelButtonText: 'ยกเลิก'
            }).then((r) => {
                if (r.isConfirmed) window.location.href = `?delete_mission=${id}`;
            });
        }

        function confirmDeleteTitle(id, name) {
            Swal.fire({
                ...getSwalConfig(),
                title: 'ลบฉายา?',
                text: `ต้องการลบฉายา "${name}" ออกจากร้านค้าใช่หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ใช่, ลบเลย',
                cancelButtonText: 'ยกเลิก'
            }).then((r) => {
                if (r.isConfirmed) window.location.href = `?delete_title=${id}`;
            });
        }

        function confirmLogout() {
            Swal.fire({
                ...getSwalConfig(),
                title: 'ออกจากระบบ?',
                text: 'คุณต้องการออกจากระบบ Admin ใช่หรือไม่?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'ตกลง',
                cancelButtonText: 'ยกเลิก'
            }).then((r) => {
                if (r.isConfirmed) window.location.href = 'logout.php';
            });
        }

        function confirmDeleteExercise(id, name) {
            Swal.fire({
                ...getSwalConfig(),
                title: 'ลบท่าออกกำลังกาย?',
                text: `ต้องการลบท่า "${name}" ใช่หรือไม่? (ภารกิจที่ใช้ท่านี้อยู่จะได้รับผลกระทบ)`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ใช่, ลบเลย',
                cancelButtonText: 'ยกเลิก'
            }).then((r) => {
                if (r.isConfirmed) window.location.href = `?delete_exercise=${id}`;
            });
        }

        <?php if (isset($delete_success) && $delete_success): ?>
            Swal.fire({
                ...getSwalConfig(),
                title: 'ลบสำเร็จ!',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        <?php endif; ?>

        <?php if (isset($title_delete_success) && $title_delete_success): ?>
            Swal.fire({
                ...getSwalConfig(),
                title: 'ลบฉายาสำเร็จ!',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        <?php endif; ?>

        <?php if (isset($title_update_success) && $title_update_success): ?>
            Swal.fire({
                ...getSwalConfig(),
                title: 'อัปเดตสำเร็จ!',
                text: 'ข้อมูลฉายาถูกแก้ไขเรียบร้อยแล้ว',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        <?php endif; ?>

        <?php if (isset($delete_ex_success) && $delete_ex_success): ?>
            Swal.fire({
                ...getSwalConfig(),
                title: 'ลบท่าออกกำลังกายสำเร็จ!',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        <?php endif; ?>
    </script>
</body>

</html>