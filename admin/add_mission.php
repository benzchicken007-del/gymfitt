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

// ดึงท่าออกกำลังกายทั้งหมดมาเตรียมไว้สร้าง Dropdown
$ex_query = $conn->query("SELECT * FROM exercises ORDER BY exercise_name ASC");
$exercises_list = $ex_query->fetch_all(MYSQLI_ASSOC);

if (isset($_POST['save_mission'])) {
    $name = $_POST['mission_name'];
    $detail = $_POST['mission_detail'];
    $exp = $_POST['exp_reward'];
    $token = $_POST['token_reward'];
    $limit = $_POST['daily_limit'];

    $mission_image = "";
    $targetDir = "../uploads/";
    if (!empty($_FILES["mission_image"]["name"])) {
        $ext = pathinfo($_FILES["mission_image"]["name"], PATHINFO_EXTENSION);
        $mission_image = time() . "_mission_img_" . rand(1000, 9999) . "." . $ext;
        move_uploaded_file($_FILES["mission_image"]["tmp_name"], $targetDir . $mission_image);
    }

    $stmt = $conn->prepare("INSERT INTO missions (mission_name, mission_image, mission_detail, exp_reward, token_reward, daily_limit) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("sssiii", $name, $mission_image, $detail, $exp, $token, $limit);

    if ($stmt->execute()) {
        $mission_id = $stmt->insert_id;

        // บันทึกท่าที่เลือกเข้าภารกิจนี้
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
    } else {
        $error_msg = "Error: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>สร้างภารกิจ (จัดเซ็ต) - GYMFITT</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: #0f111a;
            color: white;
            font-family: 'Poppins', sans-serif;
            padding: 20px;
        }

        .card {
            background: rgba(255, 255, 255, 0.05);
            padding: 30px;
            border-radius: 20px;
            max-width: 900px;
            margin: auto;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid #333;
            color: white;
            border-radius: 10px;
        }

        .btn {
            padding: 10px 20px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
        }

        .exercise-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
            background: rgba(0, 0, 0, 0.5);
            padding: 10px;
            border-radius: 10px;
        }

        .exercise-row select {
            margin-bottom: 0;
        }

        .exercise-row input {
            margin-bottom: 0;
            width: 150px;
        }
    </style>
</head>

<body>

    <div class="card">
        <h2>สร้างภารกิจและจัดเซ็ต (Create Mission)</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="text" name="mission_name" placeholder="ชื่อภารกิจ เช่น ฟิตเนสเบื้องต้น 3 ท่า" required>
            <textarea name="mission_detail" placeholder="รายละเอียดภารกิจ"></textarea>
            <label>รูปหน้าปกภารกิจ:</label>
            <input type="file" name="mission_image" accept="image/*">

            <div style="display:flex; gap:10px;">
                <input type="number" name="exp_reward" placeholder="EXP ที่จะได้รับ" required>
                <input type="number" name="token_reward" placeholder="Token ที่จะได้รับ" required>
                <input type="number" name="daily_limit" placeholder="จำกัดเล่นต่อวัน (ครั้ง)" value="1">
            </div>

            <h3 style="margin-top:20px;">เพิ่มท่าออกกำลังกายในภารกิจนี้</h3>
            <div id="exercise-list">
                <div class="exercise-row">
                    <select name="exercise_id[]" required>
                        <option value="">-- เลือกท่าออกกำลังกาย --</option>
                        <?php foreach ($exercises_list as $ex): ?>
                            <option value="<?= $ex['exercise_id'] ?>"><?= $ex['exercise_name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="sets[]" placeholder="จำนวนเซ็ต" required min="1">
                    <button type="button" class="btn" onclick="this.parentElement.remove()" style="background:#444;">ลบ</button>
                </div>
            </div>

            <button type="button" class="btn" onclick="addExerciseRow()" style="background:#3b82f6; margin-bottom:20px;">+ เพิ่มท่า</button>
            <br>
            <button type="submit" name="save_mission" class="btn" style="background:#22c55e; width: 100%;">บันทึกภารกิจ</button>
        </form>
    </div>

    <script>
        function addExerciseRow() {
            const row = document.querySelector('.exercise-row').cloneNode(true);
            row.querySelector('input').value = ""; // เคลียร์ค่าเซ็ตเก่า
            document.getElementById('exercise-list').appendChild(row);
        }
        <?php if ($success_msg) echo "Swal.fire('สำเร็จ!', 'สร้างภารกิจพร้อมเรียงท่าสำเร็จ', 'success').then(()=>window.location='dashboard.php');"; ?>
    </script>
</body>

</html><?php
        if ($error_msg) {
            echo "Swal.fire('เกิดข้อผิดพลาด!', '" . addslashes($error_msg) . "', 'error');";
        }
        ?>