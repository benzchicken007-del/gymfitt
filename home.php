<?php
session_start();
date_default_timezone_set("Asia/Bangkok");
include "config/config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// ล้างประวัติ
if (isset($_POST['clear_history'])) {
    $delStmt = $conn->prepare("DELETE FROM workout_history WHERE user_id = ?");
    $delStmt->bind_param("i", $user_id);
    if ($delStmt->execute()) {
        header("Location: home.php?status=history_cleared");
        exit();
    }
}

// ดึงข้อมูลผู้ใช้
$stmtUser = $conn->prepare("SELECT u.*, t.title_id as active_id, t.title_name, t.title_color FROM users u LEFT JOIN shop_titles t ON u.title_active_id = t.title_id WHERE u.user_id=?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();

$full_name = !empty($user['full_name']) ? $user['full_name'] : $user['username'];

// เช็คค่าแบบปลอดภัย
$gender      = !empty($user['gender']) ? $user['gender'] : 'ชาย';
$weight      = !empty($user['weight']) ? (float)$user['weight'] : 0;
$height      = !empty($user['height']) ? (float)$user['height'] : 0;
$current_exp = !empty($user['exp']) ? (int)$user['exp'] : 0;
$current_level = !empty($user['level']) ? (int)$user['level'] : 0;
$tokens      = !empty($user['tokens']) ? (int)$user['tokens'] : 0;
$active_title = !empty($user['title_name']) ? $user['title_name'] : "";
$active_title_color = !empty($user['title_color']) ? $user['title_color'] : "#ffffff";
$current_title_id = !empty($user['active_id']) ? $user['active_id'] : 0;

// --- ระบบรูปโปรไฟล์แบบป้องกันรูปหาย 100% ---
// สร้างรูประดับเริ่มต้นจากชื่อ User เป็นอักษรย่อสีแดง
$default_avatar = "https://api.dicebear.com/7.x/initials/svg?seed=" . urlencode($full_name) . "&backgroundColor=dc2626&textColor=ffffff";
$profile_pic = $default_avatar;

if (!empty($user['profile_image'])) {
    $filename = basename($user['profile_image']);
    if (file_exists("uploads/avatars/" . $filename)) {
        $profile_pic = "uploads/avatars/" . $filename;
    } elseif (file_exists("uploads/" . $filename)) {
        $profile_pic = "uploads/" . $filename;
    }
}
// ------------------------------------------

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

$lastDate = !empty($user['last_workout_date']) ? $user['last_workout_date'] : null;
$streak = !empty($user['current_streak']) ? (int)$user['current_streak'] : 0;
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

$rank_name = !empty($user['account_rank']) ? $user['account_rank'] : 'Member';
$rank_icon = "👤";
if ($rank_name == 'Bronze') $rank_icon = "🥉";
if ($rank_name == 'Silver') $rank_icon = "🥈";
if ($rank_name == 'Gold') $rank_icon = "🥇";

// คำนวณ BMI
$bmi = 0;
$bmi_text = "รอข้อมูล";
$bmi_color = "text-zinc-400";
if ($weight > 0 && $height > 0) {
    $height_m = $height / 100;
    $bmi = number_format($weight / ($height_m * $height_m), 1);
    if ($bmi < 18.5) {
        $bmi_text = "น้ำหนักน้อย";
        $bmi_color = "text-blue-400";
    } else if ($bmi < 23) {
        $bmi_text = "สมส่วน";
        $bmi_color = "text-green-500";
    } else if ($bmi < 25) {
        $bmi_text = "น้ำหนักเกิน";
        $bmi_color = "text-orange-500";
    } else {
        $bmi_text = "เริ่มอ้วน";
        $bmi_color = "text-red-500";
    }
}

/* ===== LOAD MISSIONS, SHOP & OWNED TITLES ===== */
$today_str = date('Y-m-d');
$missions_query = "SELECT m.*, (SELECT COUNT(*) FROM workout_history wh WHERE wh.mission_id = m.mission_id AND wh.user_id = ? AND DATE(wh.workout_date) = ?) as today_play_count FROM missions m ORDER BY m.mission_id DESC";
$stmtMissions = $conn->prepare($missions_query);
$stmtMissions->bind_param("is", $user_id, $today_str);
$stmtMissions->execute();
$missions = $stmtMissions->get_result();

$shop_query = "SELECT * FROM shop_titles WHERE title_id NOT IN (SELECT title_id FROM user_titles WHERE user_id = ?) ORDER BY price ASC";
$stmtShop = $conn->prepare($shop_query);
$stmtShop->bind_param("i", $user_id);
$stmtShop->execute();
$shop_items = $stmtShop->get_result();

$owned_query = "SELECT st.* FROM user_titles ut JOIN shop_titles st ON ut.title_id = st.title_id WHERE ut.user_id = ? ORDER BY st.title_name ASC";
$stmtOwned = $conn->prepare($owned_query);
$stmtOwned->bind_param("i", $user_id);
$stmtOwned->execute();
$owned_titles = $stmtOwned->get_result();

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

// ===== เริ่มส่วน UI =====
$page_title = "หน้าแรก - Gymfitt";
include 'includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    .glass-card {
        background: rgba(20, 5, 5, 0.7);
        backdrop-filter: blur(10px);
    }

    .swal2-container {
        z-index: 99999 !important;
    }

    .swal2-popup {
        background: #140505 !important;
        border: 1px solid #7f1d1d !important;
        color: #fff !important;
    }

    .swal2-confirm {
        background: #dc2626 !important;
    }
</style>

<?php include 'includes/navbar.php'; ?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 relative">

    <div class="fixed top-20 right-0 w-96 h-96 bg-red-600/10 blur-[100px] rounded-full pointer-events-none z-[-1]"></div>
    <div class="fixed bottom-0 left-0 w-96 h-96 bg-red-900/10 blur-[100px] rounded-full pointer-events-none z-[-1]"></div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <div class="lg:col-span-1 space-y-6">
            <div class="bg-card rounded-3xl p-6 shadow-2xl border border-red-900/30 relative overflow-hidden text-center">
                <div class="absolute -top-20 -right-20 w-48 h-48 bg-primary/20 blur-[50px] rounded-full"></div>

                <div class="relative z-10 flex flex-col items-center">
                    <div class="relative cursor-pointer group" onclick="window.location='profile.php'">
                        <img src="<?php echo $profile_pic; ?>" class="w-28 h-28 rounded-full border-4 border-darker object-cover shadow-[0_0_15px_rgba(220,38,38,0.5)] group-hover:scale-105 transition duration-300">
                        <div class="absolute -bottom-2 -right-2 bg-gradient-to-r from-primary to-red-900 text-white text-xs font-bold px-3 py-1 rounded-lg shadow-md border border-red-500 z-20">
                            LV. <?php echo $current_level; ?>
                        </div>
                    </div>

                    <h2 class="mt-4 text-2xl font-bold text-white tracking-wide"><?php echo htmlspecialchars($full_name); ?></h2>

                    <?php if ($active_title != ""): ?>
                        <span class="mt-2 text-sm font-bold px-4 py-1 rounded-full bg-red-950/50 border border-red-500/50 shadow-[0_0_10px_currentColor] transition" style="color: <?php echo $active_title_color; ?>;">
                            <?php echo htmlspecialchars($active_title); ?>
                        </span>
                    <?php endif; ?>

                    <div class="w-full mt-6">
                        <div class="flex justify-between text-xs font-medium text-zinc-400 mb-1">
                            <span>EXP: <?php echo $current_exp; ?></span>
                            <span class="text-white"><?php echo $required_exp; ?> (Next)</span>
                        </div>
                        <div class="w-full bg-zinc-900 rounded-full h-3 border border-zinc-800 overflow-hidden">
                            <div class="bg-gradient-to-r from-red-800 via-primary to-white h-full rounded-full shadow-[0_0_10px_rgba(255,255,255,0.5)] transition-all duration-1000" style="width: <?php echo $level_percent; ?>%"></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 w-full mt-6">
                        <div class="bg-darker p-3 rounded-2xl border border-red-900/20 shadow-inner">
                            <div class="text-zinc-500 text-[10px] uppercase tracking-wider mb-1">Streak</div>
                            <div class="text-lg font-bold text-white flex items-center justify-center gap-2">
                                <i class="fa-solid fa-fire text-primary"></i> <?php echo $streak; ?> วัน
                            </div>
                        </div>
                        <div class="bg-darker p-3 rounded-2xl border border-red-900/20 shadow-inner">
                            <div class="text-zinc-500 text-[10px] uppercase tracking-wider mb-1">BMI: <?php echo $bmi; ?></div>
                            <div class="text-sm font-bold <?php echo $bmi_color; ?> flex items-center justify-center h-full">
                                <?php echo $bmi_text; ?>
                            </div>
                        </div>
                    </div>

                    <div class="w-full flex gap-2 mt-4">
                        <button onclick="openInventory()" class="flex-1 bg-zinc-900 hover:bg-zinc-800 border border-red-900/30 text-white py-2 rounded-xl text-sm transition"><i class="fa-solid fa-award text-yellow-500 mr-1"></i> คลังฉายา</button>
                        <button onclick="openShop()" class="flex-1 bg-zinc-900 hover:bg-zinc-800 border border-red-900/30 text-white py-2 rounded-xl text-sm transition"><i class="fa-solid fa-store text-primary mr-1"></i> ร้านค้า</button>
                    </div>
                </div>
            </div>

            <div class="bg-card rounded-3xl p-6 shadow-xl border border-red-900/30">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-white"><i class="fa-solid fa-clock-rotate-left text-primary mr-2"></i> ประวัติล่าสุด</h3>
                    <form method="POST" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการล้างประวัติการออกกำลังกายทั้งหมด?')">
                        <button type="submit" name="clear_history" class="text-xs text-zinc-500 hover:text-primary transition">ล้างข้อมูล</button>
                    </form>
                </div>

                <div class="space-y-3 max-h-64 overflow-y-auto hide-scrollbar pr-1">
                    <?php if ($history_list->num_rows > 0): ?>
                        <?php while ($h = $history_list->fetch_assoc()): ?>
                            <div class="flex items-center gap-3 bg-darker p-3 rounded-2xl border border-red-900/20 hover:border-primary/50 transition cursor-pointer group">
                                <div class="w-12 h-12 rounded-xl overflow-hidden bg-zinc-900 shrink-0">
                                    <?php
                                    $img = $h['display_image'];
                                    $imgPath = "uploads/exercises/" . $img;
                                    if (empty($img) || !file_exists($imgPath)) $imgPath = "assets/images/no-image.png";
                                    ?>
                                    <img src="<?php echo $imgPath; ?>" class="w-full h-full object-cover opacity-80 group-hover:opacity-100 transition">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h5 class="text-white text-sm font-semibold truncate"><?php echo htmlspecialchars($h['display_name']); ?></h5>
                                    <p class="text-zinc-500 text-[10px]"><?php echo date('d M, H:i', strtotime($h['workout_date'])); ?></p>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs font-bold <?php echo ($h['accuracy'] >= 70) ? 'text-green-500' : 'text-primary'; ?>"><?php echo $h['accuracy']; ?>%</span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-zinc-500 text-sm">ยังไม่มีประวัติการออกกำลังกาย</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-8">

            <div>
                <h3 class="text-2xl font-bold text-white mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-fire text-primary"></i> ภารกิจประจำวัน
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php while ($m = $missions->fetch_assoc()):
                        $limit = isset($m['daily_limit']) ? (int)$m['daily_limit'] : 1;
                        $done = (int)$m['today_play_count'];
                        $is_locked = ($done >= $limit);
                        $bg_class = $is_locked ? 'border-zinc-800 opacity-60' : 'border-red-900/40 hover:border-primary cursor-pointer hover:-translate-y-1 shadow-lg';
                    ?>
                        <div class="bg-card rounded-2xl border <?php echo $bg_class; ?> transition duration-300 relative overflow-hidden group" onclick='<?php echo $is_locked ? "" : "openMissionModal(" . json_encode($m) . ")"; ?>'>

                            <div class="absolute top-3 left-3 flex gap-2 z-20">
                                <span class="bg-primary text-white text-[10px] font-bold px-2 py-1 rounded shadow-md">+<?php echo $m['exp_reward']; ?> XP</span>
                                <?php if ($m['token_reward'] > 0): ?>
                                    <span class="bg-yellow-500 text-black text-[10px] font-bold px-2 py-1 rounded shadow-md"><i class="fa-solid fa-coins"></i> +<?php echo $m['token_reward']; ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="absolute top-3 right-3 bg-black/60 backdrop-blur-md text-white text-[10px] font-bold px-2 py-1 rounded border border-white/10 z-20">
                                <?php echo $done; ?>/<?php echo $limit; ?>
                            </div>

                            <div class="h-40 w-full relative overflow-hidden bg-darker">
                                <?php if ($is_locked): ?>
                                    <div class="absolute inset-0 bg-black/50 z-10 flex items-center justify-center backdrop-blur-sm">
                                        <div class="bg-white text-black font-bold px-4 py-2 rounded-lg"><i class="fa-solid fa-check"></i> สำเร็จแล้ว</div>
                                    </div>
                                <?php endif; ?>
                                <?php
                                $m_img = "uploads/missions/" . $m['mission_image'];
                                if (empty($m['mission_image']) || !file_exists($m_img)) $m_img = "assets/images/no-image.png";
                                ?>
                                <img src="<?php echo $m_img; ?>" class="w-full h-full object-cover opacity-70 <?php echo $is_locked ? 'grayscale' : 'group-hover:opacity-100 group-hover:scale-110'; ?> transition duration-500">
                            </div>

                            <div class="p-4 relative z-20 bg-gradient-to-t from-card via-card to-transparent -mt-10 pt-10">
                                <h4 class="font-bold text-white text-lg truncate"><?php echo htmlspecialchars($m['mission_name']); ?></h4>
                                <p class="text-xs text-zinc-400 mt-1 line-clamp-1"><?php echo htmlspecialchars($m['mission_detail']); ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <div>
                <h3 class="text-2xl font-bold text-white mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-dumbbell text-white drop-shadow-[0_0_5px_rgba(255,255,255,0.8)]"></i> ฝึกซ้อมอิสระ
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php
                    $ex_query = $conn->query("SELECT * FROM exercises ORDER BY exercise_id DESC");
                    while ($ex = $ex_query->fetch_assoc()):
                        $ex_img = "uploads/exercises/" . $ex['exercise_image'];
                        if (empty($ex['exercise_image']) || !file_exists($ex_img)) $ex_img = "assets/images/no-image.png";
                    ?>
                        <div class="bg-darker rounded-2xl border border-zinc-800 hover:border-white/50 cursor-pointer transition duration-300 flex items-center p-3 gap-4 group" onclick="window.location.href='play_exercise.php?id=<?php echo $ex['exercise_id']; ?>'">
                            <div class="w-20 h-20 rounded-xl overflow-hidden bg-zinc-900 shrink-0 border border-zinc-700 group-hover:border-white transition">
                                <img src="<?php echo $ex_img; ?>" class="w-full h-full object-cover opacity-80 group-hover:opacity-100 group-hover:scale-110 transition duration-300">
                            </div>
                            <div class="flex-1">
                                <h4 class="font-bold text-white group-hover:text-primary transition"><?php echo htmlspecialchars($ex['exercise_name']); ?></h4>
                                <p class="text-[10px] text-zinc-500 line-clamp-2 mt-1"><?php echo htmlspecialchars($ex['exercise_detail']); ?></p>
                            </div>
                            <div class="text-zinc-600 group-hover:text-white transition pr-2">
                                <i class="fa-solid fa-chevron-right"></i>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

        </div>
    </div>
</main>

<div id="missionModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[2000] hidden items-center justify-center p-4">
    <div class="bg-card w-full max-w-md rounded-3xl border border-red-900/50 overflow-hidden transform transition-all shadow-2xl">
        <div id="modalMedia" class="h-48 w-full bg-darker relative"></div>
        <div class="p-6">
            <h2 id="modalTitle" class="text-2xl font-bold text-white mb-2"></h2>
            <div class="flex flex-wrap gap-2 mb-4">
                <span class="bg-zinc-900 border border-zinc-700 text-zinc-300 text-xs px-3 py-1 rounded-lg">🎯 เซ็ตจัดเต็ม</span>
                <span class="bg-yellow-500/10 border border-yellow-500/30 text-yellow-400 text-xs px-3 py-1 rounded-lg"><i class="fa-solid fa-coins"></i> <span id="modalTokenReward"></span></span>
                <span class="bg-primary/10 border border-primary/30 text-primary text-xs px-3 py-1 rounded-lg">รอบ: <span id="modalProgress"></span></span>
            </div>
            <p id="modalDesc" class="text-zinc-400 text-sm mb-6 line-clamp-3"></p>

            <div class="flex gap-3">
                <button type="button" onclick="closeMissionModal()" class="flex-1 bg-darker border border-zinc-700 text-white py-3 rounded-xl font-bold hover:bg-zinc-800 transition">ยกเลิก</button>
                <div id="modalButtonContainer" class="flex-1"></div>
            </div>
        </div>
    </div>
</div>

<div id="shopModal" class="fixed inset-0 bg-black/90 backdrop-blur-md z-[2000] hidden items-center justify-center p-4">
    <div class="bg-card w-full max-w-4xl max-h-[85vh] rounded-3xl border border-primary/30 flex flex-col shadow-2xl overflow-hidden">
        <div class="p-6 border-b border-red-900/40 flex justify-between items-center bg-darker">
            <h2 class="text-2xl font-bold text-yellow-500 tracking-wider"><i class="fa-solid fa-store mr-2"></i> TITLE SHOP</h2>
            <div class="flex items-center gap-4">
                <div class="text-yellow-400 font-bold bg-yellow-400/10 px-4 py-2 rounded-xl border border-yellow-400/20">
                    <i class="fa-solid fa-coins"></i> <?php echo number_format($tokens); ?>
                </div>
                <button onclick="closeShop()" class="text-zinc-400 hover:text-white transition text-xl"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>
        <div class="p-6 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 hide-scrollbar">
            <?php if ($shop_items->num_rows > 0): ?>
                <?php while ($item = $shop_items->fetch_assoc()): ?>
                    <div class="bg-darker border border-red-900/30 rounded-2xl p-5 text-center hover:border-yellow-500 transition group">
                        <span class="text-[10px] text-zinc-500 uppercase tracking-widest block mb-2"><?php echo $item['rarity']; ?></span>
                        <h4 class="text-xl font-bold mb-4 shadow-sm group-hover:scale-110 transition" style="color: <?php echo $item['title_color']; ?>; text-shadow: 0 0 10px <?php echo $item['title_color']; ?>80;"><?php echo htmlspecialchars($item['title_name']); ?></h4>
                        <div class="text-yellow-400 font-bold text-lg mb-4"><i class="fa-solid fa-coins"></i> <?php echo number_format($item['price']); ?></div>
                        <button onclick="confirmBuyTitle(<?php echo $item['title_id']; ?>, '<?php echo addslashes($item['title_name']); ?>', <?php echo $item['price']; ?>)" class="w-full bg-yellow-500 hover:bg-yellow-400 text-black font-bold py-2 rounded-xl transition">ซื้อฉายา</button>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full text-center py-10 text-zinc-500">คุณมีฉายาทุกอย่างในร้านค้าแล้ว!</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="inventoryModal" class="fixed inset-0 bg-black/90 backdrop-blur-md z-[2000] hidden items-center justify-center p-4">
    <div class="bg-card w-full max-w-4xl max-h-[85vh] rounded-3xl border border-primary/30 flex flex-col shadow-2xl overflow-hidden">
        <div class="p-6 border-b border-red-900/40 flex justify-between items-center bg-darker">
            <h2 class="text-2xl font-bold text-white tracking-wider"><i class="fa-solid fa-award text-primary mr-2"></i> คลังฉายาของฉัน</h2>
            <button onclick="closeInventory()" class="text-zinc-400 hover:text-white transition text-xl"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="p-6 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 hide-scrollbar">
            <?php if ($owned_titles->num_rows > 0): ?>
                <?php while ($ot = $owned_titles->fetch_assoc()):
                    $is_active = ($ot['title_id'] == $current_title_id);
                    $border_class = $is_active ? 'border-primary shadow-[0_0_15px_rgba(220,38,38,0.3)]' : 'border-zinc-800';
                ?>
                    <div class="bg-darker border <?php echo $border_class; ?> rounded-2xl p-5 text-center transition relative overflow-hidden">
                        <?php if ($is_active): ?>
                            <div class="absolute top-2 right-2 text-primary text-xs"><i class="fa-solid fa-circle-check"></i></div>
                        <?php endif; ?>
                        <span class="text-[10px] text-zinc-500 uppercase tracking-widest block mb-2"><?php echo $ot['rarity']; ?></span>
                        <h4 class="text-xl font-bold mb-6" style="color: <?php echo $ot['title_color']; ?>; text-shadow: 0 0 10px <?php echo $ot['title_color']; ?>80;"><?php echo htmlspecialchars($ot['title_name']); ?></h4>

                        <?php if ($is_active): ?>
                            <a href="actions/process_equip.php?action=unequip" class="block w-full bg-darker border border-zinc-700 hover:bg-zinc-800 text-zinc-300 font-bold py-2 rounded-xl transition text-sm text-center">ถอดฉายา</a>
                        <?php else: ?>
                            <a href="actions/process_equip.php?action=equip&title_id=<?php echo $ot['title_id']; ?>" class="block w-full bg-primary hover:bg-red-500 text-white font-bold py-2 rounded-xl transition text-sm text-center">ใช้งานฉายานี้</a>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full text-center py-10">
                    <p class="text-zinc-500 mb-4">คุณยังไม่มีฉายาในครอบครอง</p>
                    <button onclick="openShop()" class="bg-primary text-white px-6 py-2 rounded-xl font-bold">ไปที่ร้านค้า</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function openMissionModal(m) {
        document.getElementById('modalTitle').innerText = m.mission_name;
        document.getElementById('modalDesc').innerText = m.mission_detail || 'ไม่มีรายละเอียดเพิ่มเติม';
        document.getElementById('modalTokenReward').innerText = (m.token_reward || 0);

        const limit = m.daily_limit ? parseInt(m.daily_limit) : 1;
        const done = parseInt(m.today_play_count);
        document.getElementById('modalProgress').innerText = `${done}/${limit}`;

        const media = document.getElementById('modalMedia');
        if (m.mission_image) {
            let path = "uploads/missions/" + m.mission_image;
            let ext = m.mission_image.split('.').pop().toLowerCase();
            media.innerHTML = (['mp4', 'webm'].includes(ext)) ?
                `<video src="${path}" autoplay muted loop class="w-full h-full object-cover"></video>` :
                `<img src='${path}' class="w-full h-full object-cover">`;
        } else {
            media.innerHTML = '<div class="w-full h-full flex items-center justify-center bg-zinc-900 text-zinc-600"><i class="fa-solid fa-image fa-3x"></i></div>';
        }

        const isLocked = (done >= limit);
        document.getElementById('modalButtonContainer').innerHTML = isLocked ?
            `<button class="w-full bg-zinc-800 text-zinc-500 py-3 rounded-xl font-bold cursor-not-allowed" disabled>🔒 สิทธิ์เต็มแล้ว</button>` :
            `<button class="w-full bg-primary hover:bg-red-500 text-white py-3 rounded-xl font-bold transition shadow-[0_0_15px_rgba(220,38,38,0.4)]" onclick="location.href='play_mission.php?mission_id=${m.mission_id}'">เริ่มภารกิจ</button>`;

        document.getElementById('missionModal').classList.remove('hidden');
        document.getElementById('missionModal').classList.add('flex');
    }

    function closeMissionModal() {
        document.getElementById('missionModal').classList.add('hidden');
        document.getElementById('missionModal').classList.remove('flex');
    }

    function openShop() {
        closeInventory();
        document.getElementById('shopModal').classList.remove('hidden');
        document.getElementById('shopModal').classList.add('flex');
    }

    function closeShop() {
        document.getElementById('shopModal').classList.add('hidden');
        document.getElementById('shopModal').classList.remove('flex');
    }

    function openInventory() {
        closeShop();
        document.getElementById('inventoryModal').classList.remove('hidden');
        document.getElementById('inventoryModal').classList.add('flex');
    }

    function closeInventory() {
        document.getElementById('inventoryModal').classList.add('hidden');
        document.getElementById('inventoryModal').classList.remove('flex');
    }

    function confirmBuyTitle(id, name, price) {
        const userTokens = <?php echo $tokens; ?>;
        if (userTokens < price) {
            Swal.fire({
                title: 'Token ไม่พอ!',
                text: 'ออกกำลังกายสะสมเพิ่มก่อนนะ',
                icon: 'error'
            });
            return;
        }
        Swal.fire({
            title: 'ยืนยันการซื้อ?',
            html: `ซื้อฉายา <b class="text-yellow-500">${name}</b><br>ราคา ${price} Tokens ใช่หรือไม่?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'ยืนยัน',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `actions/process_buy_title.php?title_id=${id}`;
            }
        });
    }

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('buy_status') === 'success') {
        Swal.fire({
            title: 'สำเร็จ!',
            text: 'เพิ่มฉายาในคลังแล้ว',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        });
    }
    if (urlParams.get('equip') === 'success') {
        Swal.fire({
            title: 'สำเร็จ!',
            text: 'ติดตั้งฉายาเรียบร้อย',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
        });
    }
    if (urlParams.get('unequip') === 'success') {
        Swal.fire({
            title: 'สำเร็จ!',
            text: 'ถอดฉายาเรียบร้อย',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
        });
    }
    if (urlParams.get('status') === 'history_cleared') {
        Swal.fire({
            title: 'ล้างประวัติแล้ว!',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
        });
    }
</script>

</body>

</html>