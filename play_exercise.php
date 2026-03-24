<?php
session_start();
include "config/config.php";

$is_guest = isset($_GET['guest']) ? true : false;
if (!$is_guest && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: guest_home.php");
    exit();
}
$ex_id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM exercises WHERE exercise_id=?");
$stmt->bind_param("i", $ex_id);
$stmt->execute();
$exercise = $stmt->get_result()->fetch_assoc();

if (!$exercise) {
    header("Location: guest_home.php");
    exit();
}

$steps = [];
$stmt2 = $conn->prepare("SELECT * FROM exercise_steps WHERE exercise_id=? ORDER BY step_number ASC");
$stmt2->bind_param("i", $ex_id);
$stmt2->execute();
$result = $stmt2->get_result();
while ($row = $result->fetch_assoc()) {
    $steps[] = $row;
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ฝึกซ้อมอิสระ: <?= htmlspecialchars($exercise['exercise_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/pose/pose.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #38bdf8;
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

        .info-text h1 {
            color: var(--primary);
            margin: 0 0 10px 0;
            font-size: 28px;
        }

        .badge-accuracy {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 50px;
            background: rgba(56, 189, 248, 0.15);
            color: var(--primary);
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
            box-shadow: 0 0 30px rgba(56, 189, 248, 0.2);
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

        .btn-group {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .btn-back {
            padding: 14px 30px;
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

        .btn-finish {
            padding: 14px 30px;
            border-radius: 12px;
            background: #22c55e;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
            box-shadow: 0 5px 15px rgba(34, 197, 94, 0.3);
        }

        .btn-finish:hover {
            background: #16a34a;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <div class="container">

        <div class="info-card">
            <div class="info-media">
                <?php
                $file = $exercise['exercise_video'] ? $exercise['exercise_video'] : $exercise['exercise_image'];
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!empty($file)):
                    if (in_array($ext, ['mp4', 'webm', 'mov'])): ?>
                        <video src="uploads/<?= htmlspecialchars($file) ?>" controls loop muted playsinline autoplay></video>
                    <?php else: ?>
                        <img src="uploads/<?= htmlspecialchars($file) ?>" alt="Exercise">
                    <?php endif;
                else: ?>
                    <div style="height:100%; display:flex; align-items:center; justify-content:center; color:#555; background:#222;">
                        <i class="fa-solid fa-image fa-3x"></i>
                    </div>
                <?php endif; ?>
            </div>

            <div class="info-text">
                <h1><?= htmlspecialchars($exercise['exercise_name']) ?> <span style="font-size:16px; color:#fff; opacity:0.5;">(Free Mode)</span></h1>
                <div class="badge-accuracy"><i class="fa-solid fa-crosshairs"></i> เกณฑ์ความแม่นยำ: <?= (int)$exercise['required_accuracy'] ?>%</div>

                <?php if (!empty($exercise['exercise_detail'])): ?>
                    <div class="desc-title"><i class="fa-solid fa-circle-info"></i> รายละเอียดท่า:</div>
                    <div class="desc-content"><?= htmlspecialchars($exercise['exercise_detail']) ?></div>
                <?php endif; ?>

                <?php if (!empty($exercise['how_to'])): ?>
                    <div class="desc-title"><i class="fa-solid fa-person-running"></i> วิธีปฏิบัติ:</div>
                    <div class="desc-content" style="color:#e2e8f0;"><?= htmlspecialchars($exercise['how_to']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <h2 style="text-align: left; margin-bottom: 15px;"><i class="fa-solid fa-video" style="color: var(--primary);"></i> เริ่มการฝึกซ้อม</h2>
        <div class="camera-box">
            <video class="input_video" autoplay playsinline></video>
            <canvas class="output_canvas"></canvas>
        </div>

        <div class="status-panel">
            <h3 id="statusText" style="color:var(--primary); margin-top:0;">กำลังเตรียมกล้องและ AI...</h3>
            <div style="font-size: 35px; font-weight: bold; margin-top: 10px;">
                จำนวนครั้ง: <span id="repVal" style="color:#22c55e;">0</span>
            </div>
        </div>

        <div class="btn-group">
            <button class="btn-back" onclick="window.location='<?= $is_guest ? 'guest_home.php' : 'home.php' ?>'">
                <i class="fa-solid fa-arrow-left"></i> ออกจากการฝึก
            </button>
            <button class="btn-finish" onclick="finishExercise()">
                <i class="fa-solid fa-save"></i> บันทึกและจบการฝึก
            </button>
        </div>
    </div>

    <script>
        const steps = <?= json_encode($steps) ?>;
        const REQUIRED_ACCURACY = <?= (int)$exercise['required_accuracy'] ?>;
        let currentStepIdx = 0;
        let repCount = 0;
        let similarityHistory = [];

        const videoElement = document.querySelector('.input_video');
        const canvasElement = document.querySelector('.output_canvas');
        const canvasCtx = canvasElement.getContext('2d');

        function calculateAngle(a, b, c) {
            if (!a || !b || !c) return 0;
            const radians = Math.atan2(c.y - b.y, c.x - b.x) - Math.atan2(a.y - b.y, a.x - b.x);
            let angle = Math.abs(radians * 180.0 / Math.PI);
            return angle > 180 ? 360 - angle : angle;
        }

        // ฟังก์ชันคำนวณที่อัปเกรดให้รองรับข้อมูล 12 จุด
        function calculateSimilarity(current, targetTemplateJson) {
            if (!targetTemplateJson) return 0;
            const target = JSON.parse(targetTemplateJson);

            const tA = target.angles || {
                le: 0,
                re: 0,
                lk: 0,
                rk: 0,
                ls: 0,
                rs: 0,
                lh: 0,
                rh: 0
            };

            const angleDiffs = [
                Math.abs(current.le - (tA.le || 0)),
                Math.abs(current.re - (tA.re || 0)),
                Math.abs(current.lk - (tA.lk || 0)),
                Math.abs(current.rk - (tA.rk || 0))
            ];

            // ถ้าระบบมีไหล่/สะโพกด้วย จะนำมาคำนวณเพิ่ม (สำหรับท่าที่บันทึกจากระบบใหม่)
            if (tA.ls !== undefined && tA.rs !== undefined && tA.lh !== undefined && tA.rh !== undefined) {
                angleDiffs.push(Math.abs(current.ls - tA.ls));
                angleDiffs.push(Math.abs(current.rs - tA.rs));
                angleDiffs.push(Math.abs(current.lh - tA.lh));
                angleDiffs.push(Math.abs(current.rh - tA.rh));
            }

            let angleScore = 100 - (angleDiffs.reduce((a, b) => a + b) / angleDiffs.length);
            return Math.round(Math.max(0, Math.min(100, angleScore)));
        }

        function finishExercise() {
            let avgSim = similarityHistory.length > 0 ? Math.round(similarityHistory.reduce((a, b) => a + b) / similarityHistory.length) : 0;
            if (<?= $is_guest ? 'true' : 'false' ?>) {
                window.location = 'guest_home.php?status=completed';
            } else {
                window.location = `complete_mission.php?exercise_id=<?= $ex_id ?>&accuracy=${avgSim}&reps=${repCount}`;
            }
        }

        const pose = new Pose({
            locateFile: file => `https://cdn.jsdelivr.net/npm/@mediapipe/pose/${file}`
        });
        pose.setOptions({
            modelComplexity: 1,
            smoothLandmarks: true,
            minDetectionConfidence: 0.5,
            minTrackingConfidence: 0.5
        });

        pose.onResults(results => {
            canvasCtx.clearRect(0, 0, canvasElement.width, canvasElement.height);
            canvasCtx.drawImage(results.image, 0, 0, canvasElement.width, canvasElement.height);

            if (results.poseLandmarks && steps.length > 0) {
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

                let sim = calculateSimilarity(currentData, steps[currentStepIdx].angle_template);

                if (sim >= REQUIRED_ACCURACY) {
                    similarityHistory.push(sim);
                    if (currentStepIdx < steps.length - 1) {
                        currentStepIdx++;
                        document.getElementById('statusText').innerText = `เยี่ยมมาก! ทำขั้นตอนที่ ${currentStepIdx + 1} ต่อเลย`;
                        document.getElementById('statusText').style.color = "var(--primary)";
                    } else {
                        repCount++;
                        currentStepIdx = 0;
                        document.getElementById('repVal').innerText = repCount;
                        document.getElementById('statusText').innerText = "นับ 1 ครั้ง! กลับไปเริ่มขั้นตอนแรกใหม่";
                        document.getElementById('statusText').style.color = "#22c55e";
                    }
                } else {
                    document.getElementById('statusText').innerText = `กำลังทำขั้นตอนที่ ${currentStepIdx + 1} (ความแม่นยำ: ${sim}%)`;
                    document.getElementById('statusText').style.color = "#fbbf24";
                }
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
    </script>
</body>

</html>