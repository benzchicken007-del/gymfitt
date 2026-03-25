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
    header("Location: profile.php");
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

$user = $conn->query("SELECT * FROM users WHERE user_id=$user_id")->fetch_assoc();

// --- โค้ดดึงรูปโปรไฟล์ (แบบเดียวกับหน้า home.php) ---
$db_pic = !empty($user['profile_image']) ? $user['profile_image'] : "";
$profile_pic = "uploads/avatars/Avatar.jpg";

if ($db_pic) {
    $filename = basename($db_pic);
    if (file_exists("uploads/avatars/" . $filename)) {
        $profile_pic = "uploads/avatars/" . $filename;
    } elseif (file_exists("uploads/" . $filename)) {
        $profile_pic = "uploads/" . $filename;
    } elseif (file_exists($db_pic)) {
        $profile_pic = $db_pic;
    }
}
$full_name = !empty($user['full_name']) ? $user['full_name'] : $user['username'];
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

<main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 relative">

    <div class="fixed top-20 right-0 w-96 h-96 bg-red-600/10 blur-[100px] rounded-full pointer-events-none z-[-1]"></div>

    <div class="bg-card rounded-3xl p-8 shadow-2xl border border-red-900/30 relative overflow-hidden">
        <div class="absolute -top-20 -right-20 w-48 h-48 bg-primary/20 blur-[50px] rounded-full"></div>

        <div class="flex flex-col items-center mb-10 relative z-10">
            <div class="relative group">
                <img src="<?= $profile_pic ?>" onerror="this.onerror=null; this.src='https://api.dicebear.com/7.x/initials/svg?seed=<?= urlencode($full_name) ?>&backgroundColor=dc2626&textColor=ffffff';" class="w-36 h-36 rounded-full border-4 border-darker object-cover shadow-[0_0_15px_rgba(220,38,38,0.5)]">

                <form method="POST" enctype="multipart/form-data" id="avatarForm" class="absolute bottom-1 right-1">
                    <label for="avatarInput" class="bg-primary hover:bg-red-500 text-white w-10 h-10 rounded-full flex items-center justify-center cursor-pointer shadow-lg transition border-2 border-darker">
                        <i class="fa-solid fa-camera"></i>
                    </label>
                    <input type="file" id="avatarInput" name="avatar" class="hidden" accept="image/*" onchange="document.getElementById('uploadAvatarBtn').click();">
                    <button type="submit" name="uploadAvatar" id="uploadAvatarBtn" class="hidden"></button>
                </form>
            </div>

            <h2 class="mt-4 text-3xl font-bold text-white tracking-wide"><?= htmlspecialchars($full_name) ?></h2>
            <div class="text-primary font-bold text-sm mt-1 bg-primary/10 px-4 py-1 rounded-full border border-primary/20 mt-2">
                Level <?= $user['level'] ?> <span class="text-zinc-500 mx-2">|</span> EXP <?= $user['exp'] ?>
            </div>
        </div>

        <form method="POST" id="profileForm" class="space-y-5 relative z-10">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <div>
                    <label class="block text-zinc-400 text-xs uppercase tracking-wider mb-2 font-bold"><i class="fa-solid fa-user text-primary mr-1"></i> ชื่อ-นามสกุล</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required placeholder="กรอกชื่อของคุณ" class="w-full bg-darker border border-red-900/30 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition">
                </div>

                <div>
                    <label class="block text-zinc-400 text-xs uppercase tracking-wider mb-2 font-bold"><i class="fa-solid fa-cake-candles text-primary mr-1"></i> วันเกิด</label>
                    <input type="date" name="birth_date" value="<?= $user['birth_date'] ?>" class="w-full bg-darker border border-red-900/30 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition [color-scheme:dark]">
                </div>

                <div>
                    <label class="block text-zinc-400 text-xs uppercase tracking-wider mb-2 font-bold"><i class="fa-solid fa-weight-scale text-primary mr-1"></i> น้ำหนัก (KG)</label>
                    <input type="number" step="0.1" name="weight" value="<?= $user['weight'] ?>" placeholder="0.0" class="w-full bg-darker border border-red-900/30 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition">
                </div>

                <div>
                    <label class="block text-zinc-400 text-xs uppercase tracking-wider mb-2 font-bold"><i class="fa-solid fa-ruler-vertical text-primary mr-1"></i> ส่วนสูง (CM)</label>
                    <input type="number" step="0.1" name="height" value="<?= $user['height'] ?>" placeholder="0.0" class="w-full bg-darker border border-red-900/30 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-zinc-400 text-xs uppercase tracking-wider mb-2 font-bold"><i class="fa-solid fa-venus-mars text-primary mr-1"></i> เพศ</label>
                    <select name="gender" class="w-full bg-darker border border-red-900/30 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition appearance-none">
                        <option value="" class="bg-darker">เลือกเพศ</option>
                        <option value="ชาย" <?= ($user['gender'] == "ชาย") ? 'selected' : '' ?> class="bg-darker">ชาย</option>
                        <option value="หญิง" <?= ($user['gender'] == "หญิง") ? 'selected' : '' ?> class="bg-darker">หญิง</option>
                        <option value="อื่นๆ" <?= ($user['gender'] == "อื่นๆ") ? 'selected' : '' ?> class="bg-darker">อื่นๆ</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-4 mt-8 pt-6 border-t border-red-900/30">
                <button type="button" onclick="window.location='home.php'" class="px-6 py-3 rounded-xl font-bold text-zinc-300 bg-zinc-800 hover:bg-zinc-700 transition">ย้อนกลับ</button>
                <button type="button" onclick="confirmSave()" class="px-8 py-3 rounded-xl font-bold text-white bg-primary hover:bg-red-500 shadow-[0_0_15px_rgba(220,38,38,0.4)] transition transform hover:-translate-y-1">บันทึกข้อมูล</button>
                <input type="hidden" name="saveProfile" value="1">
            </div>
        </form>

    </div>
</main>

<script>
    // ฟังก์ชันกดปุ่มบันทึกแล้วขึ้น Popup ถามยืนยัน
    function confirmSave() {
        Swal.fire({
            title: 'ยืนยันการบันทึก',
            text: 'คุณต้องการอัปเดตข้อมูลโปรไฟล์ใช่หรือไม่?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'ยืนยันการบันทึก',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById("profileForm").submit();
            }
        });
    }

    // แจ้งเตือนเมื่ออัปเดตข้อมูลสำเร็จ
    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
            title: 'สำเร็จ!',
            text: '<?= $_SESSION['success'] ?>',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        });
    <?php endif; ?>
</script>

</body>

</html>