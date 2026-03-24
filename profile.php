<?php
session_start();
include "config/config.php";

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

/* ================= SAVE PROFILE ================= */
if(isset($_POST['saveProfile'])){

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
if(isset($_POST['uploadAvatar']) && isset($_FILES['avatar']['name'])){

    $folder = "uploads/";
    if(!is_dir($folder)){
        mkdir($folder, 0777, true);
    }

    $filename = time()."_".basename($_FILES['avatar']['name']);
    $path = $folder.$filename;

    if(move_uploaded_file($_FILES['avatar']['tmp_name'], $path)){
        $conn->query("UPDATE users SET profile_image='$path' WHERE user_id=$user_id");
        $_SESSION['success'] = "อัปโหลดรูปสำเร็จ!";
    }

    header("Location: profile.php");
    exit();
}

$user = $conn->query("SELECT * FROM users WHERE user_id=$user_id")->fetch_assoc();

$avatar = !empty($user['profile_image']) 
    ? $user['profile_image'] 
    : "https://via.placeholder.com/130";
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>โปรไฟล์ - GYMFITT</title>
<link rel="stylesheet" href="css/profile.css">
</head>
<body>

<header>
    <div class="logo">GYMFITT</div>
    <a href="home.php" class="back">← กลับหน้าหลัก</a>
</header>

<div class="container">

<div class="profile-card">

    <img src="<?= $avatar ?>" class="avatar">

    <div class="level">
        Level <?= $user['level'] ?> | EXP <?= $user['exp'] ?>
    </div>

    <!-- Upload Image -->
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="avatar" required>
        <button name="uploadAvatar" class="btn">อัปโหลดรูป</button>
    </form>

    <!-- Edit Profile -->
    <form method="POST" id="profileForm">

        <input type="text" name="full_name"
            value="<?= htmlspecialchars($user['full_name']) ?>"
            placeholder="ชื่อ-สกุล" required>

        <input type="date" name="birth_date"
            value="<?= $user['birth_date'] ?>">

        <input type="number" step="0.1" name="weight"
            value="<?= $user['weight'] ?>"
            placeholder="น้ำหนัก (kg)">

        <input type="number" step="0.1" name="height"
            value="<?= $user['height'] ?>"
            placeholder="ส่วนสูง (cm)">

        <select name="gender">
            <option value="">เลือกเพศ</option>
            <option value="ชาย" <?= ($user['gender']=="ชาย")?'selected':'' ?>>ชาย</option>
            <option value="หญิง" <?= ($user['gender']=="หญิง")?'selected':'' ?>>หญิง</option>
            <option value="อื่นๆ" <?= ($user['gender']=="อื่นๆ")?'selected':'' ?>>อื่นๆ</option>
        </select>

        <button type="button" class="btn save-btn" onclick="openModal()">บันทึกข้อมูล</button>
        <input type="hidden" name="saveProfile" value="1">

    </form>

</div>
</div>

<!-- Confirm Modal -->
<div class="modal" id="confirmModal">
    <div class="modal-box">
        <h3>ยืนยันการบันทึก</h3>
        <p>คุณต้องการบันทึกข้อมูลโปรไฟล์ใช่หรือไม่?</p>
        <div class="modal-buttons">
            <button class="cancel" onclick="closeModal()">ยกเลิก</button>
            <button class="confirm" onclick="submitForm()">ยืนยัน</button>
        </div>
    </div>
</div>

<?php if(isset($_SESSION['success'])){ ?>
<div class="toast" id="toast">
    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
</div>
<?php } ?>

<script>
function openModal(){
    document.getElementById("confirmModal").classList.add("show");
}
function closeModal(){
    document.getElementById("confirmModal").classList.remove("show");
}
function submitForm(){
    document.getElementById("profileForm").submit();
}

/* Toast Auto Hide */
window.onload = function(){
    const toast = document.getElementById("toast");
    if(toast){
        setTimeout(()=>{
            toast.classList.add("show");
        },200);
        setTimeout(()=>{
            toast.classList.remove("show");
        },3000);
    }
}
</script>

</body>
</html>
