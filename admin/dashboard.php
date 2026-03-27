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
<html lang="th" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GYMFITT Pro Admin | Dashboard</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Tailwind Config to match Home Theme -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#ef4444',
                        card: '#121212',
                        darker: '#0a0a0a'
                    },
                    fontFamily: {
                        sans: ['Poppins', 'sans-serif'],
                        orbitron: ['Orbitron', 'sans-serif']
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .swal2-container {
            z-index: 99999 !important;
        }

        .gymfitt-swal-popup {
            border-radius: 24px !important;
            padding: 20px !important;
        }
    </style>
</head>

<body class="bg-gray-50 dark:bg-[#050505] text-gray-900 dark:text-white">

    <!-- Theme Initializer script (Runs before rendering to prevent flash) -->
    <script>
        (function() {
            if (localStorage.getItem('gymfitt-theme') === 'light') {
                document.documentElement.classList.remove('dark');
            } else {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <!-- เอฟเฟกต์แสงพื้นหลัง -->
    <div class="fixed top-20 right-0 w-96 h-96 bg-red-600/10 blur-[100px] rounded-full pointer-events-none z-[-1] hidden dark:block"></div>
    <div class="fixed bottom-0 left-0 w-96 h-96 bg-red-900/10 blur-[100px] rounded-full pointer-events-none z-[-1] hidden dark:block"></div>

    <!-- Sidebar -->
    <aside class="fixed top-0 left-0 h-screen w-64 bg-white dark:bg-card border-r border-gray-200 dark:border-red-900/30 z-50 flex flex-col transition-colors duration-300 shadow-xl">
        <div class="p-6 text-center border-b border-gray-100 dark:border-zinc-800">
            <h2 class="font-bold text-2xl text-primary font-orbitron tracking-wider drop-shadow-md"><i class="fa-solid fa-dumbbell"></i> GYMFITT</h2>
            <span class="text-[10px] font-bold text-gray-500 dark:text-zinc-500 uppercase tracking-widest bg-gray-100 dark:bg-zinc-900 px-3 py-1 rounded-full mt-2 inline-block border border-gray-200 dark:border-zinc-800">Admin Panel</span>
        </div>

        <nav class="flex-1 p-4 space-y-2 overflow-y-auto hide-scrollbar">
            <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-red-50 dark:bg-primary/10 text-primary font-bold transition shadow-sm border border-red-100 dark:border-primary/20">
                <i class="fa-solid fa-chart-pie"></i> ภาพรวมแอดมิน
            </a>
            <a href="members.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 dark:text-zinc-400 hover:bg-gray-50 dark:hover:bg-zinc-800/50 hover:text-primary transition font-medium">
                <i class="fa-solid fa-users"></i> จัดการสมาชิก
            </a>
            <a href="#" onclick="toggleTheme()" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 dark:text-zinc-400 hover:bg-gray-50 dark:hover:bg-zinc-800/50 hover:text-primary transition font-medium">
                <i class="fa-solid fa-palette"></i> เปลี่ยนธีมสี
            </a>
        </nav>

        <div class="p-4 border-t border-gray-100 dark:border-zinc-800">
            <a href="javascript:void(0)" onclick="confirmLogout()" class="flex items-center gap-3 px-4 py-3 rounded-xl text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition font-bold border border-transparent hover:border-red-200 dark:hover:border-red-900/50">
                <i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="ml-64 p-8 min-h-screen relative transition-colors duration-300">

        <!-- Header -->
        <header class="flex justify-between items-center mb-10 bg-white dark:bg-card p-6 rounded-3xl border border-gray-200 dark:border-red-900/30 shadow-sm">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white flex items-center gap-3 drop-shadow-sm">
                    ยินดีต้อนรับ, <span class="text-primary">@<?= $_SESSION['admin_username'] ?? 'Admin' ?></span> 👋
                </h1>
                <p class="text-sm text-gray-500 dark:text-zinc-400 mt-1">นี่คือแผงควบคุมหลักสำหรับจัดการข้อมูลในระบบ GYMFITT</p>
            </div>
            <div class="flex items-center gap-4">
                <div class="bg-yellow-50 dark:bg-yellow-500/10 border border-yellow-200 dark:border-yellow-500/30 px-5 py-2.5 rounded-2xl shadow-sm flex items-center gap-3 cursor-pointer hover:bg-yellow-100 dark:hover:bg-yellow-500/20 transition transform active:scale-95" onclick="openInventoryModal()">
                    <div class="w-8 h-8 rounded-full bg-yellow-400 text-black flex items-center justify-center font-bold shadow-sm">
                        <i class="fa-solid fa-box-open text-sm"></i>
                    </div>
                    <div>
                        <div class="text-[10px] text-yellow-600 dark:text-yellow-400 uppercase font-bold tracking-widest leading-none">ฉายาทั้งหมด</div>
                        <div class="text-sm font-bold text-gray-900 dark:text-white"><?= $totalTitles ?> <span class="text-xs text-gray-500 dark:text-zinc-400">รายการ</span></div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <!-- Stat 1 -->
            <div class="bg-white dark:bg-card rounded-3xl p-6 border border-gray-200 dark:border-red-900/30 shadow-xl dark:shadow-2xl relative overflow-hidden group hover:-translate-y-2 transition duration-300">
                <div class="absolute -bottom-4 -right-4 opacity-5 text-9xl group-hover:scale-110 transition duration-500 text-gray-900 dark:text-white"><i class="fa-solid fa-users"></i></div>
                <div class="relative z-10 flex flex-col h-full">
                    <p class="text-xs font-bold text-gray-500 dark:text-zinc-400 uppercase tracking-widest mb-1"><i class="fa-solid fa-users text-gray-400 mr-1"></i> สมาชิกในระบบ</p>
                    <div class="text-4xl font-extrabold text-gray-900 dark:text-white mt-auto"><?= number_format($totalUsers) ?> <span class="text-sm text-primary font-bold">คน</span></div>
                </div>
            </div>
            <!-- Stat 2 -->
            <div class="bg-white dark:bg-card rounded-3xl p-6 border border-gray-200 dark:border-blue-900/30 shadow-xl dark:shadow-2xl relative overflow-hidden group hover:-translate-y-2 transition duration-300">
                <div class="absolute -bottom-4 -right-4 opacity-5 text-9xl group-hover:scale-110 transition duration-500 text-blue-500"><i class="fa-solid fa-person-walking"></i></div>
                <div class="relative z-10 flex flex-col h-full">
                    <p class="text-xs font-bold text-gray-500 dark:text-zinc-400 uppercase tracking-widest mb-1"><i class="fa-solid fa-person-walking text-blue-500 mr-1"></i> ท่าออกกำลังกาย</p>
                    <div class="text-4xl font-extrabold text-blue-500 mt-auto"><?= number_format($totalExercises) ?> <span class="text-sm text-gray-500 dark:text-zinc-400 font-bold">ท่า</span></div>
                </div>
            </div>
            <!-- Stat 3 -->
            <div class="bg-white dark:bg-card rounded-3xl p-6 border border-gray-200 dark:border-yellow-900/30 shadow-xl dark:shadow-2xl relative overflow-hidden group hover:-translate-y-2 transition duration-300">
                <div class="absolute -bottom-4 -right-4 opacity-5 text-9xl group-hover:scale-110 transition duration-500 text-yellow-500"><i class="fa-solid fa-star"></i></div>
                <div class="relative z-10 flex flex-col h-full">
                    <p class="text-xs font-bold text-gray-500 dark:text-zinc-400 uppercase tracking-widest mb-1"><i class="fa-solid fa-dumbbell text-yellow-500 mr-1"></i> ภารกิจทั้งหมด</p>
                    <div class="text-4xl font-extrabold text-yellow-500 mt-auto"><?= number_format($totalMissions) ?> <span class="text-sm text-gray-500 dark:text-zinc-400 font-bold">ภารกิจ</span></div>
                </div>
            </div>
        </div>

        <!-- Exercises Table -->
        <div class="bg-white dark:bg-card rounded-3xl p-6 lg:p-8 border border-gray-200 dark:border-red-900/30 shadow-xl mb-10 transition-colors duration-300">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2 drop-shadow-sm">
                    <i class="fa-solid fa-person-walking text-blue-500"></i> คลังท่าออกกำลังกาย
                </h2>
                <a href="add_exercise.php" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2.5 px-5 rounded-xl transition shadow-md hover:shadow-lg flex items-center gap-2 transform active:scale-95 text-sm">
                    <i class="fa-solid fa-plus"></i> เพิ่มท่าใหม่
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-gray-400 dark:text-zinc-500 text-xs border-b border-gray-100 dark:border-zinc-800 uppercase tracking-wider">
                            <th class="pb-4 font-semibold px-4">ID</th>
                            <th class="pb-4 font-semibold px-4">รูปภาพ</th>
                            <th class="pb-4 font-semibold px-4">ชื่อท่า</th>
                            <th class="pb-4 font-semibold px-4 text-center">ความแม่นยำ AI</th>
                            <th class="pb-4 font-semibold px-4 text-right">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 dark:text-zinc-300 text-sm">
                        <?php if ($exerciseList && $exerciseList->num_rows > 0): ?>
                            <?php while ($row = $exerciseList->fetch_assoc()): ?>
                                <tr class="border-b border-gray-50 dark:border-zinc-800/50 hover:bg-gray-50 dark:hover:bg-zinc-800/20 transition">
                                    <td class="py-4 px-4 text-gray-400 dark:text-zinc-600 font-medium">#<?= $row['exercise_id'] ?></td>
                                    <td class="py-4 px-4">
                                        <?php if ($row['exercise_image']): ?>
                                            <img src="../uploads/exercises/<?= htmlspecialchars($row['exercise_image']) ?>" onerror="this.src='../uploads/<?= htmlspecialchars($row['exercise_image']) ?>'" class="w-12 h-12 rounded-xl object-cover border border-gray-200 dark:border-zinc-700 shadow-sm">
                                        <?php else: ?>
                                            <div class="w-12 h-12 bg-gray-100 dark:bg-zinc-900 rounded-xl flex items-center justify-center text-[9px] text-gray-400 font-bold border border-gray-200 dark:border-zinc-800">NO IMG</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4 font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($row['exercise_name']) ?></td>
                                    <td class="py-4 px-4 text-center">
                                        <span class="bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 font-bold px-3 py-1.5 rounded-lg border border-blue-200 dark:border-blue-500/20 inline-flex items-center gap-1 text-xs shadow-sm">
                                            <i class="fa-solid fa-crosshairs text-[10px]"></i> <?= $row['required_accuracy'] ?>%
                                        </span>
                                    </td>
                                    <td class="py-4 px-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="edit_exercise.php?id=<?= $row['exercise_id'] ?>" class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center hover:bg-blue-500 hover:text-white dark:hover:bg-blue-500 dark:hover:text-white transition shadow-sm" title="แก้ไข"><i class="fa-solid fa-pen-to-square text-sm"></i></a>
                                            <button onclick="confirmDeleteExercise(<?= $row['exercise_id'] ?>, '<?= htmlspecialchars(addslashes($row['exercise_name'])) ?>')" class="w-9 h-9 rounded-xl bg-red-50 dark:bg-red-500/10 text-primary flex items-center justify-center hover:bg-primary hover:text-white dark:hover:bg-primary dark:hover:text-white transition shadow-sm" title="ลบ"><i class="fa-solid fa-trash text-sm"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="py-12 text-center text-gray-400 italic">ยังไม่มีท่าออกกำลังกายในระบบ</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Missions Table -->
        <div class="bg-white dark:bg-card rounded-3xl p-6 lg:p-8 border border-gray-200 dark:border-red-900/30 shadow-xl transition-colors duration-300">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2 drop-shadow-sm">
                    <i class="fa-solid fa-star text-primary"></i> จัดการระบบภารกิจ
                </h2>
                <div class="flex flex-wrap gap-2">
                    <button onclick="openTitleModal()" class="bg-yellow-400 hover:bg-yellow-500 text-black font-bold py-2.5 px-5 rounded-xl transition shadow-md hover:shadow-lg flex items-center gap-2 transform active:scale-95 text-sm">
                        <i class="fa-solid fa-tags"></i> เพิ่มฉายา
                    </button>
                    <a href="add_mission.php" class="bg-primary hover:bg-red-600 text-white font-bold py-2.5 px-5 rounded-xl transition shadow-md hover:shadow-lg flex items-center gap-2 transform active:scale-95 text-sm">
                        <i class="fa-solid fa-plus"></i> เพิ่มภารกิจ
                    </a>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-gray-400 dark:text-zinc-500 text-xs border-b border-gray-100 dark:border-zinc-800 uppercase tracking-wider">
                            <th class="pb-4 font-semibold px-4">ID</th>
                            <th class="pb-4 font-semibold px-4">ชื่อภารกิจ</th>
                            <th class="pb-4 font-semibold px-4 text-center">จำกัด/วัน</th>
                            <th class="pb-4 font-semibold px-4">รางวัล</th>
                            <th class="pb-4 font-semibold px-4 text-right">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 dark:text-zinc-300 text-sm">
                        <?php if ($missionList && $missionList->num_rows > 0): ?>
                            <?php while ($row = $missionList->fetch_assoc()): ?>
                                <tr class="border-b border-gray-50 dark:border-zinc-800/50 hover:bg-gray-50 dark:hover:bg-zinc-800/20 transition">
                                    <td class="py-4 px-4 text-gray-400 dark:text-zinc-600 font-medium">#<?= $row['mission_id'] ?></td>
                                    <td class="py-4 px-4 font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($row['mission_name']) ?></td>
                                    <td class="py-4 px-4 text-center">
                                        <div class="inline-flex items-center gap-1 text-xs font-semibold text-gray-600 dark:text-zinc-400 bg-gray-100 dark:bg-zinc-800 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-zinc-700 shadow-sm">
                                            <i class="fa-solid fa-rotate-right text-[10px]"></i> <?= $row['daily_limit'] ?> รอบ
                                        </div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="flex flex-wrap gap-2">
                                            <span class="bg-red-50 dark:bg-primary/10 text-primary font-bold px-2.5 py-1 rounded-lg border border-red-200 dark:border-primary/20 text-[10px] shadow-sm">
                                                +<?= number_format($row['exp_reward']) ?> XP
                                            </span>
                                            <?php if ($row['token_reward'] > 0): ?>
                                                <span class="bg-yellow-50 dark:bg-yellow-400/10 text-yellow-600 dark:text-yellow-400 font-bold px-2.5 py-1 rounded-lg border border-yellow-200 dark:border-yellow-400/20 text-[10px] shadow-sm">
                                                    +<?= number_format($row['token_reward']) ?> <i class="fa-solid fa-coins"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="edit_mission.php?id=<?= $row['mission_id'] ?>" class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center hover:bg-blue-500 hover:text-white dark:hover:bg-blue-500 dark:hover:text-white transition shadow-sm" title="แก้ไข"><i class="fa-solid fa-pen-to-square text-sm"></i></a>
                                            <button onclick="confirmDelete(<?= $row['mission_id'] ?>, '<?= htmlspecialchars(addslashes($row['mission_name'])) ?>')" class="w-9 h-9 rounded-xl bg-red-50 dark:bg-red-500/10 text-primary flex items-center justify-center hover:bg-primary hover:text-white dark:hover:bg-primary dark:hover:text-white transition shadow-sm" title="ลบ"><i class="fa-solid fa-trash text-sm"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="py-12 text-center text-gray-400 italic">ยังไม่มีข้อมูลภารกิจในระบบ</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- ================= MODALS ================= -->

    <!-- Modal เพิ่มฉายา -->
    <div id="titleModal" class="fixed inset-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm z-[2000] hidden items-center justify-center p-4">
        <div class="bg-white dark:bg-card w-full max-w-md rounded-3xl border border-gray-200 dark:border-red-900/50 shadow-2xl overflow-hidden transform transition-all">
            <div class="p-6 border-b border-gray-100 dark:border-zinc-800 flex justify-between items-center bg-gray-50 dark:bg-darker">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><i class="fa-solid fa-tags text-yellow-500"></i> สร้างฉายาใหม่</h2>
                <button onclick="closeTitleModal()" class="text-gray-400 hover:text-primary transition"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div class="p-6">
                <form action="process_add_title.php" method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-zinc-300 mb-1">ชื่อฉายา</label>
                        <input type="text" name="title_name" class="w-full bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:border-yellow-500 focus:ring-1 focus:ring-yellow-500 outline-none transition" placeholder="เช่น เทพเจ้าสงคราม" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-zinc-300 mb-1">ระดับความหายาก</label>
                        <select name="rarity" class="w-full bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:border-yellow-500 outline-none transition">
                            <option value="Common">Common</option>
                            <option value="Rare">Rare</option>
                            <option value="Epic">Epic</option>
                            <option value="Legendary">Legendary</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-zinc-300 mb-1">ราคา (Token)</label>
                            <input type="number" name="price" value="100" min="0" class="w-full bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:border-yellow-500 outline-none transition" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-zinc-300 mb-1">สีฉายา</label>
                            <input type="color" name="title_color" value="#ef4444" class="w-full h-[50px] rounded-xl cursor-pointer border border-gray-200 dark:border-zinc-700 p-1 bg-gray-50 dark:bg-zinc-900">
                        </div>
                    </div>
                    <button type="submit" class="w-full mt-4 bg-yellow-400 hover:bg-yellow-500 text-black font-bold py-3 rounded-xl shadow-md hover:shadow-lg transition transform active:scale-95 flex justify-center items-center gap-2">
                        <i class="fa-solid fa-check"></i> บันทึกเข้าร้านค้า
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal คลังฉายาทั้งหมด -->
    <div id="inventoryModal" class="fixed inset-0 bg-black/60 dark:bg-black/90 backdrop-blur-sm z-[2000] hidden items-center justify-center p-4">
        <div class="bg-white dark:bg-card w-full max-w-4xl max-h-[85vh] rounded-3xl border border-gray-200 dark:border-primary/30 flex flex-col shadow-2xl overflow-hidden transition-colors">
            <div class="p-6 border-b border-gray-200 dark:border-red-900/40 flex justify-between items-center bg-gray-50 dark:bg-darker">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white tracking-wider flex items-center gap-2"><i class="fa-solid fa-box-open text-yellow-500"></i> คลังฉายาทั้งหมด</h2>
                <button onclick="closeInventoryModal()" class="text-gray-400 hover:text-primary transition text-xl"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 hide-scrollbar">
                <?php
                if ($totalTitles > 0):
                    $titlesQuery->data_seek(0);
                    while ($title = $titlesQuery->fetch_assoc()):
                        // Set rarity badge style
                        $badgeClass = 'bg-gray-500 text-white';
                        if ($title['rarity'] == 'Rare') $badgeClass = 'bg-blue-500 text-white';
                        elseif ($title['rarity'] == 'Epic') $badgeClass = 'bg-purple-500 text-white';
                        elseif ($title['rarity'] == 'Legendary') $badgeClass = 'bg-yellow-500 text-black';
                ?>
                        <div class="bg-gray-50 dark:bg-darker border border-gray-200 dark:border-red-900/30 rounded-2xl p-5 text-center transition hover:border-yellow-500/50 group relative">
                            <span class="<?= $badgeClass ?> text-[10px] uppercase font-bold px-2 py-1 rounded-md mb-3 inline-block shadow-sm tracking-widest"><?= $title['rarity'] ?></span>
                            <h3 class="text-xl font-bold mb-2 shadow-sm group-hover:scale-105 transition" style="color: <?= $title['title_color'] ?>; text-shadow: 0 0 10px <?= $title['title_color'] ?>80;"><?= htmlspecialchars($title['title_name']) ?></h3>
                            <p class="text-yellow-600 dark:text-yellow-400 font-bold text-sm mb-4 bg-yellow-50 dark:bg-yellow-500/10 inline-block px-3 py-1 rounded-lg border border-yellow-200 dark:border-yellow-500/20"><i class="fa-solid fa-coins"></i> <?= number_format($title['price']) ?> Tokens</p>
                            <div class="flex justify-center gap-2 mt-2">
                                <button onclick='openEditTitle(<?= htmlspecialchars(json_encode($title), ENT_QUOTES, "UTF-8") ?>)' class="flex-1 bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-500 hover:text-white dark:bg-blue-500/10 dark:border-blue-500/20 dark:text-blue-400 dark:hover:bg-blue-500 dark:hover:text-white py-2 rounded-xl transition text-xs font-bold shadow-sm"><i class="fa-solid fa-pen"></i> แก้ไข</button>
                                <button onclick="confirmDeleteTitle(<?= $title['title_id'] ?>, '<?= htmlspecialchars(addslashes($title['title_name'])) ?>')" class="flex-1 bg-red-50 text-primary border border-red-200 hover:bg-primary hover:text-white dark:bg-red-500/10 dark:border-red-500/20 dark:text-primary dark:hover:bg-primary dark:hover:text-white py-2 rounded-xl transition text-xs font-bold shadow-sm"><i class="fa-solid fa-trash"></i> ลบ</button>
                            </div>
                        </div>
                    <?php endwhile;
                else: ?>
                    <div class="col-span-full text-center py-10 text-gray-500 dark:text-zinc-500 italic">ยังไม่มีฉายาในคลัง</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal แก้ไขฉายา -->
    <div id="editTitleModal" class="fixed inset-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm z-[2000] hidden items-center justify-center p-4">
        <div class="bg-white dark:bg-card w-full max-w-md rounded-3xl border border-gray-200 dark:border-red-900/50 shadow-2xl overflow-hidden transform transition-all relative">
            <div class="p-6 border-b border-gray-100 dark:border-zinc-800 flex justify-between items-center bg-gray-50 dark:bg-darker">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2"><i class="fa-solid fa-pen-to-square text-blue-500"></i> แก้ไขฉายา</h2>
                <button onclick="closeEditTitleModal()" class="text-gray-400 hover:text-primary transition"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div class="p-6">
                <form action="" method="POST" class="space-y-4">
                    <input type="hidden" name="title_id" id="edit_title_id">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-zinc-300 mb-1">ชื่อฉายา</label>
                        <input type="text" name="title_name" id="edit_title_name" class="w-full bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-zinc-300 mb-1">ระดับความหายาก</label>
                        <select name="rarity" id="edit_rarity" class="w-full bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:border-blue-500 outline-none transition">
                            <option value="Common">Common</option>
                            <option value="Rare">Rare</option>
                            <option value="Epic">Epic</option>
                            <option value="Legendary">Legendary</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-zinc-300 mb-1">ราคา (Token)</label>
                            <input type="number" name="price" id="edit_price" min="0" class="w-full bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:border-blue-500 outline-none transition" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-zinc-300 mb-1">สีฉายา</label>
                            <input type="color" name="title_color" id="edit_title_color" class="w-full h-[50px] rounded-xl cursor-pointer border border-gray-200 dark:border-zinc-700 p-1 bg-gray-50 dark:bg-zinc-900">
                        </div>
                    </div>
                    <button type="submit" name="update_title" class="w-full mt-4 bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 rounded-xl shadow-md hover:shadow-lg transition transform active:scale-95 flex justify-center items-center gap-2">
                        <i class="fa-solid fa-save"></i> อัปเดตข้อมูล
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        function toggleTheme() {
            const html = document.documentElement;
            html.classList.toggle('dark');
            localStorage.setItem('gymfitt-theme', html.classList.contains('dark') ? 'dark' : 'light');
        }

        // Modal Functions
        function openTitleModal() {
            document.getElementById('titleModal').classList.remove('hidden');
            document.getElementById('titleModal').classList.add('flex');
        }

        function closeTitleModal() {
            document.getElementById('titleModal').classList.add('hidden');
            document.getElementById('titleModal').classList.remove('flex');
        }

        function openInventoryModal() {
            document.getElementById('inventoryModal').classList.remove('hidden');
            document.getElementById('inventoryModal').classList.add('flex');
        }

        function closeInventoryModal() {
            document.getElementById('inventoryModal').classList.add('hidden');
            document.getElementById('inventoryModal').classList.remove('flex');
        }

        function openEditTitle(data) {
            document.getElementById('edit_title_id').value = data.title_id;
            document.getElementById('edit_title_name').value = data.title_name;
            document.getElementById('edit_rarity').value = data.rarity;
            document.getElementById('edit_price').value = data.price;
            document.getElementById('edit_title_color').value = data.title_color;

            document.getElementById('editTitleModal').classList.remove('hidden');
            document.getElementById('editTitleModal').classList.add('flex');
        }

        function closeEditTitleModal() {
            document.getElementById('editTitleModal').classList.add('hidden');
            document.getElementById('editTitleModal').classList.remove('flex');
        }

        // Close modal on backdrop click
        window.onclick = function(e) {
            if (e.target.id === 'titleModal') closeTitleModal();
            if (e.target.id === 'inventoryModal') closeInventoryModal();
            if (e.target.id === 'editTitleModal') closeEditTitleModal();
        }

        // SweetAlert Configuration
        function getSwalConfig() {
            const isDark = document.documentElement.classList.contains('dark');
            return {
                background: isDark ? '#18181b' : '#fff',
                color: isDark ? '#fafafa' : '#18181b',
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
                text: `ต้องการลบท่า "${name}" ใช่หรือไม่? (ภารกิจที่เกี่ยวข้องจะได้รับผลกระทบ)`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ใช่, ลบเลย',
                cancelButtonText: 'ยกเลิก'
            }).then((r) => {
                if (r.isConfirmed) window.location.href = `?delete_exercise=${id}`;
            });
        }

        // Notification alerts
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