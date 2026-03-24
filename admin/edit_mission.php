<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}
include "../config/config.php";
$conn->set_charset("utf8mb4");

$success_msg = false;
$error_msg = false;

// 1. ตรวจสอบ ID
if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}
$mission_id = intval($_GET['id']);

// ดึงข้อมูลภารกิจเดิม
$stmt = $conn->prepare("SELECT * FROM missions WHERE mission_id = ?");
$stmt->bind_param("i", $mission_id);
$stmt->execute();
$mission = $stmt->get_result()->fetch_assoc();

if (!$mission) {
    header("Location: dashboard.php");
    exit();
}

// ดึงท่าออกกำลังกายทั้งหมดเตรียมไว้ใน Dropdown
$ex_query = $conn->query("SELECT * FROM exercises ORDER BY exercise_name ASC");
$exercises_list = $ex_query->fetch_all(MYSQLI_ASSOC);

// 2. จัดการการอัปเดตข้อมูล (POST)
if (isset($_POST['update_mission'])) {
    $name = $_POST['mission_name'];
    $detail = $_POST['mission_detail'];
    $exp = $_POST['exp_reward'];
    $token = $_POST['token_reward'];
    $limit = $_POST['daily_limit'];

    $mission_image = $mission['mission_image'] ?? ""; // ใช้รูปเดิมถ้าไม่ได้อัปใหม่
    $targetDir = "../uploads/";

    // อัปโหลดรูปหน้าปกใหม่ (ถ้ามี)
    if (!empty($_FILES["mission_image"]["name"])) {
        $ext = pathinfo($_FILES["mission_image"]["name"], PATHINFO_EXTENSION);
        $mission_image = time() . "_mission_img_" . rand(1000, 9999) . "." . $ext;
        move_uploaded_file($_FILES["mission_image"]["tmp_name"], $targetDir . $mission_image);
    }

    $update_sql = "UPDATE missions SET mission_name=?, mission_image=?, mission_detail=?, exp_reward=?, token_reward=?, daily_limit=? WHERE mission_id=?";
    $stmt_upd = $conn->prepare($update_sql);
    $stmt_upd->bind_param("sssiiii", $name, $mission_image, $detail, $exp, $token, $limit, $mission_id);

    if ($stmt_upd->execute()) {
        // ลบท่าที่เคยจับคู่ไว้ในภารกิจนี้ออกก่อน
        $conn->query("DELETE FROM mission_exercises WHERE mission_id = $mission_id");

        // บันทึกท่าที่เลือกเข้ามาใหม่
        if (isset($_POST['exercise_id']) && is_array($_POST['exercise_id'])) {
            $ex_ids = $_POST['exercise_id'];
            $sets = $_POST['sets'];

            for ($i = 0; $i < count($ex_ids); $i++) {
                $e_id = $ex_ids[$i];
                $set_val = $sets[$i];
                $seq = $i + 1;

                $stmt2 = $conn->prepare("INSERT INTO mission_exercises (mission_id, exercise_id, sequence_order, sets) VALUES (?,?,?,?)");
                $stmt2->bind_param("iiii", $mission_id, $e_id, $seq, $set_val);
                $stmt2->execute();
            }
        }
        $success_msg = true;

        // อัปเดตตัวแปรสำหรับแสดงผล
        $mission['mission_name'] = $name;
        $mission['mission_detail'] = $detail;
        $mission['exp_reward'] = $exp;
        $mission['token_reward'] = $token;
        $mission['daily_limit'] = $limit;
        $mission['mission_image'] = $mission_image;
    } else {
        $error_msg = "Error updating: " . $conn->error;
    }
}

// 3. ดึงรายการท่าออกกำลังกายที่มีอยู่ในภารกิจนี้ (ล่าสุด)
$stmt_mex = $conn->prepare("SELECT * FROM mission_exercises WHERE mission_id = ? ORDER BY sequence_order ASC");
$stmt_mex->bind_param("i", $mission_id);
$stmt_mex->execute();
$current_exercises = $stmt_mex->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขภารกิจ - GYMFITT</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #a855f7;
            --primary-hover: #9333ea;
            --bg-dark: #0f111a;
            --card-glass: rgba(255, 255, 255, 0.03);
            --border-glass: rgba(255, 255, 255, 0.08);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        body {
            background: radial-gradient(circle at top right, #1e2235, var(--bg-dark));
            color: var(--text-main);
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }

        .header-title {
            text-align: center;
            margin-bottom: 30px;
            font-weight: 700;
            font-size: 28px;
            color: var(--primary);
            text-shadow: 0 0 15px rgba(168, 85, 247, 0.3);
        }

        .main-container {
            max-width: 1200px;
            margin: auto;
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 30px;
        }

        @media (max-width: 900px) {
            .main-container {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: var(--card-glass);
            backdrop-filter: blur(20px);
            padding: 30px;
            border-radius: 24px;
            border: 1px solid var(--border-glass);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            height: fit-content;
        }

        .card h2 {
            font-size: 18px;
            margin-top: 0;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-glass);
            padding-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 8px;
            font-weight: 500;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 14px 18px;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-glass);
            color: white;
            border-radius: 12px;
            box-sizing: border-box;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(0, 0, 0, 0.4);
        }

        .btn {
            padding: 14px 24px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(168, 85, 247, 0.2);
        }

        .btn-success {
            background: #10b981;
            width: 100%;
            margin-top: 20px;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-glass);
            width: 100%;
            margin-top: 10px;
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        /* Exercise List Style */
        .exercise-row {
            display: flex;
            gap: 15px;
            align-items: center;
            background: rgba(0, 0, 0, 0.2);
            padding: 15px;
            border-radius: 15px;
            border: 1px solid var(--border-glass);
            margin-bottom: 12px;
            transition: 0.3s;
        }

        .exercise-row:hover {
            border-color: rgba(255, 255, 255, 0.2);
            background: rgba(0, 0, 0, 0.3);
        }

        .ex-select {
            flex-grow: 1;
            margin-bottom: 0;
        }

        .set-input-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .set-input {
            width: 85px;
            margin-bottom: 0;
            text-align: center;
        }

        .btn-remove {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: none;
            width: 48px;
            height: 48px;
            border-radius: 12px;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .btn-remove:hover {
            background: #ef4444;
            color: white;
        }

        .image-preview {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 15px;
            border: 1px solid var(--border-glass);
        }
    </style>
</head>

<body>

    <h1 class="header-title"><i class="fa-solid fa-pen-to-square"></i> แก้ไขภารกิจ: <?= htmlspecialchars($mission['mission_name']) ?></h1>

    <form method="POST" enctype="multipart/form-data">
        <div class="main-container">

            <div class="card">
                <h2><i class="fa-solid fa-circle-info"></i> ข้อมูลภารกิจ (Mission Info)</h2>

                <?php if (!empty($mission['mission_image'])): ?>
                    <img src="../uploads/<?= htmlspecialchars($mission['mission_image']) ?>" class="image-preview" alt="Mission Cover">
                <?php endif; ?>

                <div class="input-group">
                    <label>ชื่อภารกิจ</label>
                    <input type="text" name="mission_name" value="<?= htmlspecialchars($mission['mission_name']) ?>" required>
                </div>

                <div class="input-group">
                    <label>รายละเอียดภารกิจ</label>
                    <textarea name="mission_detail" rows="2"><?= htmlspecialchars($mission['mission_detail']) ?></textarea>
                </div>

                <div class="input-group">
                    <label>รูปหน้าปก (เลือกใหม่หากต้องการเปลี่ยน)</label>
                    <input type="file" name="mission_image" accept="image/*">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="input-group">
                        <label style="color: #38bdf8;"><i class="fa-solid fa-star"></i> ค่าประสบการณ์ (EXP)</label>
                        <input type="number" name="exp_reward" value="<?= $mission['exp_reward'] ?>" required>
                    </div>
                    <div class="input-group">
                        <label style="color: #fbbf24;"><i class="fa-solid fa-coins"></i> โทเคน (Token)</label>
                        <input type="number" name="token_reward" value="<?= $mission['token_reward'] ?>" required>
                    </div>
                </div>

                <div class="input-group">
                    <label><i class="fa-solid fa-clock"></i> จำกัดจำนวนเล่นต่อวัน (ครั้ง)</label>
                    <input type="number" name="daily_limit" value="<?= $mission['daily_limit'] ?>" required>
                </div>
            </div>

            <div class="card">
                <h2><i class="fa-solid fa-list-check"></i> ท่าออกกำลังกายในภารกิจ (Exercises)</h2>

                <div id="exercise-list">
                    <?php if (count($current_exercises) > 0): ?>
                        <?php foreach ($current_exercises as $ce): ?>
                            <div class="exercise-row">
                                <select name="exercise_id[]" required class="ex-select">
                                    <option value="">-- เลือกท่าออกกำลังกาย --</option>
                                    <?php foreach ($exercises_list as $ex): ?>
                                        <option value="<?= $ex['exercise_id'] ?>" <?= ($ex['exercise_id'] == $ce['exercise_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($ex['exercise_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="set-input-wrap">
                                    <input type="number" name="sets[]" value="<?= $ce['sets'] ?>" required min="1" class="set-input" title="จำนวนเซ็ต">
                                    <span style="font-size: 13px; color: var(--text-muted);">เซ็ต</span>
                                </div>
                                <button type="button" class="btn-remove" onclick="this.parentElement.remove()" title="ลบท่านี้"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="exercise-row">
                            <select name="exercise_id[]" required class="ex-select">
                                <option value="">-- เลือกท่าออกกำลังกาย --</option>
                                <?php foreach ($exercises_list as $ex): ?>
                                    <option value="<?= $ex['exercise_id'] ?>"><?= htmlspecialchars($ex['exercise_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="set-input-wrap">
                                <input type="number" name="sets[]" placeholder="เซ็ต" required min="1" class="set-input">
                                <span style="font-size: 13px; color: var(--text-muted);">เซ็ต</span>
                            </div>
                            <button type="button" class="btn-remove" onclick="this.parentElement.remove()" title="ลบท่านี้"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    <?php endif; ?>
                </div>

                <button type="button" class="btn" onclick="addExerciseRow()" style="background: #3b82f6; width: 100%; margin-top: 10px;">
                    <i class="fa-solid fa-plus"></i> เพิ่มท่าออกกำลังกาย
                </button>

                <div style="margin-top: 30px; border-top: 1px solid var(--border-glass); padding-top: 20px;">
                    <button type="submit" name="update_mission" class="btn btn-success">
                        <i class="fa-solid fa-save"></i> บันทึกการแก้ไขภารกิจ
                    </button>
                    <button type="button" class="btn btn-outline" onclick="window.location.href='dashboard.php'">
                        กลับไปหน้าแดชบอร์ด
                    </button>
                </div>
            </div>

        </div>
    </form>

    <template id="exercise-row-template">
        <div class="exercise-row" style="animation: slideIn 0.3s ease;">
            <select name="exercise_id[]" required class="ex-select">
                <option value="">-- เลือกท่าออกกำลังกาย --</option>
                <?php foreach ($exercises_list as $ex): ?>
                    <option value="<?= $ex['exercise_id'] ?>"><?= htmlspecialchars($ex['exercise_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="set-input-wrap">
                <input type="number" name="sets[]" value="1" required min="1" class="set-input">
                <span style="font-size: 13px; color: var(--text-muted);">เซ็ต</span>
            </div>
            <button type="button" class="btn-remove" onclick="this.parentElement.remove()" title="ลบท่านี้"><i class="fa-solid fa-trash"></i></button>
        </div>
    </template>

    <style>
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>

    <script>
        function addExerciseRow() {
            const template = document.getElementById('exercise-row-template');
            const clone = template.content.cloneNode(true);
            document.getElementById('exercise-list').appendChild(clone);
        }

        <?php if ($success_msg): ?>
            Swal.fire({
                title: 'อัปเดตสำเร็จ!',
                text: 'บันทึกข้อมูลและจัดเซ็ตภารกิจเรียบร้อยแล้ว',
                icon: 'success',
                background: '#1e2235',
                color: '#fff',
                confirmButtonColor: '#10b981'
            }).then(() => window.location = 'dashboard.php');
        <?php endif; ?>

        <?php if ($error_msg): ?>
            Swal.fire({
                title: 'เกิดข้อผิดพลาด!',
                text: '<?= addslashes($error_msg) ?>',
                icon: 'error',
                background: '#1e2235',
                color: '#fff',
                confirmButtonColor: '#ef4444'
            });
        <?php endif; ?>
    </script>
</body>

</html>