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

if (isset($_POST['save_exercise'])) {
    $name = $_POST['exercise_name'] ?? "";
    $detail = $_POST['exercise_detail'] ?? "";
    $how_to = $_POST['how_to'] ?? "";
    $msg_high = $_POST['msg_too_high'] ?? "";
    $msg_low = $_POST['msg_too_low'] ?? "";
    $req_acc = $_POST['required_accuracy'] ?? 70;
    $total_steps = $_POST['total_steps'] ?? 0;

    $targetDir = "../uploads/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    // อัปโหลดรูปภาพและวิดีโอ
    $ex_image = "";
    $ex_video = "";
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

    $stmt = $conn->prepare("INSERT INTO exercises (exercise_name, exercise_image, exercise_video, exercise_detail, how_to, msg_too_high, msg_too_low, required_accuracy) VALUES (?,?,?,?,?,?,?,?)");

    if ($stmt) {
        $stmt->bind_param("sssssssi", $name, $ex_image, $ex_video, $detail, $how_to, $msg_high, $msg_low, $req_acc);
        if ($stmt->execute()) {
            $ex_id = $stmt->insert_id;
            for ($i = 1; $i <= $total_steps; $i++) {
                $angle_template = $_POST["angle_template_$i"] ?? "";
                $stmt2 = $conn->prepare("INSERT INTO exercise_steps (exercise_id, step_number, angle_template) VALUES (?,?,?)");
                $stmt2->bind_param("iis", $ex_id, $i, $angle_template);
                $stmt2->execute();
            }
            $success_msg = true;
        } else {
            $error_msg = "Error: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างท่าออกกำลังกาย - GYMFITT</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/pose/pose.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js"></script>
    <style>
        :root {
            --primary: #ef4444;
            --primary-hover: #dc2626;
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
            text-shadow: 0 0 15px rgba(239, 68, 68, 0.3);
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
            font-family: 'Poppins', sans-serif;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(0, 0, 0, 0.4);
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.1);
        }

        /* Camera Box (16:9 Vertical Ratio) */
        .camera-container {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .camera-wrap {
            width: 100%;
            max-width: 320px;
            /* ขนาดความกว้างมือถือ */
            aspect-ratio: 9 / 16;
            /* 16:9 แนวตั้ง */
            position: relative;
            background: #000;
            border-radius: 20px;
            overflow: hidden;
            border: 2px solid var(--primary);
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.15);
        }

        video,
        canvas {
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
            object-fit: cover;
            /* ครอปส่วนที่เกินเพื่อให้เต็ม 16:9 พอดี */
            transform: scaleX(-1);
            /* Mirror */
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
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.2);
        }

        .btn-success {
            background: #10b981;
        }

        .btn-success:hover {
            background: #059669;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.2);
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

    <h1 class="header-title"><i class="fa-solid fa-person-walking"></i> เพิ่มท่าออกกำลังกายใหม่</h1>



    <form method="POST" enctype="multipart/form-data">
        <div class="main-container">

            <div class="card">
                <h2><i class="fa-solid fa-pen-nib"></i> ข้อมูลท่า (Exercise Info)</h2>

                <div class="input-group">
                    <label>ชื่อท่าออกกำลังกาย</label>
                    <input type="text" name="exercise_name" placeholder="เช่น Squat, Push-up" required>
                </div>

                <div class="input-group">
                    <label>รายละเอียดของท่า</label>
                    <textarea name="exercise_detail" rows="2" placeholder="อธิบายจุดเด่นของท่านี้"></textarea>
                </div>

                <div class="input-group">
                    <label>วิธีทำ (Step-by-step)</label>
                    <textarea name="how_to" rows="3" placeholder="1. ลง...&#10;2. ขึ้น..."></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="input-group">
                        <label>รูปภาพ (Image)</label>
                        <input type="file" name="ex_image" accept="image/*">
                    </div>
                    <div class="input-group">
                        <label>วิดีโอ (Video)</label>
                        <input type="file" name="ex_video" accept="video/*">
                    </div>
                </div>

                <div class="input-group">
                    <label>ข้อความโต้ตอบ AI</label>
                    <input type="text" name="msg_too_high" placeholder="เมื่อสูง/เร็วไป (เช่น 'ย่อลงอีกนิด!')">
                    <input type="text" name="msg_too_low" placeholder="เมื่อต่ำ/ช้าไป (เช่น 'ต่ำไปแล้ว ดันขึ้น!')" style="margin-top:10px;">
                </div>

                <div class="input-group">
                    <label>เกณฑ์ความแม่นยำ AI (%)</label>
                    <select name="required_accuracy">
                        <option value="70">70% (ระดับเริ่มต้น)</option>
                        <option value="80" selected>80% (มาตรฐาน)</option>
                        <option value="90">90% (เข้มงวด)</option>
                    </select>
                </div>
            </div>

            <div class="card">
                <h2><i class="fa-solid fa-camera-retro"></i> บันทึกเซ็นเซอร์ (Sensor Record)</h2>

                <div class="input-group">
                    <label>จำนวนขั้นตอนท่านี้ (Steps)</label>
                    <select name="total_steps" id="total_steps" onchange="initSteps()">
                        <option value="">-- เลือกจำนวนขั้นตอน --</option>
                        <?php for ($i = 1; $i <= 12; $i++) echo "<option value='$i'>$i ขั้นตอน</option>"; ?>
                    </select>
                </div>

                <div class="camera-container" id="cam-area" style="display:none;">
                    <div class="camera-wrap">
                        <video id="video" autoplay playsinline></video>
                        <canvas id="canvas"></canvas>
                    </div>

                    <div class="steps-grid" id="steps_preview"></div>

                    <div style="width: 100%; margin-top: 25px;">
                        <button type="button" class="btn" id="btn_record" onclick="startCapturing()" style="display:none;">
                            <i class="fa-solid fa-record-vinyl"></i> เริ่มบันทึกสเต็ปอัตโนมัติ
                        </button>
                        <button type="button" class="btn" id="btn_reset" onclick="location.reload()" style="display:none; background: #64748b;">
                            <i class="fa-solid fa-rotate-right"></i> เริ่มบันทึกใหม่
                        </button>
                        <button type="submit" name="save_exercise" class="btn btn-success" id="btn_save" style="display:none;">
                            <i class="fa-solid fa-cloud-arrow-up"></i> บันทึกท่าลงฐานข้อมูล
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </form>

    <button onclick="window.location='dashboard.php'" class="btn" style="max-width: 200px; margin: 30px auto; display: block;">
        <i class="fa-solid fa-arrow-left"></i> กลับ

        <script>
            let totalSteps = 0,
                capIdx = 1,
                lastData = {};

            function initSteps() {
                totalSteps = parseInt(document.getElementById('total_steps').value);
                if (totalSteps > 0) {
                    document.getElementById('cam-area').style.display = "flex";
                    document.getElementById('btn_record').style.display = "flex";
                    let preview = document.getElementById('steps_preview');
                    preview.innerHTML = "";
                    for (let i = 1; i <= totalSteps; i++) {
                        preview.innerHTML += `
                        <div class="mini-step" id="step_box_${i}">
                            <b>Step ${i}</b><br>
                            <span id="st_${i}"><i class="fa-solid fa-hourglass-half"></i></span>
                            <input type="hidden" name="angle_template_${i}" id="tpl_${i}">
                        </div>`;
                    }
                    startCamera();
                }
            }

            function startCapturing() {
                if (capIdx > totalSteps) return;
                const btn = document.getElementById('btn_record');
                btn.disabled = true;

                function captureNext() {
                    if (capIdx > totalSteps) {
                        finishCapturing();
                        return;
                    }

                    let countdown = 3;
                    document.getElementById('step_box_' + capIdx).classList.add('active');

                    let timer = setInterval(() => {
                        if (countdown > 0) {
                            btn.innerHTML = `<i class="fa-solid fa-clock"></i> Step ${capIdx}: เตรียมตัว... ${countdown}`;
                            countdown--;
                        } else {
                            clearInterval(timer);
                            // บันทึกข้อมูล
                            btn.innerHTML = `<i class="fa-solid fa-video"></i> กำลังบันทึก Step ${capIdx}...`;
                            btn.style.background = "#dc2626";

                            document.getElementById('tpl_' + capIdx).value = JSON.stringify(lastData);

                            // อัปเดต UI
                            let box = document.getElementById('step_box_' + capIdx);
                            box.classList.remove('active');
                            box.classList.add('done');
                            document.getElementById('st_' + capIdx).innerHTML = '<i class="fa-solid fa-check"></i>';

                            capIdx++;
                            setTimeout(captureNext, 1000); // เว้นระยะ 1 วินาทีก่อนเริ่มสเต็ปถัดไป
                        }
                    }, 1000);
                }

                function finishCapturing() {
                    btn.style.display = "none";
                    document.getElementById('btn_reset').style.display = "flex";
                    document.getElementById('btn_save').style.display = "flex";
                    Swal.fire({
                        title: 'บันทึกสำเร็จ!',
                        text: 'จับท่าทางครบถ้วนแล้ว กรุณากดปุ่มบันทึกลงฐานข้อมูล',
                        icon: 'success',
                        background: '#1e2235',
                        color: '#fff',
                        confirmButtonColor: '#10b981'
                    });
                }

                captureNext();
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
                    // ดึงขนาดความกว้าง/ยาว ของวิดีโอมาปรับให้ canvas ทับพอดี
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;

                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    // ctx.drawImage(results.image, 0, 0, canvas.width, canvas.height); // ปิดไม่วาดภาพซ้ำเพื่อความลื่น

                    if (results.poseLandmarks) {
                        drawConnectors(ctx, results.poseLandmarks, POSE_CONNECTIONS, {
                            color: '#00f2ff',
                            lineWidth: 4
                        });
                        drawLandmarks(ctx, results.poseLandmarks, {
                            color: '#ffffff',
                            fillColor: '#ef4444',
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

                // ตั้งค่าความละเอียดแนวตั้งให้ MediaPipe ขอจากกล้อง
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
                    title: 'สำเร็จ!',
                    text: 'เพิ่มท่าออกกำลังกายเรียบร้อย',
                    icon: 'success',
                    background: '#1e2235',
                    color: '#fff',
                    confirmButtonColor: '#10b981'
                }).then(() => window.location = 'dashboard.php');
            <?php endif; ?>
        </script>
</body>

</html>