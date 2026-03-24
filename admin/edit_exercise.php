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
$ex_id = intval($_GET['id']);

// ดึงข้อมูลท่าหลัก
$stmt = $conn->prepare("SELECT * FROM exercises WHERE exercise_id = ?");
$stmt->bind_param("i", $ex_id);
$stmt->execute();
$exercise = $stmt->get_result()->fetch_assoc();

if (!$exercise) {
    header("Location: dashboard.php");
    exit();
}

// ดึงข้อมูล Step เดิม
$stmt_steps = $conn->prepare("SELECT * FROM exercise_steps WHERE exercise_id = ? ORDER BY step_number ASC");
$stmt_steps->bind_param("i", $ex_id);
$stmt_steps->execute();
$existing_steps = $stmt_steps->get_result()->fetch_all(MYSQLI_ASSOC);
$total_existing_steps = count($existing_steps);

// 2. จัดการการอัปเดตข้อมูล
if (isset($_POST['update_exercise'])) {
    $name = $_POST['exercise_name'] ?? "";
    $detail = $_POST['exercise_detail'] ?? "";
    $how_to = $_POST['how_to'] ?? "";
    $msg_high = $_POST['msg_too_high'] ?? "";
    $msg_low = $_POST['msg_too_low'] ?? "";
    $req_acc = $_POST['required_accuracy'] ?? 70;
    $total_steps = $_POST['total_steps'] ?? 0;

    $targetDir = "../uploads/";
    $ex_image = $exercise['exercise_image'];
    $ex_video = $exercise['exercise_video'];

    // อัปโหลดรูป/วิดีโอใหม่ (ถ้ามี)
    if (!empty($_FILES["ex_image"]["name"])) {
        $ext = pathinfo($_FILES["ex_image"]["name"], PATHINFO_EXTENSION);
        $ex_image = time() . "_ex_img_" . rand(1000, 9999) . "." . $ext;
        move_uploaded_file($_FILES["ex_image"]["tmp_name"], $targetDir . $ex_image);
    }
    if (!empty($_FILES["ex_video"]["name"])) {
        $ext = pathinfo($_FILES["ex_video"]["name"], PATHINFO_EXTENSION);
        $ex_video = time() . "_ex_vid_" . rand(1000, 9999) . "." . $ext;
        move_uploaded_file($_FILES["ex_video"]["tmp_name"], $targetDir . $ex_video);
    }

    $update_sql = "UPDATE exercises SET exercise_name=?, exercise_image=?, exercise_video=?, exercise_detail=?, how_to=?, msg_too_high=?, msg_too_low=?, required_accuracy=? WHERE exercise_id=?";
    $stmt_upd = $conn->prepare($update_sql);

    if ($stmt_upd) {
        $stmt_upd->bind_param("sssssssii", $name, $ex_image, $ex_video, $detail, $how_to, $msg_high, $msg_low, $req_acc, $ex_id);
        if ($stmt_upd->execute()) {
            // ลบ step เดิมและบันทึกใหม่
            $conn->query("DELETE FROM exercise_steps WHERE exercise_id = $ex_id");
            for ($i = 1; $i <= $total_steps; $i++) {
                $angle_template = $_POST["angle_template_$i"] ?? "";
                $stmt2 = $conn->prepare("INSERT INTO exercise_steps (exercise_id, step_number, angle_template) VALUES (?,?,?)");
                $stmt2->bind_param("iis", $ex_id, $i, $angle_template);
                $stmt2->execute();
            }
            $success_msg = true;
        } else {
            $error_msg = "Error updating: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขท่าออกกำลังกาย - GYMFITT</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/pose/pose.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js"></script>

    <style>
        :root {
            --primary: #38bdf8;
            --primary-hover: #0284c7;
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
            text-shadow: 0 0 15px rgba(56, 189, 248, 0.3);
        }

        .main-container {
            max-width: 1200px;
            margin: auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
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
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(0, 0, 0, 0.4);
        }

        .camera-container {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .camera-wrap {
            width: 100%;
            max-width: 320px;
            aspect-ratio: 9 / 16;
            position: relative;
            background: #000;
            border-radius: 20px;
            overflow: hidden;
            border: 2px solid var(--primary);
            box-shadow: 0 0 30px rgba(56, 189, 248, 0.15);
        }

        video,
        canvas {
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
            object-fit: cover;
            transform: scaleX(-1);
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
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
        }

        .btn-success {
            background: #10b981;
        }

        .btn-success:hover {
            background: #059669;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            width: 100%;
            margin-top: 20px;
        }

        .mini-step {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-glass);
            border-radius: 10px;
            padding: 10px;
            text-align: center;
            font-size: 12px;
            transition: 0.3s;
        }

        .mini-step.active {
            border-color: var(--primary);
            color: var(--primary);
        }

        .mini-step.done {
            border-color: #10b981;
            color: #10b981;
            background: rgba(16, 185, 129, 0.1);
        }
    </style>
</head>

<body>

    <h1 class="header-title"><i class="fa-solid fa-pen-to-square"></i> แก้ไขท่า: <?= htmlspecialchars($exercise['exercise_name']) ?></h1>

    <form method="POST" enctype="multipart/form-data">
        <div class="main-container">

            <div class="card">
                <h2><i class="fa-solid fa-pen-nib"></i> ข้อมูลท่า (Exercise Info)</h2>
                <div class="input-group">
                    <label>ชื่อท่าออกกำลังกาย</label>
                    <input type="text" name="exercise_name" value="<?= htmlspecialchars($exercise['exercise_name']) ?>" required>
                </div>
                <div class="input-group">
                    <label>รายละเอียดของท่า</label>
                    <textarea name="exercise_detail" rows="2"><?= htmlspecialchars($exercise['exercise_detail']) ?></textarea>
                </div>
                <div class="input-group">
                    <label>วิธีทำ (Step-by-step)</label>
                    <textarea name="how_to" rows="3"><?= htmlspecialchars($exercise['how_to']) ?></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="input-group">
                        <label>รูปภาพ (เปลี่ยนใหม่)</label>
                        <input type="file" name="ex_image" accept="image/*">
                    </div>
                    <div class="input-group">
                        <label>วิดีโอ (เปลี่ยนใหม่)</label>
                        <input type="file" name="ex_video" accept="video/*">
                    </div>
                </div>
                <div class="input-group">
                    <label>ข้อความโต้ตอบ AI</label>
                    <input type="text" name="msg_too_high" value="<?= htmlspecialchars($exercise['msg_too_high'] ?? '') ?>" placeholder="เมื่อสูง/เร็วไป">
                    <input type="text" name="msg_too_low" value="<?= htmlspecialchars($exercise['msg_too_low'] ?? '') ?>" placeholder="เมื่อต่ำ/ช้าไป" style="margin-top:10px;">
                </div>
                <div class="input-group">
                    <label>เกณฑ์ความแม่นยำ AI (%)</label>
                    <select name="required_accuracy">
                        <option value="70" <?= $exercise['required_accuracy'] == 70 ? 'selected' : '' ?>>70% (ระดับเริ่มต้น)</option>
                        <option value="80" <?= $exercise['required_accuracy'] == 80 ? 'selected' : '' ?>>80% (มาตรฐาน)</option>
                        <option value="90" <?= $exercise['required_accuracy'] == 90 ? 'selected' : '' ?>>90% (เข้มงวด)</option>
                    </select>
                </div>
            </div>

            <div class="card">
                <h2><i class="fa-solid fa-camera-retro"></i> บันทึกเซ็นเซอร์ (Sensor Record)</h2>
                <div class="input-group">
                    <label>จำนวนขั้นตอนของท่านี้</label>
                    <select name="total_steps" id="total_steps" onchange="initSteps(false)">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= ($total_existing_steps == $i) ? 'selected' : '' ?>><?= $i ?> ขั้นตอน</option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="camera-container" id="cam-area">
                    <div class="camera-wrap">
                        <video id="video" autoplay playsinline></video>
                        <canvas id="canvas"></canvas>
                    </div>
                    <div class="steps-grid" id="steps_preview"></div>

                    <div style="width: 100%; margin-top: 25px;">
                        <button type="button" class="btn" id="btn_record" onclick="startCapturing()" style="margin-bottom: 10px; background: #fbbf24; color: black;">
                            <i class="fa-solid fa-rotate-right"></i> อัดท่าทางใหม่ (Re-Record)
                        </button>
                        <button type="submit" name="update_exercise" class="btn btn-success" id="btn_save">
                            <i class="fa-solid fa-save"></i> บันทึกการแก้ไข
                        </button>
                        <a href="dashboard.php" class="btn" style="background: rgba(255,255,255,0.1); margin-top: 10px;">ยกเลิก</a>
                    </div>
                </div>
            </div>

        </div>
    </form>

    <script>
        let totalSteps = 0,
            capIdx = 1,
            lastData = {};
        const existingStepsData = <?= json_encode($existing_steps) ?>;

        window.onload = () => {
            initSteps(true);
            startCamera();
        };

        function initSteps(loadExisting = false) {
            totalSteps = parseInt(document.getElementById('total_steps').value);
            let preview = document.getElementById('steps_preview');
            preview.innerHTML = "";
            for (let i = 1; i <= totalSteps; i++) {
                let dataVal = "";
                let statusIcon = '<i class="fa-solid fa-hourglass-half"></i>';
                let cssClass = "";

                if (loadExisting && existingStepsData[i - 1]) {
                    dataVal = existingStepsData[i - 1].angle_template;
                    statusIcon = '<i class="fa-solid fa-check-double"></i>';
                    cssClass = "done";
                }

                preview.innerHTML += `
                    <div class="mini-step ${cssClass}" id="step_box_${i}">
                        <b>Step ${i}</b><br>
                        <span id="st_${i}">${statusIcon}</span>
                        <input type="hidden" name="angle_template_${i}" id="tpl_${i}" value='${dataVal}'>
                    </div>`;
            }
        }

        function startCapturing() {
            capIdx = 1; // Reset
            initSteps(false); // Clear old data
            document.getElementById('btn_record').disabled = true;
            document.getElementById('btn_record').innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังบันทึก...';

            let timer = setInterval(() => {
                document.getElementById('tpl_' + capIdx).value = JSON.stringify(lastData);
                let box = document.getElementById('step_box_' + capIdx);
                box.classList.add('done');
                document.getElementById('st_' + capIdx).innerHTML = '<i class="fa-solid fa-check"></i>';

                capIdx++;

                if (capIdx > totalSteps) {
                    clearInterval(timer);
                    document.getElementById('btn_record').innerHTML = '<i class="fa-solid fa-rotate-right"></i> อัดท่าทางใหม่';
                    document.getElementById('btn_record').disabled = false;
                    Swal.fire({
                        title: 'สำเร็จ!',
                        text: 'อัดท่าทางใหม่ครบถ้วน',
                        icon: 'success',
                        background: '#1e2235',
                        color: '#fff'
                    });
                } else {
                    document.getElementById('step_box_' + capIdx).classList.add('active');
                }
            }, 2500);

            document.getElementById('step_box_' + capIdx).classList.add('active');
        }

        function startCamera() {
            const video = document.getElementById('video');
            const canvas = document.getElementById('canvas');
            const ctx = canvas.getContext('2d');

            const pose = new Pose({
                locateFile: file => `https://cdn.jsdelivr.net/npm/@mediapipe/pose/${file}`
            });
            pose.setOptions({
                modelComplexity: 1,
                smoothLandmarks: true,
                minDetectionConfidence: 0.6
            });

            pose.onResults(results => {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                if (results.poseLandmarks) {
                    drawConnectors(ctx, results.poseLandmarks, POSE_CONNECTIONS, {
                        color: '#00f2ff',
                        lineWidth: 4
                    });
                    drawLandmarks(ctx, results.poseLandmarks, {
                        color: '#ffffff',
                        fillColor: '#38bdf8',
                        radius: 4
                    });

                    const lm = results.poseLandmarks;
                    lastData = {
                        angles: {
                            le: calc(lm[11], lm[13], lm[15]),
                            re: calc(lm[12], lm[14], lm[16]),
                            lk: calc(lm[23], lm[25], lm[27]),
                            rk: calc(lm[24], lm[26], lm[28])
                        },
                        heights: {
                            lw_y: (lm[15].y - lm[11].y).toFixed(3),
                            rw_y: (lm[16].y - lm[12].y).toFixed(3)
                        }
                    };
                }
            });
            new Camera(video, {
                onFrame: async () => {
                    await pose.send({
                        image: video
                    });
                },
                width: 720,
                height: 1280
            }).start();
        }

        function calc(a, b, c) {
            let r = Math.atan2(c.y - b.y, c.x - b.x) - Math.atan2(a.y - b.y, a.x - b.x);
            let ang = Math.abs(r * 180.0 / Math.PI);
            return ang > 180 ? 360 - ang : ang;
        }

        <?php if ($success_msg): ?>
            Swal.fire({
                    title: 'อัปเดตสำเร็จ!',
                    icon: 'success',
                    background: '#1e2235',
                    color: '#fff',
                    confirmButtonColor: '#10b981'
                })
                .then(() => window.location = 'dashboard.php');
        <?php endif; ?>
    </script>
</body>

</html>