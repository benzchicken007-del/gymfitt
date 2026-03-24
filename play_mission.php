<?php
session_start();
include "config/config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
if (!isset($_GET['mission_id'])) {
    header("Location: home.php");
    exit();
}

$mission_id = (int)$_GET['mission_id'];
$conn->set_charset("utf8mb4");

// ดึงข้อมูลภารกิจ
$stmt = $conn->prepare("SELECT * FROM missions WHERE mission_id=?");
$stmt->bind_param("i", $mission_id);
$stmt->execute();
$mission = $stmt->get_result()->fetch_assoc();

if (!$mission) {
    header("Location: home.php");
    exit();
}

// ดึงคิวท่าออกกำลังกายที่จัดเซ็ตไว้ (เพิ่มการดึง image, video, detail, how_to)
$mission_queue = [];
$q = $conn->query("SELECT me.sets, e.exercise_id, e.exercise_name, e.required_accuracy, e.msg_too_high, e.msg_too_low, e.exercise_image, e.exercise_video, e.exercise_detail, e.how_to 
                   FROM mission_exercises me JOIN exercises e ON me.exercise_id = e.exercise_id 
                   WHERE me.mission_id = $mission_id ORDER BY me.sequence_order ASC");

while ($ex = $q->fetch_assoc()) {
    $ex_id = $ex['exercise_id'];
    $steps = [];
    $q_steps = $conn->query("SELECT * FROM exercise_steps WHERE exercise_id = $ex_id ORDER BY step_number ASC");
    while ($st = $q_steps->fetch_assoc()) {
        $steps[] = $st;
    }
    $ex['steps'] = $steps;
    $mission_queue[] = $ex;
}

if (count($mission_queue) == 0) {
    echo "<script>alert('ภารกิจนี้ยังไม่ได้จัดเซ็ตท่าออกกำลังกาย!'); window.location='home.php';</script>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ภารกิจ: <?= htmlspecialchars($mission['mission_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/pose/pose.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #a855f7;
            --bg-dark: #0a0a0a;
            --card-bg: #141414;
            --border: rgba(255, 255, 255, 0.1);
        }

        body {
            background: var(--bg-dark);
            color: #fff;
            font-family: 'Poppins', sans-serif;
            text-align: center;
            margin: 0;
            padding-bottom: 50px;
        }

        .container {
            padding: 20px;
            max-width: 900px;
            margin: auto;
        }

        .header-mission {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background: var(--card-bg);
            padding: 15px 25px;
            border-radius: 20px;
            border: 1px solid var(--border);
        }

        .header-mission h1 {
            color: var(--primary);
            margin: 0;
            font-size: 24px;
        }

        .btn-back {
            padding: 10px 20px;
            border-radius: 12px;
            background: #333;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
        }

        .btn-back:hover {
            background: #ef4444;
        }

        /* --- Info Card Style --- */
        .info-card {
            background: var(--card-bg);
            border-radius: 24px;
            border: 1px solid var(--border);
            padding: 25px;
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap;
            gap: 25px;
            text-align: left;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .info-media {
            flex: 1 1 300px;
            border-radius: 16px;
            overflow: hidden;
            background: #000;
            border: 1px solid var(--border);
            aspect-ratio: 16/9;
        }

        .info-media video,
        .info-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .info-text {
            flex: 2 1 300px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .info-text h2 {
            color: #38bdf8;
            margin: 0 0 10px 0;
            font-size: 24px;
        }

        .badge-accuracy {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 50px;
            background: rgba(56, 189, 248, 0.15);
            color: #38bdf8;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 20px;
            width: fit-content;
        }

        .desc-title {
            color: #fbbf24;
            font-size: 14px;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .desc-content {
            font-size: 13px;
            opacity: 0.8;
            line-height: 1.6;
            margin-bottom: 15px;
            white-space: pre-wrap;
        }

        /* --- Camera Style --- */
        .camera-box {
            position: relative;
            width: 100%;
            aspect-ratio: 4/3;
            margin: 0 auto 20px;
            border-radius: 20px;
            overflow: hidden;
            background: #000;
            border: 3px solid var(--primary);
        }

        .input_video {
            display: none;
        }

        .output_canvas {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1);
            position: absolute;
            top: 0;
            left: 0;
        }

        .status-panel {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 20px;
            border: 1px solid var(--border);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-top: 15px;
        }

        .stat-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px 10px;
            border-radius: 15px;
        }

        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #fff;
        }

        .stat-label {
            font-size: 11px;
            opacity: 0.6;
            margin-top: 5px;
        }

        .progress-bar {
            height: 10px;
            background: #333;
            border-radius: 5px;
            margin-top: 15px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            width: 0%;
            transition: 0.3s;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header-mission">
            <h1><i class="fa-solid fa-flag-checkered"></i> <?= htmlspecialchars($mission['mission_name']) ?></h1>
            <button class="btn-back" onclick="window.location='home.php'"><i class="fa-solid fa-arrow-left"></i> ยอมแพ้</button>
        </div>

        <div class="info-card">
            <div class="info-media" id="info-media-container">
            </div>

            <div class="info-text">
                <h2 id="info-ex-name">กำลังโหลดข้อมูล...</h2>
                <div class="badge-accuracy"><i class="fa-solid fa-crosshairs"></i> เกณฑ์ความแม่นยำ: <span id="info-accuracy">0</span>%</div>

                <div class="desc-title"><i class="fa-solid fa-circle-info"></i> รายละเอียดท่า:</div>
                <div class="desc-content" id="info-detail">-</div>

                <div class="desc-title"><i class="fa-solid fa-person-running"></i> วิธีปฏิบัติ:</div>
                <div class="desc-content" style="color:#e2e8f0;" id="info-howto">-</div>
            </div>
        </div>

        <h3 style="text-align: left; margin-bottom: 15px; color: var(--primary);"><i class="fa-solid fa-video"></i> เริ่มการฝึกซ้อม</h3>
        <div class="camera-box">
            <video class="input_video" autoplay playsinline></video>
            <canvas class="output_canvas"></canvas>
        </div>

        <div class="status-panel">
            <div id="statusText" style="font-size:18px; font-weight:bold; color:#38bdf8;">กำลังเตรียมกล้อง...</div>
            <div class="progress-bar">
                <div class="progress-fill" id="progressBar"></div>
            </div>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value" id="repVal" style="color:#22c55e;">0 / 10</div>
                    <div class="stat-label">ครั้ง (Reps)</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value" id="setVal" style="color:#fbbf24;">1 / 1</div>
                    <div class="stat-label">เซ็ต (Sets)</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value" id="exVal" style="color:#a855f7;">1 / <?= count($mission_queue) ?></div>
                    <div class="stat-label">ท่าที่ (Exercise)</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const queue = <?= json_encode($mission_queue) ?>;
        const MAX_REPS_PER_SET = 10; // เปลี่ยนจำนวนครั้งต่อเซ็ตได้ที่นี่

        let qIdx = 0;
        let currentSet = 1;
        let currentStepIdx = 0;
        let repCount = 0;
        let similarityHistory = [];
        let missionCompleted = false;

        const videoElement = document.querySelector('.input_video');
        const canvasElement = document.querySelector('.output_canvas');
        const canvasCtx = canvasElement.getContext('2d');

        function updateUI() {
            let ex = queue[qIdx];

            // อัปเดตข้อมูลกล่อง Info Card
            document.getElementById('info-ex-name').innerHTML = ex.exercise_name + ' <span style="font-size:14px; color:#fff; opacity:0.5;">(ท่าที่ ' + (qIdx + 1) + ')</span>';
            document.getElementById('info-accuracy').innerText = ex.required_accuracy;
            document.getElementById('info-detail').innerText = ex.exercise_detail ? ex.exercise_detail : 'ไม่มีรายละเอียด';
            document.getElementById('info-howto').innerText = ex.how_to ? ex.how_to : 'ไม่มีวิธีปฏิบัติ';

            let mediaContainer = document.getElementById('info-media-container');
            let file = ex.exercise_video ? ex.exercise_video : ex.exercise_image;
            if (file) {
                let ext = file.split('.').pop().toLowerCase();
                if (['mp4', 'webm', 'mov'].includes(ext)) {
                    mediaContainer.innerHTML = `<video src="uploads/${file}" controls loop muted playsinline autoplay></video>`;
                } else {
                    mediaContainer.innerHTML = `<img src="uploads/${file}" alt="Exercise">`;
                }
            } else {
                mediaContainer.innerHTML = `<div style="height:100%; display:flex; align-items:center; justify-content:center; color:#555; background:#222;"><i class="fa-solid fa-image fa-3x"></i></div>`;
            }

            // อัปเดตข้อมูลสถานะด้านล่าง
            document.getElementById('setVal').innerText = `${currentSet} / ${ex.sets}`;
            document.getElementById('repVal').innerText = `${repCount} / ${MAX_REPS_PER_SET}`;
            document.getElementById('exVal').innerText = `${qIdx + 1} / ${queue.length}`;
            document.getElementById('progressBar').style.width = (repCount / MAX_REPS_PER_SET * 100) + '%';
        }

        function calculateAngle(a, b, c) {
            if (!a || !b || !c) return 0;
            const radians = Math.atan2(c.y - b.y, c.x - b.x) - Math.atan2(a.y - b.y, a.x - b.x);
            let angle = Math.abs(radians * 180.0 / Math.PI);
            return angle > 180 ? 360 - angle : angle;
        }

        function calculateSimilarity(current, targetTemplateJson) {
            if (!targetTemplateJson) return 0;
            const target = JSON.parse(targetTemplateJson);
            const tA = target.angles || {
                le: 0,
                re: 0,
                lk: 0,
                rk: 0
            };

            const angleDiffs = [
                Math.abs(current.le - (tA.le || 0)), Math.abs(current.re - (tA.re || 0)),
                Math.abs(current.lk - (tA.lk || 0)), Math.abs(current.rk - (tA.rk || 0))
            ];

            if (tA.ls !== undefined && tA.rs !== undefined && tA.lh !== undefined && tA.rh !== undefined) {
                angleDiffs.push(Math.abs(current.ls - tA.ls));
                angleDiffs.push(Math.abs(current.rs - tA.rs));
                angleDiffs.push(Math.abs(current.lh - tA.lh));
                angleDiffs.push(Math.abs(current.rh - tA.rh));
            }

            let angleScore = 100 - (angleDiffs.reduce((a, b) => a + b) / angleDiffs.length);
            return Math.round(Math.max(0, Math.min(100, angleScore)));
        }

        function finishMission() {
            if (missionCompleted) return;
            missionCompleted = true;
            let avgSim = similarityHistory.length > 0 ? Math.round(similarityHistory.reduce((a, b) => a + b) / similarityHistory.length) : 0;
            Swal.fire({
                title: 'ภารกิจสำเร็จ!',
                text: 'คุณแข็งแกร่งมาก ระบบกำลังบันทึกข้อมูล...',
                icon: 'success',
                background: '#141414',
                color: '#fff',
                showConfirmButton: false,
                timer: 2500
            }).then(() => {
                window.location = `complete_mission.php?mission_id=<?= $mission_id ?>&accuracy=${avgSim}`;
            });
        }

        const pose = new Pose({
            locateFile: file => `https://cdn.jsdelivr.net/npm/@mediapipe/pose/${file}`
        });
        pose.setOptions({
            modelComplexity: 1,
            smoothLandmarks: true,
            minDetectionConfidence: 0.5
        });

        pose.onResults(results => {
            if (missionCompleted) return;
            canvasCtx.clearRect(0, 0, canvasElement.width, canvasElement.height);
            canvasCtx.drawImage(results.image, 0, 0, canvasElement.width, canvasElement.height);

            let ex = queue[qIdx];
            if (results.poseLandmarks && ex.steps.length > 0) {
                drawConnectors(canvasCtx, results.poseLandmarks, POSE_CONNECTIONS, {
                    color: '#00f2ff',
                    lineWidth: 2
                });
                drawLandmarks(canvasCtx, results.poseLandmarks, {
                    color: '#ffffff',
                    fillColor: '#ef4444',
                    radius: 3
                });

                const lm = results.poseLandmarks;
                const currentData = {
                    le: calculateAngle(lm[11], lm[13], lm[15]),
                    re: calculateAngle(lm[12], lm[14], lm[16]),
                    ls: calculateAngle(lm[13], lm[11], lm[23]),
                    rs: calculateAngle(lm[14], lm[12], lm[24]),
                    lk: calculateAngle(lm[23], lm[25], lm[27]),
                    rk: calculateAngle(lm[24], lm[26], lm[28]),
                    lh: calculateAngle(lm[25], lm[23], lm[11]),
                    rh: calculateAngle(lm[26], lm[24], lm[12])
                };

                let sim = calculateSimilarity(currentData, ex.steps[currentStepIdx].angle_template);

                if (sim >= ex.required_accuracy) {
                    similarityHistory.push(sim);
                    if (currentStepIdx < ex.steps.length - 1) {
                        currentStepIdx++;
                        document.getElementById('statusText').innerText = `เยี่ยม! ต่อไปสเต็ป ${currentStepIdx + 1}`;
                        document.getElementById('statusText').style.color = "var(--primary)";
                    } else {
                        repCount++;
                        currentStepIdx = 0;
                        document.getElementById('statusText').innerText = "ได้ 1 ครั้ง!";
                        document.getElementById('statusText').style.color = "#22c55e";

                        if (repCount >= MAX_REPS_PER_SET) {
                            if (currentSet < ex.sets) {
                                currentSet++;
                                repCount = 0;
                                Swal.fire({
                                    title: 'ยอดเยี่ยม! ขึ้นเซ็ตใหม่',
                                    background: '#141414',
                                    color: '#fff',
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            } else {
                                if (qIdx < queue.length - 1) {
                                    qIdx++;
                                    currentSet = 1;
                                    repCount = 0;
                                    Swal.fire({
                                        title: 'เปลี่ยนท่าต่อไป!',
                                        text: queue[qIdx].exercise_name,
                                        icon: 'info',
                                        background: '#141414',
                                        color: '#fff',
                                        timer: 2500,
                                        showConfirmButton: false
                                    });
                                } else {
                                    finishMission();
                                }
                            }
                        }
                    }
                } else {
                    document.getElementById('statusText').innerText = `กำลังทำสเต็ป ${currentStepIdx + 1} (ความแม่นยำ: ${sim}%)`;
                    document.getElementById('statusText').style.color = "#fbbf24";
                }
                updateUI();
            }
        });

        new Camera(videoElement, {
            onFrame: async () => {
                await pose.send({
                    image: videoElement
                });
            },
            width: 640,
            height: 480
        }).start();

        // โหลดข้อมูลครั้งแรก
        updateUI();
    </script>
</body>

</html>