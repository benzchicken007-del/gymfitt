<?php
session_start();
date_default_timezone_set("Asia/Bangkok");
include "config/config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// ดึงข้อมูลผู้ใช้ (เพื่อให้ได้ข้อมูล Level, EXP, Tokens, Title เหมือนหน้า Home)
$stmtUser = $conn->prepare("SELECT u.*, t.title_id as active_id, t.title_name, t.title_color FROM users u LEFT JOIN shop_titles t ON u.title_active_id = t.title_id WHERE u.user_id=?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();

$full_name = !empty($user['full_name']) ? $user['full_name'] : $user['username'];
$current_exp = !empty($user['exp']) ? (int)$user['exp'] : 0;
$current_level = !empty($user['level']) ? (int)$user['level'] : 0;
$tokens = !empty($user['tokens']) ? (int)$user['tokens'] : 0;
$active_title = !empty($user['title_name']) ? $user['title_name'] : "";
$active_title_color = !empty($user['title_color']) ? $user['title_color'] : "#ffffff";

// ระบบรูปโปรไฟล์
$default_avatar = "https://api.dicebear.com/7.x/initials/svg?seed=" . urlencode($full_name) . "&backgroundColor=dc2626&textColor=ffffff";
$profile_pic = $default_avatar;
if (!empty($user['profile_image'])) {
    $profile_pic = $user['profile_image'];
}

// ล้างประวัติทั้งหมดจากหน้านี้
if (isset($_POST['clear_history'])) {
    $delStmt = $conn->prepare("DELETE FROM workout_history WHERE user_id = ?");
    $delStmt->bind_param("i", $user_id);
    if ($delStmt->execute()) {
        header("Location: history.php?status=history_cleared");
        exit();
    }
}

// คิวรีดึงประวัติทั้งหมด (ไม่มีการ Limit 10)
$history_query = "
    SELECT 
        wh.*,
        IF(wh.mission_id > 0, m.mission_name, e.exercise_name) as display_name,
        IF(wh.mission_id > 0, m.mission_image, e.exercise_image) as display_image
    FROM workout_history wh 
    LEFT JOIN missions m ON wh.mission_id = m.mission_id 
    LEFT JOIN exercises e ON wh.exercise_id = e.exercise_id 
    WHERE wh.user_id = ? 
    ORDER BY wh.workout_date DESC";
$stmtHist = $conn->prepare($history_query);
$stmtHist->bind_param("i", $user_id);
$stmtHist->execute();
$history_list = $stmtHist->get_result();

$page_title = "ประวัติทั้งหมด - Gymfitt";
include 'includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php include 'includes/navbar.php'; ?>

<main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 relative transition-colors duration-300 min-h-screen">
    <div class="fixed top-20 right-0 w-96 h-96 bg-red-600/10 blur-[100px] rounded-full pointer-events-none z-[-1] hidden dark:block"></div>

    <!-- ส่วนหัวแสดงข้อมูลผู้ใช้แบบเดียวกับหน้า Home -->
    <div class="bg-white dark:bg-card rounded-3xl p-6 shadow-xl border border-gray-200 dark:border-red-900/30 mb-8 relative overflow-hidden transition-colors duration-300">
        <div class="absolute -top-20 -right-20 w-48 h-48 bg-primary/10 dark:bg-primary/20 blur-[50px] rounded-full"></div>
        <div class="relative z-10 flex flex-col sm:flex-row items-center gap-6">
            <div class="relative">
                <img src="<?php echo $profile_pic; ?>" class="w-24 h-24 rounded-full border-4 border-white dark:border-darker object-cover shadow-lg bg-gray-200">
                <div class="absolute -bottom-2 -right-2 bg-primary text-white text-xs font-bold px-2 py-1 rounded-lg border border-red-500">
                    LV. <?php echo $current_level; ?>
                </div>
            </div>
            <div class="text-center sm:text-left flex-1">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($full_name); ?></h2>
                <?php if ($active_title != ""): ?>
                    <p class="text-sm font-bold mt-1" style="color: <?php echo $active_title_color; ?>;">
                        <?php echo htmlspecialchars($active_title); ?>
                    </p>
                <?php endif; ?>
                <div class="flex flex-wrap justify-center sm:justify-start gap-4 mt-3">
                    <span class="text-sm text-gray-500 dark:text-zinc-400"><i class="fa-solid fa-star text-yellow-500 mr-1"></i> EXP: <?php echo $current_exp; ?></span>
                    <span class="text-sm text-yellow-600 dark:text-yellow-400 font-bold"><i class="fa-solid fa-coins mr-1"></i> Tokens: <?php echo number_format($tokens); ?></span>
                </div>
            </div>
            <a href="home.php" class="bg-gray-100 dark:bg-zinc-900 hover:bg-gray-200 dark:hover:bg-zinc-800 text-gray-800 dark:text-white px-6 py-3 rounded-2xl font-bold transition flex items-center gap-2 border border-gray-200 dark:border-red-900/30">
                <i class="fa-solid fa-house"></i> กลับหน้าหลัก
            </a>
        </div>
    </div>

    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-3">
        <i class="fa-solid fa-clock-rotate-left text-primary"></i> รายการประวัติทั้งหมด
    </h2>

    <!-- ส่วนรายการประวัติ -->
    <div class="bg-white dark:bg-card rounded-3xl p-6 shadow-xl border border-gray-200 dark:border-red-900/30 transition-colors duration-300">

        <div class="bg-white dark:bg-card rounded-3xl p-6 shadow-xl border border-gray-200 dark:border-red-900/30 transition-colors duration-300">
            <div class="flex justify-between items-center mb-6 border-b border-gray-100 dark:border-zinc-800 pb-4">
                <p class="text-gray-500 dark:text-zinc-400 font-medium">ประวัติทั้งหมดของคุณ (<span class="text-primary font-bold"><?php echo $history_list->num_rows; ?></span> รายการ)</p>

                <?php if ($history_list->num_rows > 0): ?>
                    <form method="POST" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการล้างประวัติการออกกำลังกายทั้งหมด?')">
                        <button type="submit" name="clear_history" class="text-sm bg-red-50 dark:bg-red-900/20 text-primary px-4 py-2 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/40 transition border border-red-200 dark:border-red-800/30 font-bold flex items-center gap-2">
                            <i class="fa-solid fa-trash-can"></i> ล้างประวัติทั้งหมด
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="space-y-4">
                <?php if ($history_list->num_rows > 0): ?>
                    <?php while ($h = $history_list->fetch_assoc()): ?>
                        <div class="flex items-center gap-4 bg-gray-50 dark:bg-darker p-4 rounded-2xl border border-gray-100 dark:border-red-900/20 hover:border-primary/50 hover:shadow-md transition duration-300 cursor-pointer group">
                            <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl overflow-hidden bg-gray-200 dark:bg-zinc-900 shrink-0 border border-gray-200 dark:border-zinc-800">
                                <?php
                                $img_name = basename($h['display_image']);
                                $is_mission = ($h['mission_id'] > 0);
                                $imgPath = "assets/images/no-image.png";

                                if (!empty($img_name)) {
                                    if ($is_mission && file_exists("uploads/missions/" . $img_name)) {
                                        $imgPath = "uploads/missions/" . $img_name;
                                    } elseif (!$is_mission && file_exists("uploads/exercises/" . $img_name)) {
                                        $imgPath = "uploads/exercises/" . $img_name;
                                    } elseif (file_exists("uploads/" . $img_name)) {
                                        $imgPath = "uploads/" . $img_name;
                                    }
                                }
                                ?>
                                <img src="<?php echo $imgPath; ?>" class="w-full h-full object-cover opacity-90 group-hover:opacity-100 group-hover:scale-110 transition duration-500">
                            </div>
                            <div class="flex-1 min-w-0">
                                <h5 class="text-gray-900 dark:text-white text-base sm:text-lg font-bold truncate transition-colors group-hover:text-primary"><?php echo htmlspecialchars($h['display_name']); ?></h5>
                                <div class="flex items-center text-xs sm:text-sm text-gray-500 dark:text-zinc-400 mt-1 gap-3">
                                    <span class="flex items-center gap-1"><i class="fa-solid fa-calendar-day"></i> <?php echo date('d M Y', strtotime($h['workout_date'])); ?></span>
                                    <span class="flex items-center gap-1"><i class="fa-solid fa-clock"></i> <?php echo date('H:i', strtotime($h['workout_date'])); ?> น.</span>
                                </div>
                            </div>
                            <div class="text-right flex flex-col items-end justify-center">
                                <span class="text-xs text-gray-400 dark:text-zinc-500 mb-1">ความแม่นยำ</span>
                                <span class="text-lg sm:text-xl font-black <?php echo ($h['accuracy'] >= 70) ? 'text-green-500' : 'text-primary'; ?> bg-white dark:bg-zinc-900 px-3 py-1 rounded-lg border <?php echo ($h['accuracy'] >= 70) ? 'border-green-200 dark:border-green-900/30' : 'border-red-200 dark:border-red-900/30'; ?> shadow-sm">
                                    <?php echo $h['accuracy']; ?>%
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-16 text-gray-500 dark:text-zinc-500">
                        <i class="fa-solid fa-folder-open text-5xl mb-4 opacity-30"></i>
                        <p class="text-lg">คุณยังไม่มีประวัติการออกกำลังกายเลย</p>
                        <p class="text-sm mt-2 opacity-70">เริ่มออกกำลังกายเพื่อสะสมประวัติและรับรางวัล!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
</main>

<script>
    // สคริปต์เพื่อให้สีของ SweetAlert ปรับตาม Theme ด้วย
    function getSwalBg() {
        return document.documentElement.classList.contains('dark') ? '#140505' : '#ffffff';
    }

    function getSwalColor() {
        return document.documentElement.classList.contains('dark') ? '#ffffff' : '#1f2937';
    }

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('status') === 'history_cleared') {
        Swal.fire({
            title: 'ล้างประวัติแล้ว!',
            text: 'ข้อมูลประวัติทั้งหมดของคุณถูกลบเรียบร้อย',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false,
            background: getSwalBg(),
            color: getSwalColor()
        }).then(() => {
            // เคลียร์ URL param ไม่ให้เด้งซ้ำ
            window.history.replaceState(null, null, window.location.pathname);
        });
    }
</script>

</body>

</html>