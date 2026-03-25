<?php
session_start();
require_once 'config/config.php';

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

// ดึงข้อมูลภารกิจหลัก
$stmt = $conn->prepare("SELECT * FROM missions WHERE mission_id=?");
$stmt->bind_param("i", $mission_id);
$stmt->execute();
$mission = $stmt->get_result()->fetch_assoc();

if (!$mission) {
    header("Location: home.php");
    exit();
}

// ดึงข้อมูลคิวท่าออกกำลังกายในภารกิจนี้
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
    <title>ภารกิจ: <?php echo htmlspecialchars($mission['mission_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/pose/pose.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #0f172a;
            color: white;
            margin: 0;
            overflow: hidden;
            /* ป้องกัน scroll bar รบกวนหน้าจอหลัก */
        }

        .parent {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            grid-template-rows: repeat(6, 1fr);
            grid-column-gap: 0px;
            grid-row-gap: 0px;
            height: 100vh;
            padding: 15px;
            box-sizing: border-box;
        }

        .grid-item {
            background: rgba(30, 41, 59, 0.7);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* 1: รูปท่าออกกำลังกาย */
        .div1 {
            grid-area: 1 / 1 / 4 / 3;
        }

        /* 2: วิดีโอท่าออกกำลังกาย */
        .div2 {
            grid-area: 4 / 1 / 7 / 3;
        }

        /* 3: ระบบตัวจับเเสดงตรงนี้ */
        .div3 {
            grid-area: 1 / 3 / 7 / 5;
            position: relative;
        }

        /* 4: รายละเอียดท่าออกกำลังกาย */
        .div4 {
            grid-area: 1 / 5 / 4 / 7;
            padding: 15px;
        }

        /* 5: ข้อมูลภารกิจ */
        .div5 {
            grid-area: 4 / 5 / 6 / 7;
            padding: 15px;
        }

        /* 6: ปุ่ม ย้อนกับ เเเละ ปุ่มบันทึก */
        .div6 {
            grid-area: 6 / 5 / 7 / 7;
            display: flex;
            gap: 10px;
            padding: 10px;
            align-items: center;
            justify-content: center;
        }

        video.demo-video,
        img.demo-img,
        canvas.output_canvas {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .output_canvas {
            transform: scaleX(-1);
            /* กลับด้านซ้ายขวาให้เหมือนกระจก */
        }

        .count-overlay {
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.7);
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: bold;
            color: #38bdf8;
            border: 2px solid #38bdf8;
            z-index: 10;
            text-align: center;
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .count-val {
            font-size: 2rem;
            color: #fff;
        }

        .count-label {
            font-size: 0.8rem;
            color: #9ca3af;
            text-transform: uppercase;
        }
    </style>
</head>

<body>

    <div class="parent">
        <div class="grid-item div1">
            <div class="bg-gray-800 p-2 text-xs font-bold uppercase tracking-wider text-gray-400 flex justify-between">
                <span>ตัวอย่างท่าปัจจุบัน</span>
                <span id="qIdxDisplay" class="text-primary font-bold">ท่าที่ 1</span>
            </div>
            <div id="demoImgContainer" class="w-full h-full flex items-center justify-center p-2">
            </div>
        </div>

        <div class="grid-item div2">
            <div class="bg-gray-800 p-2 text-xs font-bold uppercase tracking-wider text-gray-400">วิดีโอสาธิต</div>
            <div id="demoVideoContainer" class="w-full h-full flex flex-col items-center justify-center bg-gray-900">
            </div>
        </div>

        <div class="grid-item div3 bg-black">
            <div class="count-overlay">
                <div>
                    <div class="count-label">ท่า (Ex)</div>
                    <div id="uiEx" class="count-val text-primary">1/1</div>
                </div>
                <div class="w-px h-10 bg-gray-600"></div>
                <div>
                    <div class="count-label">เซ็ต (Set)</div>
                    <div id="uiSet" class="count-val text-yellow-400">1/1</div>
                </div>
                <div class="w-px h-10 bg-gray-600"></div>
                <div>
                    <div class="count-label">ครั้ง (Rep)</div>
                    <div id="uiRep" class="count-val text-green-400">0/10</div>
                </div>
            </div>

            <video class="input_video" style="display:none;" autoplay playsinline></video>
            <canvas class="output_canvas"></canvas>

            <div id="statusText" class="absolute bottom-10 left-1/2 transform -translate-x-1/2 bg-gray-800/80 px-6 py-3 rounded-full text-white font-bold text-lg text-center w-max shadow-lg border border-gray-600 z-10 transition-colors">
                กำลังเตรียมกล้องและ AI...
            </div>
        </div>

        <div class="grid-item div4 flex flex-col">
            <h2 id="exName" class="text-2xl font-bold text-blue-400 mb-2">กำลังโหลดท่า...</h2>

            <div class="text-sm text-gray-300 mb-2 overflow-y-auto pr-2 flex-1">
                <p class="font-semibold text-yellow-500 mb-1">รายละเอียดท่า:</p>
                <p id="exDetail" class="mb-3 whitespace-pre-wrap text-xs text-gray-400">-</p>

                <p class="font-semibold text-white mb-1">วิธีทำ:</p>
                <p id="exHowTo" class="whitespace-pre-wrap text-sm text-gray-200">-</p>

                <div id="feedbackContainer" class="mt-4 bg-gray-800/80 p-3 rounded-xl border border-gray-700/50 hidden">
                    <p class="font-bold text-red-400 mb-2">⚠️ คำแนะนำจัดท่าทาง:</p>
                    <div class="space-y-2 text-xs">
                        <p id="msgTooHigh" class="text-gray-300 leading-relaxed hidden">
                            <span class="bg-red-900/50 text-red-300 px-2 py-1 rounded mr-1 font-bold">สูงไป!</span>
                            <span id="textTooHigh"></span>
                        </p>
                        <p id="msgTooLow" class="text-gray-300 leading-relaxed hidden">
                            <span class="bg-orange-900/50 text-orange-300 px-2 py-1 rounded mr-1 font-bold">ต่ำไป!</span>
                            <span id="textTooLow"></span>
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-auto pt-3 border-t border-gray-700 flex justify-between items-center">
                <span class="text-sm text-gray-400">เกณฑ์ความแม่นยำ: </span>
                <span class="text-green-400 font-bold text-lg"><span id="reqAcc">0</span>%</span>
            </div>
        </div>

        <div class="grid-item div5 flex flex-col">
            <div class="border-b border-gray-700 pb-2 mb-2 shrink-0">
                <h3 class="font-bold text-yellow-500 text-lg flex items-center gap-2">
                    <i class="fa-solid fa-flag-checkered"></i> รายละเอียดภารกิจ
                </h3>
            </div>
            <div class="flex-1 overflow-y-auto space-y-2 pr-2">
                <div class="text-white font-bold text-lg leading-tight"><?php echo htmlspecialchars($mission['mission_name']); ?></div>

                <!-- ป้ายรางวัลแบบย่อขนาด -->
                <div class="flex flex-wrap gap-2">
                    <span class="bg-blue-900/40 border border-blue-700/50 text-blue-400 text-[10px] px-2 py-0.5 rounded font-bold">
                        +<?php echo $mission['exp_reward']; ?> XP
                    </span>
                    <span class="bg-yellow-900/40 border border-yellow-700/50 text-yellow-400 text-[10px] px-2 py-0.5 rounded font-bold">
                        <i class="fa-solid fa-coins"></i> <?php echo $mission['token_reward']; ?>
                    </span>
                </div>

                <p class="text-xs text-gray-400 line-clamp-2"><?php echo htmlspecialchars($mission['mission_detail']); ?></p>

                <div class="pt-1">
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-gray-400">ความคืบหน้ารวม</span>
                        <span id="progressTextOverall" class="text-primary font-bold">0%</span>
                    </div>
                    <div class="bg-gray-900 rounded-full h-2.5 border border-gray-700 overflow-hidden relative">
                        <div id="missionProgressOverall" class="bg-primary h-full transition-all duration-300 shadow-[0_0_10px_rgba(220,38,38,0.5)]" style="width: 0%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid-item div6">
            <button onclick="confirmGiveUp()" class="flex-1 py-4 bg-gray-700 hover:bg-gray-600 rounded-xl font-bold transition duration-200 border border-gray-500 text-white">
                <i class="fa-solid fa-flag mr-2"></i> ยอมแพ้
            </button>
            <button onclick="skipExercise()" class="flex-1 py-4 bg-yellow-600 hover:bg-yellow-500 rounded-xl font-bold transition duration-200 shadow-lg shadow-yellow-900/40 border border-yellow-500/50 text-white">
                ข้ามท่านี้ <i class="fa-solid fa-forward ml-1"></i>
            </button>
        </div>
    </div>

    <script>
        // ข้อมูลคิวภารกิจจาก PHP
        const queue = <?php echo json_encode($mission_queue); ?>;
        const MAX_REPS_PER_SET = 10; // เปลี่ยนจำนวนครั้งต่อเซ็ตที่ต้องทำได้ที่นี่
        const missionId = <?php echo $mission_id; ?>;

        // ตัวแปรควบคุมสถานะ
        let qIdx = 0;
        let currentSet = 1;
        let currentStepIdx = 0;
        let repCount = 0;
        let similarityHistory = [];
        let missionCompleted = false;

        // การตั้งค่า DOM
        const videoElement = document.querySelector('.input_video');
        const canvasElement = document.querySelector('.output_canvas');
        const canvasCtx = canvasElement.getContext('2d');
        const statusText = document.getElementById('statusText');

        // ฟังก์ชันอัปเดตหน้าจอเมื่อเปลี่ยนท่า
        function updateUI() {
            if (qIdx >= queue.length) return;
            let ex = queue[qIdx];

            // อัปเดตข้อมูลรายละเอียดท่า (div4)
            document.getElementById('exName').innerText = ex.exercise_name;
            document.getElementById('exDetail').innerText = ex.exercise_detail ? ex.exercise_detail : 'ไม่มีรายละเอียด';
            document.getElementById('exHowTo').innerText = ex.how_to ? ex.how_to : 'ไม่มีวิธีปฏิบัติ';
            document.getElementById('reqAcc').innerText = ex.required_accuracy;

            // โหลดข้อความแนะนำ (สูงไป/ต่ำไป)
            document.getElementById('textTooHigh').innerText = ex.msg_too_high ? ex.msg_too_high : '';
            document.getElementById('textTooLow').innerText = ex.msg_too_low ? ex.msg_too_low : '';

            // อัปเดตสื่อสาธิต (div1, div2)
            let imgContainer = document.getElementById('demoImgContainer');
            let vidContainer = document.getElementById('demoVideoContainer');

            let file = ex.exercise_video ? ex.exercise_video : ex.exercise_image;
            if (file) {
                let filename = file.split('/').pop().split('\\').pop();
                let ext = filename.split('.').pop().toLowerCase();

                // อัปเดตวิดีโอ (ถ้ามี)
                if (['mp4', 'webm', 'mov'].includes(ext)) {
                    vidContainer.innerHTML = `
                <video class="demo-video" controls loop muted playsinline autoplay>
                    <source src="uploads/exercises/${filename}" type="video/${ext}">
                    <source src="uploads/${filename}" type="video/${ext}">
                </video>`;

                    // เอารูปไปใส่ที่ div1 แทน (ถ้าระบบมีรูปประกอบแยกก็ดึงมาใส่)
                    let imgFile = ex.exercise_image ? ex.exercise_image.split('/').pop().split('\\').pop() : filename;
                    imgContainer.innerHTML = `<img src="uploads/exercises/${imgFile}" onerror="this.src='uploads/${imgFile}'; this.onerror=null; this.src='assets/images/no-image.png'" class="demo-img object-contain" alt="Exercise">`;
                } else {
                    // ถ้าเป็นรูปอย่างเดียว ใส่รูปทั้งสองที่ (หรือใส่คลิป placeholder)
                    imgContainer.innerHTML = `<img src="uploads/exercises/${filename}" onerror="this.src='uploads/${filename}'; this.onerror=null; this.src='assets/images/no-image.png'" class="demo-img object-contain" alt="Exercise">`;
                    vidContainer.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mb-2 opacity-50 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg><span class="text-gray-500 italic">ไม่มีวิดีโอสาธิต</span>`;
                }
            } else {
                imgContainer.innerHTML = '<span class="text-gray-500 italic">ไม่มีรูปตัวอย่าง</span>';
                vidContainer.innerHTML = '<span class="text-gray-500 italic">ไม่มีวิดีโอสาธิต</span>';
            }

            // อัปเดตตัวเลขการนับ (div3 overlay)
            document.getElementById('uiEx').innerText = `${qIdx + 1}/${queue.length}`;
            document.getElementById('uiSet').innerText = `${currentSet}/${ex.sets}`;
            document.getElementById('uiRep').innerText = `${repCount}/${MAX_REPS_PER_SET}`;
            document.getElementById('qIdxDisplay').innerText = `ท่าที่ ${qIdx + 1}/${queue.length}`;

            // อัปเดต Progress รวม (div5)
            let totalExercises = queue.length;
            let progressOverall = Math.min((qIdx / totalExercises) * 100, 100);
            document.getElementById('missionProgressOverall').style.width = progressOverall + '%';
            document.getElementById('progressTextOverall').innerText = Math.round(progressOverall) + '%';

            // รีเซ็ตการแสดงผล Msg
            document.getElementById('feedbackContainer').classList.add('hidden');
        }

        // ฟังก์ชันคำนวณมุม
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

            // เติมหลอด Progress ให้เต็ม 100%
            document.getElementById('missionProgressOverall').style.width = '100%';
            document.getElementById('progressTextOverall').innerText = '100%';

            Swal.fire({
                title: 'กำลังบันทึกข้อมูล...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // ส่งข้อมูลให้หลังบ้านเหมือนโหมดปกติ
            const formData = new FormData();
            formData.append('mission_id', missionId);
            formData.append('passed', 'true');
            formData.append('accuracy', avgSim);

            fetch('actions/complete_mission.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success || data.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: data.title || 'ภารกิจสำเร็จ! 🎉',
                            text: data.message || `ยอดเยี่ยมมาก! ได้รับค่าประสบการณ์และความแม่นยำเฉลี่ย ${avgSim}%`,
                            background: '#1e293b',
                            color: '#fff',
                            confirmButtonColor: '#10b981'
                        }).then(() => {
                            window.location.href = data.redirect || 'home.php';
                        });
                    } else {
                        throw new Error(data.message || 'บันทึกไม่สำเร็จ');
                    }
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    // Fallback
                    window.location.href = `actions/complete_mission.php?mission_id=${missionId}&passed=true&accuracy=${avgSim}`;
                });
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
            if (missionCompleted) return;

            canvasCtx.save();
            canvasCtx.clearRect(0, 0, canvasElement.width, canvasElement.height);
            canvasCtx.drawImage(results.image, 0, 0, canvasElement.width, canvasElement.height);

            let ex = queue[qIdx];
            if (results.poseLandmarks && ex.steps.length > 0) {
                drawConnectors(canvasCtx, results.poseLandmarks, POSE_CONNECTIONS, {
                    color: '#38bdf8',
                    lineWidth: 4
                });
                drawLandmarks(canvasCtx, results.poseLandmarks, {
                    color: '#ffffff',
                    fillColor: '#38bdf8',
                    lineWidth: 2,
                    radius: 4
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

                // ---------------------------------------------------------
                // จัดการข้อความคำแนะนำท่าทาง (สูงไป/ต่ำไป)
                // ---------------------------------------------------------
                const feedbackContainer = document.getElementById('feedbackContainer');
                const msgTooHigh = document.getElementById('msgTooHigh');
                const msgTooLow = document.getElementById('msgTooLow');
                const txtHigh = document.getElementById('textTooHigh').innerText.trim();
                const txtLow = document.getElementById('textTooLow').innerText.trim();

                if (txtHigh !== '' || txtLow !== '') {
                    if (sim >= ex.required_accuracy) {
                        feedbackContainer.classList.add('hidden');
                        msgTooHigh.classList.add('hidden');
                        msgTooLow.classList.add('hidden');
                    } else {
                        feedbackContainer.classList.remove('hidden');

                        let tA = {
                            le: 0,
                            re: 0,
                            lk: 0,
                            rk: 0
                        };
                        if (ex.steps[currentStepIdx].angle_template) {
                            try {
                                const parsed = JSON.parse(ex.steps[currentStepIdx].angle_template);
                                if (parsed.angles) tA = parsed.angles;
                            } catch (e) {}
                        }

                        let totalCurrent = currentData.le + currentData.re + currentData.lk + currentData.rk;
                        let totalTarget = (tA.le || 0) + (tA.re || 0) + (tA.lk || 0) + (tA.rk || 0);

                        if (totalCurrent > totalTarget) {
                            if (txtHigh !== '') {
                                msgTooHigh.classList.remove('hidden');
                                msgTooLow.classList.add('hidden');
                            } else if (txtLow !== '') {
                                msgTooLow.classList.remove('hidden');
                            }
                        } else {
                            if (txtLow !== '') {
                                msgTooLow.classList.remove('hidden');
                                msgTooHigh.classList.add('hidden');
                            } else if (txtHigh !== '') {
                                msgTooHigh.classList.remove('hidden');
                            }
                        }
                    }
                }

                // ---------------------------------------------------------
                // จัดการระบบนับครั้ง เซ็ต และเปลี่ยนท่า
                // ---------------------------------------------------------
                if (sim >= ex.required_accuracy) {
                    similarityHistory.push(sim);

                    if (currentStepIdx < ex.steps.length - 1) {
                        currentStepIdx++;
                        statusText.innerText = `เยี่ยมมาก! ทำขั้นตอนที่ ${currentStepIdx + 1} ต่อเลย`;
                        statusText.className = "absolute bottom-10 left-1/2 transform -translate-x-1/2 bg-green-600/90 px-6 py-3 rounded-full text-white font-bold text-lg text-center w-max shadow-lg border border-green-400 z-10 transition-colors";
                    } else {
                        repCount++;
                        currentStepIdx = 0;
                        statusText.innerText = "นับ 1 ครั้ง! กลับไปเริ่มขั้นตอนแรกใหม่";
                        statusText.className = "absolute bottom-10 left-1/2 transform -translate-x-1/2 bg-blue-600/90 px-6 py-3 rounded-full text-white font-bold text-lg text-center w-max shadow-lg border border-blue-400 z-10 transition-colors";

                        // ถ้าทำครบตามกำหนดครั้งต่อเซ็ต
                        if (repCount >= MAX_REPS_PER_SET) {
                            if (currentSet < ex.sets) {
                                currentSet++;
                                repCount = 0;
                                Swal.fire({
                                    title: 'ยอดเยี่ยม! ขึ้นเซ็ตใหม่',
                                    icon: 'success',
                                    timer: 2000,
                                    background: '#1e293b',
                                    color: '#fff',
                                    showConfirmButton: false
                                });
                            } else {
                                // ถ้าครบทุกเซ็ตในท่านี้
                                if (qIdx < queue.length - 1) {
                                    qIdx++;
                                    currentSet = 1;
                                    repCount = 0;
                                    Swal.fire({
                                        title: 'เปลี่ยนท่าต่อไป!',
                                        text: queue[qIdx].exercise_name,
                                        icon: 'info',
                                        confirmButtonColor: '#dc2626',
                                        background: '#1e293b',
                                        color: '#fff',
                                        timer: 2500,
                                        showConfirmButton: false
                                    });
                                } else {
                                    // ถ้าครบทุกท่าในภารกิจ
                                    finishMission();
                                }
                            }
                        }
                        updateUI(); // อัปเดตตัวเลขหน้าจอทันที
                    }
                } else {
                    statusText.innerText = `กำลังทำขั้นตอนที่ ${currentStepIdx + 1} (ความแม่นยำ: ${sim}%)`;
                    statusText.className = "absolute bottom-10 left-1/2 transform -translate-x-1/2 bg-red-600/90 px-6 py-3 rounded-full text-white font-bold text-lg text-center w-max shadow-lg border border-red-400 z-10 transition-colors";
                }
            }
            canvasCtx.restore();
        });

        new Camera(videoElement, {
            onFrame: async () => {
                await pose.send({
                    image: videoElement
                });
            },
            width: 1280,
            height: 720
        }).start();

        // เริ่มต้นอัปเดตหน้าจอครั้งแรก
        updateUI();

        // ปรับขนาด Canvas ให้พอดี
        function resizeCanvas() {
            const parent = canvasElement.parentElement;
            canvasElement.width = parent.clientWidth;
            canvasElement.height = parent.clientHeight;
        }
        window.addEventListener('resize', resizeCanvas);
        setTimeout(resizeCanvas, 500);

        // ฟังก์ชันยอมแพ้
        function confirmGiveUp() {
            Swal.fire({
                title: 'ยอมแพ้ใช่ไหม?',
                text: "หากออกตอนนี้ จะไม่ได้รับรางวัลใดๆ จากภารกิจนี้เลยนะ!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#4b5563',
                confirmButtonText: 'ใช่, กลับหน้าแรก',
                cancelButtonText: 'สู้ต่อ!',
                background: '#1e293b',
                color: '#fff'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'home.php';
                }
            })
        }

        // ฟังก์ชันข้ามท่า (สิทธิพิเศษข้ามคิว)
        function skipExercise() {
            Swal.fire({
                title: 'ต้องการข้ามท่านี้?',
                text: "ข้ามท่านี้เพื่อไปท่าต่อไป หรือถ้าเป็นท่าสุดท้ายจะจบภารกิจทันที",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#eab308',
                cancelButtonColor: '#4b5563',
                confirmButtonText: 'ใช่, ข้ามเลย',
                cancelButtonText: 'ยกเลิก',
                background: '#1e293b',
                color: '#fff'
            }).then((result) => {
                if (result.isConfirmed) {
                    if (qIdx < queue.length - 1) {
                        qIdx++;
                        currentSet = 1;
                        repCount = 0;
                        updateUI();
                    } else {
                        finishMission();
                    }
                }
            })
        }
    </script>

</body>

</html>