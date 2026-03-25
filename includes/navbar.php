<nav class="bg-dark border-b border-red-900/40 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">

            <a href="home.php" class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-tr from-primary to-white rounded-xl flex items-center justify-center shadow-lg shadow-red-600/40">
                    <i class="fa-solid fa-dumbbell text-darker"></i>
                </div>
                <span class="font-bold text-xl tracking-wider text-white">GYM<span class="text-primary drop-shadow-[0_0_8px_rgba(220,38,38,0.8)]">FITT</span></span>
            </a>

            <div class="hidden md:flex space-x-4">
                <a href="home.php" class="text-white bg-red-950/60 border border-red-900/50 px-4 py-2 rounded-lg font-medium transition hover:bg-red-900/80"><i class="fa-solid fa-house mr-2 text-primary"></i>หน้าแรก</a>
                <a href="play_mission.php" class="text-zinc-400 hover:text-white px-4 py-2 rounded-lg font-medium transition"><i class="fa-solid fa-fire mr-2"></i>ภารกิจ</a>
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2 bg-zinc-900 px-3 py-1.5 rounded-full border border-yellow-500/30">
                    <i class="fa-solid fa-coins text-yellow-400"></i>
                    <span class="font-semibold text-yellow-400"><?php echo isset($tokens) ? number_format($tokens) : '0'; ?></span>
                </div>

                <a href="profile.php" class="w-10 h-10 rounded-full overflow-hidden border-2 border-primary shadow-[0_0_10px_rgba(220,38,38,0.5)] transition transform hover:scale-110 block">
                    <img src="<?php echo isset($profile_pic) ? $profile_pic : 'https://api.dicebear.com/7.x/initials/svg?seed=User&backgroundColor=dc2626&textColor=ffffff'; ?>" onerror="this.onerror=null; this.src='https://api.dicebear.com/7.x/initials/svg?seed=User&backgroundColor=dc2626&textColor=ffffff';" alt="Profile" class="w-full h-full object-cover bg-zinc-300">
                </a>
            </div>

        </div>
    </div>
</nav>