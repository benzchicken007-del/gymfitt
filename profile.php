<?php
session_start();
include "config/config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

/* ================= SAVE PROFILE ================= */
if (isset($_POST['saveProfile'])) {

    $full_name  = $conn->real_escape_string($_POST['full_name']);
    $birth_date = $_POST['birth_date'];
    $weight     = (float)$_POST['weight'];
    $height     = (float)$_POST['height'];
    $gender     = $conn->real_escape_string($_POST['gender']);

    $conn->query("UPDATE users SET
        full_name='$full_name',
        birth_date='$birth_date',
        weight=$weight,
        height=$height,
        gender='$gender'
        WHERE user_id=$user_id");

    $_SESSION['success'] = "บันทึกข้อมูลสำเร็จ!";
    header("Location: home.php");
    exit();
}

/* ================= UPLOAD IMAGE ================= */
if (isset($_POST['uploadAvatar']) && isset($_FILES['avatar']['name'])) {
    // แก้ไขให้เซฟไปที่โฟลเดอร์ uploads/avatars/ ให้ตรงกับโครงสร้างใหม่
    $folder = "uploads/avatars/";
    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }

    $filename = time() . "_" . basename($_FILES['avatar']['name']);
    $path = $folder . $filename;

    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $path)) {
        $conn->query("UPDATE users SET profile_image='$path' WHERE user_id=$user_id");

        $_SESSION['success'] = "อัปโหลดรูปสำเร็จ!";
    }

    header("Location: profile.php");
    exit();
}

$stmtUser = $conn->prepare("SELECT u.*, t.title_id as active_id, t.title_name, t.title_color FROM users u LEFT JOIN shop_titles t ON u.title_active_id = t.title_id WHERE u.user_id=?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();

$full_name = !empty($user['full_name']) ? $user['full_name'] : $user['username'];
$active_title = !empty($user['title_name']) ? $user['title_name'] : "";
$active_title_color = !empty($user['title_color']) ? $user['title_color'] : "#ffffff";

// ดึงค่าอย่างปลอดภัยแบบเดียวกับหน้า home.php
$current_level = !empty($user['level']) ? (int)$user['level'] : 0;
$current_exp = !empty($user['exp']) ? (int)$user['exp'] : 0;
$tokens = !empty($user['tokens']) ? (int)$user['tokens'] : 0;

// --- ระบบรูปโปรไฟล์แบบป้องกันรูปหาย (แบบเดียวกับหน้า home.php) ---
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
// ------------------------------------------------

$page_title = "โปรไฟล์ของฉัน - Gymfitt";
include 'includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    /* ปรับแต่ง SweetAlert ให้เข้ากับธีม */
    .swal2-popup {
        background: #140505 !important;
        border: 1px solid #7f1d1d !important;
        color: #fff !important;
    }

    .swal2-confirm {
        background: #dc2626 !important;
    }

    .swal2-cancel {
        background: #27272a !important;
    }
</style>

<?php include 'includes/navbar.php'; ?>

<main class="bg-gray-50 dark:bg-transparent min-h-screen transition-colors duration-300 py-8 relative">

    <div class="fixed top-20 right-0 w-96 h-96 bg-red-600/10 blur-[100px] rounded-full pointer-events-none z-[-1] hidden dark:block"></div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-card rounded-3xl p-8 shadow-xl dark:shadow-2xl border border-gray-200 dark:border-red-900/30 relative overflow-hidden transition-colors duration-300">
            <div class="absolute -top-20 -right-20 w-48 h-48 bg-primary/10 dark:bg-primary/20 blur-[50px] rounded-full"></div>

            <div class="flex flex-col items-center mb-10 relative z-10">
                <div class="relative group">
                    <img src="<?php echo $profile_pic; ?>" class="w-36 h-36 rounded-full border-4 border-white dark:border-darker object-cover shadow-lg dark:shadow-[0_0_15px_rgba(220,38,38,0.5)] bg-gray-200">

                    <form method="POST" enctype="multipart/form-data" id="avatarForm" class="absolute bottom-1 right-1">
                        <label for="avatarInput" class="bg-gradient-to-r from-primary to-red-700 dark:to-red-900 text-white w-10 h-10 rounded-full flex items-center justify-center cursor-pointer shadow-lg transition border-2 border-white dark:border-darker">
                            <i class="fa-solid fa-camera"></i>
                        </label>
                        <input type="file" id="avatarInput" name="avatar" class="hidden" accept="image/*" onchange="document.getElementById('uploadAvatarBtn').click();">
                        <button type="submit" name="uploadAvatar" id="uploadAvatarBtn" class="hidden"></button>
                    </form>
                </div>

                <h2 class="mt-4 text-3xl font-bold text-gray-900 dark:text-white tracking-wide transition-colors"><?php echo htmlspecialchars($full_name); ?></h2>

                <?php if ($active_title != ""): ?>
                    <span class="mt-2 text-sm font-bold px-4 py-1 rounded-full bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-500/50 shadow-sm dark:shadow-[0_0_10px_currentColor] transition" style="color: <?php echo $active_title_color; ?>;">
                        <?php echo htmlspecialchars($active_title); ?>
                    </span>
                <?php endif; ?>

                <div class="text-primary font-bold text-sm mt-3 bg-red-50 dark:bg-primary/10 px-4 py-1 rounded-full border border-red-200 dark:border-primary/20 shadow-sm">
                    LV. <?php echo $current_level; ?>
                    <span class="text-gray-400 dark:text-zinc-500 mx-2">|</span>
                    EXP <?php echo $current_exp; ?>
                    <span class="text-gray-400 dark:text-zinc-500 mx-2">|</span>
                    <span class="text-yellow-600 dark:text-yellow-500"><i class="fa-solid fa-coins"></i> <?php echo number_format($tokens); ?></span>
                </div>
            </div>

            <form method="POST" id="profileForm" class="space-y-5 relative z-10">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-gray-600 dark:text-zinc-400 text-xs uppercase tracking-wider mb-2 font-bold transition-colors"><i class="fa-solid fa-user text-primary mr-1"></i> ชื่อ-นามสกุล</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required class="w-full bg-gray-50 dark:bg-darker border border-gray-300 dark:border-red-900/30 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                    </div>
                    <div>
                        <label class="block text-gray-600 dark:text-zinc-400 text-xs uppercase tracking-wider mb-2 font-bold transition-colors"><i class="fa-solid fa-cake-candles text-primary mr-1"></i> วันเกิด</label>
                        <input type="date" name="birth_date" value="<?php echo $user['birth_date']; ?>" class="w-full bg-gray-50 dark:bg-darker border border-gray-300 dark:border-red-900/30 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                    </div>
                    <div>
                        <label class="block text-gray-600 dark:text-zinc-400 text-xs uppercase tracking-wider mb-2 font-bold transition-colors"><i class="fa-solid fa-weight-scale text-primary mr-1"></i> น้ำหนัก (KG)</label>
                        <input type="number" step="0.1" name="weight" value="<?php echo $user['weight']; ?>" class="w-full bg-gray-50 dark:bg-darker border border-gray-300 dark:border-red-900/30 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                    </div>
                    <div>
                        <label class="block text-gray-600 dark:text-zinc-400 text-xs uppercase tracking-wider mb-2 font-bold transition-colors"><i class="fa-solid fa-ruler-vertical text-primary mr-1"></i> ส่วนสูง (CM)</label>
                        <input type="number" step="0.1" name="height" value="<?php echo $user['height']; ?>" class="w-full bg-gray-50 dark:bg-darker border border-gray-300 dark:border-red-900/30 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-gray-600 dark:text-zinc-400 text-xs uppercase tracking-wider mb-2 font-bold transition-colors"><i class="fa-solid fa-venus-mars text-primary mr-1"></i> เพศ</label>
                        <select name="gender" class="w-full bg-gray-50 dark:bg-darker border border-gray-300 dark:border-red-900/30 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors appearance-none">
                            <option value="">เลือกเพศ</option>
                            <option value="ชาย" <?php echo ($user['gender'] == "ชาย") ? 'selected' : ''; ?>>ชาย</option>
                            <option value="หญิง" <?php echo ($user['gender'] == "หญิง") ? 'selected' : ''; ?>>หญิง</option>
                            <option value="อื่นๆ" <?php echo ($user['gender'] == "อื่นๆ") ? 'selected' : ''; ?>>อื่นๆ</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-4 mt-8 pt-6 border-t border-gray-200 dark:border-red-900/30 transition-colors">
                    <button type="button" onclick="window.location='home.php'" class="px-6 py-3 rounded-xl font-bold text-gray-700 dark:text-zinc-300 bg-gray-200 dark:bg-zinc-800 hover:bg-gray-300 dark:hover:bg-zinc-700 transition">ย้อนกลับ</button>
                    <button type="button" onclick="confirmSave()" class="px-8 py-3 rounded-xl font-bold text-white bg-primary hover:bg-red-600 shadow-md dark:shadow-[0_0_15px_rgba(220,38,38,0.4)] transition transform hover:-translate-y-1">บันทึกข้อมูล</button>
                    <input type="hidden" name="saveProfile" value="1">
                </div>
            </form>
        </div>
    </div>
</main>

<script>
    function getSwalBg() {
        return document.documentElement.classList.contains('dark') ? '#140505' : '#ffffff';
    }

    function getSwalColor() {
        return document.documentElement.classList.contains('dark') ? '#ffffff' : '#1f2937';
    }

    function confirmSave() {
        Swal.fire({
            title: 'ยืนยันการบันทึก',
            text: 'คุณต้องการอัปเดตข้อมูลโปรไฟล์ใช่หรือไม่?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'ยืนยันการบันทึก',
            cancelButtonText: 'ยกเลิก',
            background: getSwalBg(),
            color: getSwalColor()
        }).then((result) => {
            if (result.isConfirmed) document.getElementById("profileForm").submit();

        });
    }

    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
            title: 'สำเร็จ!',
            text: '<?php echo $_SESSION['success']; ?>',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false,
            background: getSwalBg(),
            color: getSwalColor()
        });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
</script>
</body>

</html>