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

    $targetDir = "../uploads/exercises/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

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
<html lang="th" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขท่าออกกำลังกาย - GYMFITT</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/pose/pose.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#ef4444',
                        card: '#121212',
                        darker: '#0a0a0a'
                    },
                    fontFamily: {
                        sans: ['Poppins', 'sans-serif'],
                        orbitron: ['Orbitron', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        body {
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .swal2-container {
            z-index: 99999 !important;
        }

        .gymfitt-swal-popup {
            border-radius: 24px !important;
            padding: 20px !important;
        }

        /* Camera Custom Ratio */
        .camera-wrap video,
        .camera-wrap canvas {
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
            object-fit: cover;
            transform: scaleX(-1);
        }
    </style>
</head>

<body class="bg-gray-50 dark:bg-[#050505] text-gray-900 dark:text-white font-sans min-h-screen">

    <script>
        (function() {
            if (localStorage.getItem('gymfitt-theme') === 'light') document.documentElement.classList.remove('dark');
            else document.documentElement.classList.add('dark');
        })();
    </script>

    <!-- Background Effects -->
    <div class="fixed top-20 right-0 w-96 h-96 bg-red-600/10 blur-[100px] rounded-full pointer-events-none z-[-1] hidden dark:block"></div>
    <div class="fixed bottom-0 left-0 w-96 h-96 bg-red-900/10 blur-[100px] rounded-full pointer-events-none z-[-1] hidden dark:block"></div>

    <div class="max-w-7xl mx-auto px-4 py-8">

        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white drop-shadow-sm">
                <i class="fa-solid fa-pen-to-square text-blue-500 mr-2"></i> แก้ไขท่า: <?= htmlspecialchars($exercise['exercise_name']) ?>
            </h1>
            <a href="dashboard.php" class="bg-gray-200 dark:bg-zinc-800 hover:bg-gray-300 dark:hover:bg-zinc-700 text-gray-700 dark:text-white px-5 py-2.5 rounded-xl font-bold transition shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i> กลับแดชบอร์ด
            </a>
        </div>

        <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-2 gap-8">

            <!-- Left Column: Form Info -->
            <div class="bg-white dark:bg-card rounded-3xl p-6 md:p-8 border border-gray-200 dark:border-red-900/30 shadow-xl transition-colors">
                <h2 class="text-xl font-bold border-b border-gray-100 dark:border-zinc-800 pb-4 mb-6 flex items-center gap-2 text-primary">
                    <i class="fa-solid fa-pen-nib"></i> ข้อมูลท่า (Exercise Info)
                </h2>

                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-zinc-300 mb-1.5">ชื่อท่าออกกำลังกาย <span class="text-primary">*</span></label>
                        <input type="text" name="exercise_name" value="<?= htmlspecialchars($exercise['exercise_name']) ?>" required class="w-full bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:border-primary focus:ring-1 focus:ring-primary outline-none transition">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-zinc-300 mb-1.5">รายละเอียดของท่า</label>
                        <textarea name="exercise_detail" rows="2" class="w-full bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:border-primary focus:ring-1 focus:ring-primary outline-none transition"><?= htmlspecialchars($exercise['exercise_detail']) ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-zinc-300 mb-1.5">วิธีทำ (Step-by-step)</label>
                        <textarea name="how_to" rows="3" class="w-full bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:border-primary focus:ring-1 focus:ring-primary outline-none transition"><?= htmlspecialchars($exercise['how_to']) ?></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-zinc-300 mb-1.5">รูปภาพ (เปลี่ยนใหม่)</label>
                            <input type="file" name="ex_image" accept="image/*" class="w-full bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-white hover:file:bg-red-600 transition">
                            <?php if ($exercise['exercise_image']): ?>
                                <p class="text-xs text-blue-500 mt-2"><i class="fa-solid fa-image"></i> มีรูปภาพเดิมอยู่แล้ว</p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-zinc-300 mb-1.5">วิดีโอ (เปลี่ยนใหม่)</label>
                            <input type="file" name="ex_video" accept="video/*" class="w-full bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-white hover:file:bg-red-600 transition">
                            <?php if ($exercise['exercise_video']): ?>
                                <p class="text-xs text-blue-500 mt-2"><i class="fa-solid fa-video"></i> มีวิดีโอเดิมอยู่แล้ว</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-darker p-4 rounded-2xl border border-gray-200 dark:border-zinc-800 space-y-4">
                        <label class="block text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2"><i class="fa-solid fa-robot text-blue-500"></i> ข้อความโต้ตอบ AI</label>
                        <input type="text" name="msg_too_high" value="<?= htmlspecialchars($exercise['msg_too_high'] ?? '') ?>" placeholder="เมื่อสูง/เร็วไป (เช่น 'ย่อลงอีกนิด!')" class="w-full bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:border-blue-500 outline-none transition">
                        <input type="text" name="msg_too_low" value="<?= htmlspecialchars($exercise['msg_too_low'] ?? '') ?>" placeholder="เมื่อต่ำ/ช้าไป (เช่น 'ต่ำไปแล้ว ดันขึ้น!')" class="w-full bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:border-blue-500 outline-none transition">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-zinc-300 mb-1.5">เกณฑ์ความแม่นยำ AI (%)</label>
                        <select name="required_accuracy" class="w-full bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:border-primary outline-none transition">
                            <option value="70" <?= $exercise['required_accuracy'] == 70 ? 'selected' : '' ?>>70% (ระดับเริ่มต้น)</option>
                            <option value="80" <?= $exercise['required_accuracy'] == 80 ? 'selected' : '' ?>>80% (มาตรฐาน)</option>
                            <option value="90" <?= $exercise['required_accuracy'] == 90 ? 'selected' : '' ?>>90% (เข้มงวด)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Right Column: Camera & Steps -->
            <div class="bg-white dark:bg-card rounded-3xl p-6 md:p-8 border border-gray-200 dark:border-red-900/30 shadow-xl transition-colors flex flex-col">
                <h2 class="text-xl font-bold border-b border-gray-100 dark:border-zinc-800 pb-4 mb-6 flex items-center gap-2 text-primary">
                    <i class="fa-solid fa-camera-retro"></i> บันทึกเซ็นเซอร์ (Sensor Record)
                </h2>

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-zinc-300 mb-1.5">จำนวนขั้นตอนของท่านี้ (Steps)</label>
                    <select name="total_steps" id="total_steps" onchange="initSteps(false)" class="w-full bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:border-primary outline-none transition">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= ($total_existing_steps == $i) ? 'selected' : '' ?>><?= $i ?> ขั้นตอน</option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div id="cam-area" class="flex-col items-center flex-1">
                    <!-- Phone Mockup Camera -->
                    <div class="camera-wrap w-full max-w-[280px] aspect-[9/16] relative bg-black rounded-[2rem] overflow-hidden border-[6px] border-gray-800 dark:border-zinc-800 shadow-2xl mx-auto">
                        <video id="video" autoplay playsinline></video>
                        <canvas id="canvas"></canvas>
                    </div>

                    <!-- Steps Preview -->
                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-3 w-full mt-6" id="steps_preview"></div>

                    <!-- Action Buttons -->
                    <div class="w-full mt-auto pt-6">
                        <button type="button" id="btn_record" onclick="startCapturing()" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-3.5 rounded-xl shadow-md hover:shadow-lg transition transform active:scale-95 flex justify-center items-center gap-2 mb-3">
                            <i class="fa-solid fa-rotate-right"></i> อัดท่าทางใหม่ (Re-Record)
                        </button>

                        <button type="submit" name="update_exercise" id="btn_save" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3.5 rounded-xl shadow-md hover:shadow-lg transition transform active:scale-95 flex justify-center items-center gap-2">
                            <i class="fa-solid fa-save"></i> บันทึกการแก้ไข
                        </button>
                    </div>
                </div>
            </div>

        </form>
    </div>

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
                let bgClass = "bg-gray-100 dark:bg-zinc-900 border-gray-200 dark:border-zinc-700";
                let textClass = "text-gray-400 dark:text-zinc-500";

                if (loadExisting && existingStepsData[i - 1]) {
                    dataVal = existingStepsData[i - 1].angle_template;
                    statusIcon = '<i class="fa-solid fa-check-double text-green-500"></i>';
                    bgClass = "bg-green-50 dark:bg-green-900/20 border-green-500";
                }

                preview.innerHTML += `
                    <div class="${bgClass} border rounded-xl p-3 text-center transition-colors" id="step_box_${i}">
                        <b class="text-xs text-gray-500 dark:text-zinc-400">Step ${i}</b><br>
                        <span id="st_${i}" class="${textClass}">${statusIcon}</span>
                        <input type="hidden" name="angle_template_${i}" id="tpl_${i}" value='${dataVal}'>
                    </div>`;
            }
        }

        function startCapturing() {
            if (capIdx > totalSteps) capIdx = 1;
            const btn = document.getElementById('btn_record');
            btn.disabled = true;
            initSteps(false);

            function captureNext() {
                if (capIdx > totalSteps) {
                    finishCapturing();
                    return;
                }

                let countdown = 3;
                let box = document.getElementById('step_box_' + capIdx);
                box.classList.remove('bg-gray-100', 'dark:bg-zinc-900', 'border-gray-200', 'dark:border-zinc-700');
                box.classList.add('bg-red-50', 'dark:bg-primary/20', 'border-primary');

                let timer = setInterval(() => {
                    if (countdown > 0) {
                        btn.innerHTML = `<i class="fa-solid fa-clock"></i> Step ${capIdx}: เตรียมตัว... ${countdown}`;
                        countdown--;
                    } else {
                        clearInterval(timer);
                        btn.innerHTML = `<i class="fa-solid fa-video"></i> กำลังบันทึก Step ${capIdx}...`;
                        btn.classList.add('bg-red-700');

                        document.getElementById('tpl_' + capIdx).value = JSON.stringify(lastData);

                        box.classList.remove('bg-red-50', 'dark:bg-primary/20', 'border-primary');
                        box.classList.add('bg-green-50', 'dark:bg-green-900/20', 'border-green-500');
                        document.getElementById('st_' + capIdx).innerHTML = '<i class="fa-solid fa-check text-green-500"></i>';

                        capIdx++;
                        setTimeout(captureNext, 1000);
                    }
                }, 1000);
            }

            function finishCapturing() {
                btn.disabled = false;
                btn.classList.remove('bg-red-700');
                btn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> อัดท่าทางใหม่ (Re-Record)';
                Swal.fire({
                    title: 'บันทึกสำเร็จ!',
                    text: 'จับท่าทางใหม่ครบถ้วนแล้ว',
                    icon: 'success',
                    background: document.documentElement.classList.contains('dark') ? '#18181b' : '#fff',
                    color: document.documentElement.classList.contains('dark') ? '#fafafa' : '#18181b',
                    confirmButtonColor: '#10b981',
                    customClass: {
                        popup: 'gymfitt-swal-popup'
                    }
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
                background: document.documentElement.classList.contains('dark') ? '#18181b' : '#fff',
                color: document.documentElement.classList.contains('dark') ? '#fafafa' : '#18181b',
                confirmButtonColor: '#10b981',
                customClass: {
                    popup: 'gymfitt-swal-popup'
                }
            }).then(() => window.location = 'dashboard.php');
        <?php endif; ?>

        <?php if ($error_msg): ?>
            Swal.fire({
                title: 'ผิดพลาด!',
                text: '<?= addslashes($error_msg) ?>',
                icon: 'error',
                background: document.documentElement.classList.contains('dark') ? '#18181b' : '#fff',
                color: document.documentElement.classList.contains('dark') ? '#fafafa' : '#18181b',
                confirmButtonColor: '#ef4444',
                customClass: {
                    popup: 'gymfitt-swal-popup'
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>