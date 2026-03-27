<?php
session_start();
include "config/config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// ดึงข้อมูลผู้ใช้เพื่อแสดง Token ใน Navbar
$stmtUser = $conn->prepare("SELECT tokens, profile_image, full_name, username FROM users WHERE user_id = ?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();

$tokens = $user['tokens'];
$full_name = !empty($user['full_name']) ? $user['full_name'] : $user['username'];

// จัดการรูปโปรไฟล์สำหรับ Navbar
$profile_pic = "https://api.dicebear.com/7.x/initials/svg?seed=" . urlencode($full_name) . "&backgroundColor=dc2626&textColor=ffffff";
if (!empty($user['profile_image'])) {
    $filename = basename($user['profile_image']);
    if (file_exists("uploads/avatars/" . $filename)) {
        $profile_pic = "uploads/avatars/" . $filename;
    }
}

// ดึงรายการภารกิจทั้งหมด
$query = "SELECT * FROM missions ORDER BY mission_id DESC";
$result = $conn->query($query);

$page_title = "รายการภารกิจ - Gymfitt";
include 'includes/header.php';
?>
<style>
    /* ปรับแต่ง Scrollbar ซ่อนไว้แต่ยังเลื่อนได้ถ้าจำเป็น */
    .hide-scrollbar::-webkit-scrollbar {
        display: none;
    }

    .hide-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>

<?php include 'includes/navbar.php'; ?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 relative">

    <!-- Background Effects สำหรับ Dark Mode -->
    <div class="fixed top-20 left-0 w-96 h-96 bg-red-600/10 blur-[100px] rounded-full pointer-events-none z-[-1] hidden dark:block"></div>
    <div class="fixed bottom-0 right-0 w-96 h-96 bg-red-900/10 blur-[100px] rounded-full pointer-events-none z-[-1] hidden dark:block"></div>

    <div class="mb-12 text-center relative z-10">
        <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 dark:text-white mb-4 tracking-tight drop-shadow-sm">
            <i class="fa-solid fa-trophy text-yellow-500 mr-3 drop-shadow-md"></i>รายการภารกิจทั้งหมด
        </h1>
        <p class="text-gray-500 dark:text-zinc-400 max-w-2xl mx-auto text-lg">
            เลือกภารกิจที่คุณต้องการท้าทายเพื่อรับค่าประสบการณ์ (EXP) และเหรียญรางวัล (Tokens) ยิ่งท้าทายมากยิ่งได้รางวัลมาก!
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 relative z-10">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($m = $result->fetch_assoc()):
                $m_img_name = basename($m['mission_image']);
                $m_img = "assets/images/no-image.png"; // รูป Default หากไม่มีรูป
                if (!empty($m_img_name)) {
                    if (file_exists("uploads/missions/" . $m_img_name)) {
                        $m_img = "uploads/missions/" . $m_img_name;
                    } elseif (file_exists("uploads/" . $m_img_name)) {
                        $m_img = "uploads/" . $m_img_name;
                    }
                }
            ?>
                <div class="bg-white dark:bg-card rounded-3xl border border-gray-200 dark:border-red-900/30 overflow-hidden shadow-lg hover:shadow-2xl hover:border-primary/50 dark:hover:border-primary/50 transition-all duration-300 group flex flex-col transform hover:-translate-y-2">
                    <div class="relative h-56 overflow-hidden bg-gray-100 dark:bg-darker">
                        <img src="<?php echo $m_img; ?>" class="w-full h-full object-cover group-hover:scale-110 group-hover:opacity-90 transition duration-500">

                        <!-- ป้ายรางวัล -->
                        <div class="absolute top-4 left-4 flex flex-wrap gap-2 pr-4">
                            <span class="bg-primary text-white text-xs font-bold px-3 py-1.5 rounded-lg shadow-lg border border-red-500/50 backdrop-blur-sm bg-opacity-90">
                                +<?php echo $m['exp_reward']; ?> XP
                            </span>
                            <?php if ($m['token_reward'] > 0): ?>
                                <span class="bg-yellow-400 text-black text-xs font-bold px-3 py-1.5 rounded-lg shadow-lg border border-yellow-500/50 backdrop-blur-sm bg-opacity-90">
                                    <i class="fa-solid fa-coins mr-1"></i>+<?php echo $m['token_reward']; ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- ป้ายแสดงจำนวนเซ็ตสูงสุดมุมขวาบน -->
                        <div class="absolute top-4 right-4 bg-black/60 backdrop-blur-md text-white text-xs font-bold px-3 py-1.5 rounded-lg border border-white/10 shadow-lg">
                            <i class="fa-solid fa-arrows-rotate mr-1 text-primary"></i> <?php echo isset($m['max_sets']) ? $m['max_sets'] : '1'; ?> Sets
                        </div>

                        <!-- Gradient Overlay ด้านล่างรูปภาพ -->
                        <div class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-white dark:from-card to-transparent pointer-events-none"></div>
                    </div>

                    <div class="p-6 relative flex flex-col flex-1 bg-white dark:bg-card -mt-4 z-10 rounded-t-3xl">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 group-hover:text-primary transition-colors tracking-wide">
                            <?php echo htmlspecialchars($m['mission_name']); ?>
                        </h3>
                        <p class="text-gray-500 dark:text-zinc-400 text-sm mb-6 line-clamp-3 flex-1 leading-relaxed">
                            <?php echo htmlspecialchars($m['mission_detail'] ?? 'ไม่มีรายละเอียดเพิ่มเติมสำหรับภารกิจนี้ แต่คุณสามารถกดเริ่มเพื่อทดสอบขีดจำกัดของคุณได้ทันที!'); ?>
                        </p>

                        <div class="border-t border-gray-100 dark:border-zinc-800/50 pt-5 mt-auto">
                            <a href="play_mission.php?mission_id=<?php echo $m['mission_id']; ?>" class="block w-full text-center bg-primary hover:bg-red-600 text-white font-bold py-3.5 px-5 rounded-2xl transition duration-300 shadow-[0_4px_15px_rgba(220,38,38,0.3)] hover:shadow-[0_8px_25px_rgba(220,38,38,0.5)] transform active:scale-95 group/btn">
                                เริ่มทำภารกิจนี้ <i class="fa-solid fa-arrow-right ml-2 group-hover/btn:translate-x-1 transition-transform"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-span-full text-center py-24 bg-white dark:bg-card rounded-3xl border border-gray-200 dark:border-zinc-800 shadow-sm">
                <div class="w-24 h-24 bg-gray-100 dark:bg-zinc-900 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fa-solid fa-clipboard-list text-4xl text-gray-400 dark:text-zinc-600"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">ยังไม่มีภารกิจในขณะนี้</h3>
                <p class="text-gray-500 dark:text-zinc-400 max-w-md mx-auto">
                    ระบบยังไม่มีการเพิ่มภารกิจ กรุณารอผู้ดูแลระบบอัปเดตภารกิจใหม่ๆ ในเร็วๆ นี้
                </p>
            </div>
        <?php endif; ?>
    </div>
</main>

</body>

</html>