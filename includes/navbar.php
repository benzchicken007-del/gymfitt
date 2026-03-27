<nav class="bg-white dark:bg-dark border-b border-gray-200 dark:border-red-900/40 sticky top-0 z-50 transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">

            <a href="home.php" class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-tr from-primary to-red-400 dark:to-white rounded-xl flex items-center justify-center shadow-lg shadow-red-600/40">
                    <i class="fa-solid fa-dumbbell text-white dark:text-darker"></i>
                </div>
                <span class="font-bold text-xl tracking-wider text-gray-900 dark:text-white">GYM<span class="text-primary drop-shadow-md dark:drop-shadow-[0_0_8px_rgba(220,38,38,0.8)]">FITT</span></span>
            </a>

            <div class="hidden md:flex space-x-4">
                <a href="home.php" class="text-primary dark:text-white bg-red-50 dark:bg-red-950/60 border border-red-200 dark:border-red-900/50 px-4 py-2 rounded-lg font-medium transition hover:bg-red-100 dark:hover:bg-red-900/80"><i class="fa-solid fa-house mr-2 text-primary"></i>หน้าแรก</a>
                <a href="missions.php" class="text-gray-500 dark:text-zinc-400 hover:text-primary dark:hover:text-white px-4 py-2 rounded-lg font-medium transition"><i class="fa-solid fa-trophy mr-2"></i>รายการภารกิจ</a>
            </div>

            <div class="flex items-center gap-4">

                <button id="themeToggleBtn" class="w-9 h-9 flex items-center justify-center rounded-full bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-zinc-400 hover:text-primary dark:hover:text-white transition shadow-inner">
                    <i class="fa-solid fa-sun hidden dark:block"></i>
                    <i class="fa-solid fa-moon block dark:hidden"></i>
                </button>

                <div class="flex items-center gap-2 bg-yellow-50 dark:bg-zinc-900 px-3 py-1.5 rounded-full border border-yellow-300 dark:border-yellow-500/30">
                    <i class="fa-solid fa-coins text-yellow-500 dark:text-yellow-400"></i>
                    <span class="font-semibold text-yellow-600 dark:text-yellow-400"><?php echo isset($tokens) ? number_format($tokens) : '0'; ?></span>
                </div>

                <a href="profile.php" class="w-10 h-10 rounded-full overflow-hidden border-2 border-primary shadow-[0_0_10px_rgba(220,38,38,0.5)] transition transform hover:scale-110 block bg-zinc-200">
                    <img src="<?php echo isset($profile_pic) ? $profile_pic : 'https://api.dicebear.com/7.x/initials/svg?seed=User&backgroundColor=dc2626&textColor=ffffff'; ?>" onerror="this.onerror=null; this.src='https://api.dicebear.com/7.x/initials/svg?seed=User&backgroundColor=dc2626&textColor=ffffff';" alt="Profile" class="w-full h-full object-cover">
                </a>

                <a href="logout.php" class="w-9 h-9 flex items-center justify-center rounded-lg bg-red-50 dark:bg-red-900/20 text-primary hover:bg-primary hover:text-white transition border border-red-200 dark:border-red-900/50" title="ออกจากระบบ">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>

        </div>
    </div>
</nav>

<script>
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const htmlElement = document.documentElement;

    // ตรวจสอบค่าธีมเดิมที่เคยเลือกไว้ (ค่าเริ่มต้นคือ Dark)
    if (localStorage.getItem('gymfitt-theme') === 'light') {
        htmlElement.classList.remove('dark');
    } else {
        htmlElement.classList.add('dark');
    }

    // เมื่อกดปุ่มสลับธีม
    themeToggleBtn.addEventListener('click', function() {
        if (htmlElement.classList.contains('dark')) {
            htmlElement.classList.remove('dark');
            localStorage.setItem('gymfitt-theme', 'light');
        } else {
            htmlElement.classList.add('dark');
            localStorage.setItem('gymfitt-theme', 'dark');
        }
    });
</script>