<?php
session_start();
date_default_timezone_set("Asia/Bangkok");
include "config/config.php";

$conn->set_charset("utf8mb4");

// ดึงข้อมูลท่าออกกำลังกายทั้งหมด
$ex_query = $conn->query("SELECT * FROM exercises ORDER BY exercise_id DESC");

// เรียกใช้ Header เพื่อดึง Tailwind และ CSS พื้นฐาน
$page_title = "แดชบอร์ดทดลองใช้ - Gymfitt";
include 'includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    .swal2-popup {
        background: #140505 !important;
        border: 1px solid #7f1d1d !important;
        color: #fff !important;
    }

    .swal2-confirm {
        background: #dc2626 !important;
    }
</style>

<nav class="bg-white dark:bg-dark border-b border-gray-200 dark:border-red-900/40 sticky top-0 z-50 transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <a href="index.php" class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-tr from-primary to-red-400 dark:to-white rounded-xl flex items-center justify-center shadow-lg shadow-red-600/40">
                    <i class="fa-solid fa-dumbbell text-white dark:text-darker"></i>
                </div>
                <span class="font-bold text-xl tracking-wider text-gray-900 dark:text-white">GYM<span class="text-primary drop-shadow-[0_0_8px_rgba(220,38,38,0.8)]">FITT</span></span>
                <span class="text-xs text-gray-500 dark:text-zinc-400 border border-gray-300 dark:border-zinc-700 bg-gray-100 dark:bg-zinc-800 rounded px-2 py-0.5 ml-2 font-bold tracking-widest">GUEST</span>
            </a>
            <div class="flex items-center space-x-2 sm:space-x-4">
                <button id="themeToggleBtn" class="w-9 h-9 flex items-center justify-center rounded-full bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-zinc-400 hover:text-primary dark:hover:text-white transition shadow-inner">
                    <i class="fa-solid fa-sun hidden dark:block"></i>
                    <i class="fa-solid fa-moon block dark:hidden"></i>
                </button>
                <a href="login.php" class="text-gray-600 dark:text-zinc-300 hover:text-primary dark:hover:text-white font-bold py-2 px-3 sm:px-4 transition text-sm sm:text-base">เข้าสู่ระบบ</a>
                <a href="register.php" class="bg-primary hover:bg-red-600 text-white font-bold py-2 px-4 sm:px-5 rounded-xl shadow-md dark:shadow-[0_0_10px_rgba(220,38,38,0.4)] transition text-sm sm:text-base">สมัครสมาชิก</a>
            </div>
        </div>
    </div>
</nav>

<main class="bg-gray-50 dark:bg-transparent transition-colors duration-300 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 relative">
        <div class="fixed top-20 right-0 w-96 h-96 bg-red-600/10 blur-[100px] rounded-full pointer-events-none z-[-1] hidden dark:block"></div>

        <div class="bg-white dark:bg-card rounded-3xl p-8 shadow-xl dark:shadow-2xl border border-gray-200 dark:border-red-900/30 text-center relative overflow-hidden mb-12 transition-colors duration-300">
            <div class="absolute -top-20 -right-20 w-48 h-48 bg-primary/10 dark:bg-primary/20 blur-[50px] rounded-full z-0"></div>

            <div class="relative z-10">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-4">ยินดีต้อนรับสู่โหมดทดลอง <span class="text-primary">Gymfitt</span> 💪</h1>
                <p class="text-gray-600 dark:text-zinc-400 mb-6 max-w-2xl mx-auto leading-relaxed">
                    ในฐานะผู้ใช้ทั่วไป คุณสามารถทดสอบระบบ AI ตรวจจับท่าทางได้ฟรีแบบไม่จำกัด! <br>
                    แต่หากต้องการ <b>เก็บ EXP, อัปเลเวล, ทำภารกิจรายวัน และสะสมเหรียญแลกฉายา</b> กรุณาสมัครสมาชิกเพื่อใช้งานฟีเจอร์จัดเต็ม!
                </p>
                <button onclick="window.location='register.php'" class="bg-primary hover:bg-red-600 text-white font-bold py-3 px-8 rounded-xl shadow-lg dark:shadow-[0_0_15px_rgba(220,38,38,0.5)] transition transform hover:-translate-y-1">
                    <i class="fa-solid fa-user-plus mr-2"></i> สมัครสมาชิกฟรี ตอนนี้เลย!
                </button>
            </div>
        </div>

        <div>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-2">
                <i class="fa-solid fa-dumbbell text-primary dark:drop-shadow-[0_0_5px_rgba(220,38,38,0.8)]"></i> ท่าฝึกซ้อมที่เปิดให้ทดลอง
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php
                if ($ex_query->num_rows > 0):
                    while ($ex = $ex_query->fetch_assoc()):
                        $ex_img = "uploads/exercises/" . $ex['exercise_image'];
                        if (empty($ex['exercise_image']) || !file_exists($ex_img)) {
                            $ex_img = "uploads/" . $ex['exercise_image'];
                            if (!file_exists($ex_img)) $ex_img = "assets/images/no-image.png";
                        }
                ?>
                        <div class="bg-gray-50 dark:bg-darker rounded-2xl border border-gray-200 dark:border-zinc-800 hover:border-primary/50 dark:hover:border-primary/50 hover:shadow-lg dark:hover:shadow-[0_0_20px_rgba(220,38,38,0.15)] cursor-pointer transition duration-300 group overflow-hidden flex flex-col" onclick="window.location.href='play_exercise.php?id=<?= $ex['exercise_id'] ?>&guest=1'">
                            <div class="w-full h-48 bg-gray-200 dark:bg-zinc-900 relative overflow-hidden">
                                <div class="absolute top-3 right-3 bg-primary text-white text-[10px] font-bold px-3 py-1 rounded-lg z-10 shadow-md border border-red-400">ทดลองฟรี</div>
                                <img src="<?= $ex_img ?>" onerror="this.src='assets/images/no-image.png'" class="w-full h-full object-cover opacity-90 dark:opacity-80 group-hover:opacity-100 group-hover:scale-105 transition duration-500">
                            </div>
                            <div class="p-5 flex-1 flex flex-col relative bg-gradient-to-t from-white dark:from-card via-white dark:via-card to-transparent -mt-5 pt-6 transition-colors">
                                <h4 class="font-bold text-gray-900 dark:text-white text-xl group-hover:text-primary transition mb-2"><?= htmlspecialchars($ex['exercise_name']) ?></h4>
                                <p class="text-xs text-gray-500 dark:text-zinc-400 line-clamp-2 mb-4 flex-1"><?= htmlspecialchars($ex['exercise_detail']) ?></p>
                                <div class="mt-auto flex justify-between items-center text-sm font-bold text-primary">
                                    <span>เริ่มการฝึก <i class="fa-solid fa-play ml-1 text-xs"></i></span>
                                    <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition"></i>
                                </div>
                            </div>
                        </div>
                    <?php
                    endwhile;
                else:
                    ?>
                    <div class="col-span-full text-center py-10">
                        <p class="text-gray-500 dark:text-zinc-500 mb-4">ยังไม่มีท่าออกกำลังกายในระบบ</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
    // สคริปต์สลับธีม
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const htmlElement = document.documentElement;
    if (localStorage.getItem('gymfitt-theme') === 'light') htmlElement.classList.remove('dark');
    else htmlElement.classList.add('dark');

    themeToggleBtn.addEventListener('click', function() {
        htmlElement.classList.toggle('dark');
        localStorage.setItem('gymfitt-theme', htmlElement.classList.contains('dark') ? 'dark' : 'light');
    });

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('status') === 'completed') {
        Swal.fire({
            title: 'ฝึกซ้อมเสร็จสิ้น!',
            text: 'เยี่ยมมาก! อยากบันทึกสถิติและรับรางวัลไหม? สมัครสมาชิกเลย!',
            icon: 'success',
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'สมัครสมาชิก',
            showCancelButton: true,
            cancelButtonText: 'ปิด',
            cancelButtonColor: '#27272a',
            background: document.documentElement.classList.contains('dark') ? '#140505' : '#ffffff',
            color: document.documentElement.classList.contains('dark') ? '#ffffff' : '#1f2937'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'register.php';
            }
        });
    }
</script>
</body>

</html>