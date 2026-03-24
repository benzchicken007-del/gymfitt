<?php
session_start();
date_default_timezone_set("Asia/Bangkok");
include "config/config.php";

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$success = false;
$error_msg = false;

/* ================= 1. ดึงข้อมูลปัจจุบันมาแสดง ================= */
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$u_fullname   = $user['full_name'] ?? "";
$u_birth_date = $user['birth_date'] ?? "";
$u_gender      = $user['gender'] ?? "ชาย";
$u_weight      = (float)($user['weight'] ?? 0);
$u_height      = (float)($user['height'] ?? 0);
$u_img         = !empty($user['profile_image']) ? $user['profile_image'] : "https://via.placeholder.com/150";

/* ================= 2. ส่วนการบันทึกข้อมูลแก้ไข ================= */
if(isset($_POST['update_profile'])){
    $full_name  = $_POST['full_name'];
    $birth_date = $_POST['birth_date'];
    $gender     = $_POST['gender'];
    $weight     = (float)$_POST['weight'];
    $height     = (float)$_POST['height'];
    
    $profile_image = $user['profile_image']; 
    if(isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] == 0){
        $targetDir = "uploads/";
        if(!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $fileName = time() . "_" . basename($_FILES["profile_img"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        if(move_uploaded_file($_FILES["profile_img"]["tmp_name"], $targetFilePath)){
            $profile_image = $targetFilePath;
        }
    }

    $sql = "UPDATE users SET full_name=?, birth_date=?, gender=?, weight=?, height=?, profile_image=? WHERE user_id=?";
    $update = $conn->prepare($sql);
    
    if($update){
        $update->bind_param("sssdssi", $full_name, $birth_date, $gender, $weight, $height, $profile_image, $user_id);
        if($update->execute()){
            $success = true;
            $u_fullname = $full_name; $u_birth_date = $birth_date; $u_gender = $gender;
            $u_weight = $weight; $u_height = $height; $u_img = $profile_image;
        } else {
            $error_msg = "เกิดข้อผิดพลาดในการบันทึก: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - GYMFITT</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #ef4444;
            --primary-gradient: linear-gradient(135deg, #ef4444, #f97316);
            --bg: #09090b;
            --card: rgba(24, 24, 27, 0.8);
            --border: rgba(255, 255, 255, 0.1);
            --text: #fafafa;
            --input-bg: rgba(0, 0, 0, 0.3);
        }

        body.light-mode {
            --bg: #f4f4f5;
            --card: rgba(255, 255, 255, 0.9);
            --border: rgba(0, 0, 0, 0.05);
            --text: #18181b;
            --primary: #3b82f6;
            --primary-gradient: linear-gradient(135deg, #3b82f6, #06b6d4);
            --input-bg: #fff;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Poppins', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 40px 20px;
            transition: 0.3s;
            background-image: radial-gradient(circle at top right, rgba(239, 68, 68, 0.1), transparent);
        }

        .card {
            background: var(--card);
            backdrop-filter: blur(15px);
            padding: 40px;
            border-radius: 35px;
            width: 100%;
            max-width: 500px;
            border: 1px solid var(--border);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        h2 { 
            font-weight: 700; 
            text-align: center; 
            margin-bottom: 35px; 
            letter-spacing: -0.5px;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .profile-upload {
            position: relative;
            width: 130px;
            margin: 0 auto 35px;
        }

        .profile-preview {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--card);
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            background: var(--input-bg);
            transition: 0.3s;
        }

        .upload-btn {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: var(--primary-gradient);
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            border: 3px solid var(--card);
            transition: 0.3s;
        }

        .upload-btn:hover { transform: scale(1.1); }

        .form-group { margin-bottom: 22px; }
        
        label { 
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px; 
            color: #888; 
            margin-bottom: 10px; 
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input, select { 
            width: 100%; 
            padding: 14px 18px; 
            background: var(--input-bg); 
            border: 1px solid var(--border); 
            color: var(--text); 
            border-radius: 16px; 
            font-size: 15px;
            transition: 0.3s;
        }

        input:focus { 
            border-color: var(--primary); 
            outline: none; 
            background: rgba(255,255,255,0.05);
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
        }

        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

        .btn-submit { 
            width: 100%; 
            padding: 16px; 
            background: var(--primary-gradient); 
            border: none; 
            color: white; 
            font-weight: 700; 
            border-radius: 18px; 
            cursor: pointer; 
            margin-top: 15px; 
            transition: 0.3s; 
            font-size: 16px;
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.2);
        }

        .btn-submit:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 15px 25px rgba(239, 68, 68, 0.3);
        }

        .btn-back { 
            display: block; 
            text-align: center; 
            margin-top: 25px; 
            color: #71717a; 
            text-decoration: none; 
            font-size: 14px; 
            font-weight: 500;
            transition: 0.2s; 
        }

        .btn-back:hover { color: var(--primary); }

        #file-input { display: none; }
    </style>
</head>
<body>

<script>
    (function initTheme() {
        const savedTheme = localStorage.getItem('gymfitt-theme');
        if (savedTheme === 'light') document.body.classList.add('light-mode');
    })();
</script>

<div class="card">
    <h2><i class="fa-solid fa-user-gear"></i> Account Settings</h2>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="profile-upload">
            <img src="<?= $u_img ?>" id="preview" class="profile-preview" alt="โปรไฟล์">
            <label for="file-input" class="upload-btn">
                <i class="fa-solid fa-camera"></i>
            </label>
            <input type="file" name="profile_img" id="file-input" accept="image/*">
        </div>

        <div class="form-group">
            <label><i class="fa-solid fa-id-card"></i> ชื่อ-นามสกุล</label>
            <input type="text" name="full_name" value="<?= htmlspecialchars($u_fullname) ?>" required placeholder="ระบุชื่อจริงของคุณ">
        </div>

        <div class="grid">
            <div class="form-group">
                <label><i class="fa-solid fa-calendar-days"></i> วันเกิด</label>
                <input type="date" name="birth_date" value="<?= $u_birth_date ?>" required>
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-venus-mars"></i> เพศ</label>
                <select name="gender">
                    <option value="ชาย" <?= ($u_gender == 'ชาย') ? 'selected' : '' ?>>ชาย</option>
                    <option value="หญิง" <?= ($u_gender == 'หญิง') ? 'selected' : '' ?>>หญิง</option>
                </select>
            </div>
        </div>

        <div class="grid">
            <div class="form-group">
                <label><i class="fa-solid fa-weight-scale"></i> น้ำหนัก (กก.)</label>
                <input type="number" step="0.1" name="weight" value="<?= $u_weight ?>" required>
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-ruler-vertical"></i> ส่วนสูง (ซม.)</label>
                <input type="number" step="0.1" name="height" value="<?= $u_height ?>" required>
            </div>
        </div>

        <button type="submit" name="update_profile" class="btn-submit">
            <i class="fa-solid fa-check-circle"></i> บันทึกการเปลี่ยนแปลง
        </button>
        
        <a href="home.php" class="btn-back">ยกเลิกและย้อนกลับ</a>
    </form>
</div>

<script>
    // Preview รูปภาพทันทีเมื่อเลือกไฟล์
    const fileInput = document.getElementById('file-input');
    const previewImg = document.getElementById('preview');

    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    });

    // แสดงป๊อปอัพเมื่อสำเร็จ
    <?php if($success): ?>
        Swal.fire({
            icon: 'success',
            title: 'บันทึกสำเร็จ!',
            text: 'ข้อมูลโปรไฟล์ของคุณถูกอัปเดตเรียบร้อยแล้ว',
            confirmButtonColor: document.body.classList.contains('light-mode') ? '#3b82f6' : '#ef4444',
            background: document.body.classList.contains('light-mode') ? '#fff' : '#18181b',
            color: document.body.classList.contains('light-mode') ? '#000' : '#fff',
            timer: 2000
        }).then(() => { window.location = 'home.php'; });
    <?php endif; ?>

    // แสดงป๊อปอัพเมื่อเกิดข้อผิดพลาด
    <?php if($error_msg): ?>
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: '<?= $error_msg ?>',
            confirmButtonColor: '#ef4444',
            background: document.body.classList.contains('light-mode') ? '#fff' : '#18181b',
            color: document.body.classList.contains('light-mode') ? '#000' : '#fff'
        });
    <?php endif; ?>
</script>

</body>
</html>