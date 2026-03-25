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

$stmt = $conn->prepare("SELECT * FROM missions WHERE mission_id=?");
$stmt->bind_param("i", $mission_id);
$stmt->execute();
$mission = $stmt->get_result()->fetch_assoc();

if (!$mission) {
    header("Location: home.php");
    exit();
}

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

// ตั้งชื่อหน้าเว็บและเรียกใช้ Header
$page_title = "ภารกิจ: " . htmlspecialchars($mission['mission_name']);
include 'includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/@mediapipe/pose/pose.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    /* ปรับแต่ง Sweetalert ให้เข้ากับธีม */
    .swal2-popup {
        background: #140505 !important;
        border: 1px solid #7f1d1d !important;
        color: #fff !important;
    }
</style>

<?php include 'includes/navbar.php'; ?>

<main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 relative">

    <div class="fixed bottom-0 left-0 w-96 h-96 bg-red-900/10 blur-[100px] rounded-full pointer-events-none z-[-1]"></div>

    <div class="flex justify-between items-center bg-card p-5 rounded-2xl border border-red-900/30 shadow-lg mb-8 relative overflow-hidden">
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-10 mix-blend-overlay"></div>
        <h1 class="text-2xl font-bold text-white relative z-10"><i class="fa-solid fa-flag-checkered text-primary mr-2"></i> <?= htmlspecialchars($mission['mission_name']) ?></h1>
        <button onclick="window.location='home.php'" class="relative z-10 bg-darker hover:bg-red-600 border border-red-900/50 text-white font-bold py-2 px-5 rounded-xl transition duration-300">
            <i class="fa-solid fa-flag mr-2"></i> ยอมแพ้
        </button>
    </div>

    <div class="bg-card rounded-3xl p-6 border border-red-900/30 shadow-2xl flex flex-col md:flex-row gap-6 mb-8">
        <div id="info-media-container" class="flex-1 rounded-2xl overflow-hidden bg-darker border border-red-900/40 aspect-video relative">
        </div>

        <div class="flex-[1.5] flex flex-col justify-center">
            <h2 id="info-ex-name" class="text-3xl font-bold text-primary mb-2 drop-shadow-[0_0_8px_rgba(220,38,38,0.5)]">กำลังโหลดข้อมูล...</h2>

            <div class="inline-block px-4 py-1.5 rounded-full bg-primary/10 border border-primary/30 text-white text-sm font-bold w-fit mb-4">
                <i class="fa-solid fa-crosshairs text-primary"></i> เกณฑ์ความแม่นยำ: <span id="info-accuracy" class="text-primary">0</span>%
            </div>

            <div class="text-yellow-500 text-sm font-bold mb-1"><i class="fa-solid fa-circle-info"></i> รายละเอียดท่า:</div>
            <div id="info-detail" class="text-zinc-400 text-sm leading-relaxed mb-4 whitespace-pre-wrap">-</div>

            <div class="text-white text-sm font-bold mb-1"><i class="fa-solid fa-person-running text-primary"></i> วิธีปฏิบัติ:</div>
            <div id="info-howto" class="text-zinc-300 text-sm leading-relaxed whitespace-pre-wrap">-</div>
        </div>
    </div>

    <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-video text-primary"></i> เริ่มการฝึกซ้อม</h3>

    <div class="relative w-full max-w-4xl mx-auto aspect-[4/3] rounded-3xl overflow-hidden bg-darker border-2 border-primary shadow-[0_0_30px_rgba(220,38,38,0.2)]">
        <video class="input_video hidden" autoplay playsinline></video>
        <canvas class="output_canvas w-full h-full object-cover -scale-x-100 absolute top-0 left-0"></canvas>
    </div>

    <div class="bg-card max-w-4xl mx-auto mt-6 p-6 rounded-3xl border border-red-900/30 shadow-xl">
        <div id="statusText" class="text-xl font-bold text-white drop-shadow-[0_0_5px_currentColor] text-center mb-4">กำลังเตรียมกล้อง...</div>

        <div class="w-full h-3 bg-zinc-900 rounded-full overflow-hidden border border-zinc-800 mb-6">
            <div id="progressBar" class="h-full bg-gradient-to-r from-red-800 via-primary to-white shadow-[0_0_10px_rgba(255,255,255,0.5)] transition-all duration-300" style="width: 0%"></div>
        </div>

        <div class="grid grid-cols-3 gap-4 text-center">
            <div class="bg-darker p-4 rounded-2xl border border-red-900/20 shadow-inner">
                <div id="repVal" class="text-2xl font-black text-green-500">0 / 10</div>
                <div class="text-xs text-zinc-500 mt-1 uppercase tracking-wider">ครั้ง (Reps)</div>
            </div>
            <div class="bg-darker p-4 rounded-2xl border border-red-900/20 shadow-inner">
                <div id="setVal" class="text-2xl font-black text-yellow-500">1 / 1</div>
                <div class="text-xs text-zinc-500 mt-1 uppercase tracking-wider">เซ็ต (Sets)</div>
            </div>
            <div class="bg-darker p-4 rounded-2xl border border-red-900/20 shadow-inner">
                <div id="exVal" class="text-2xl font-black text-primary">1 / <?= count($mission_queue) ?></div>
                <div class="text-xs text-zinc-500 mt-1 uppercase tracking-wider">ท่าที่ (Exercise)</div>
            </div>
        </div>
    </div>

</main>

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

        document.getElementById('info-ex-name').innerHTML = ex.exercise_name + ' <span class="text-lg text-zinc-400 font-normal">(ท่าที่ ' + (qIdx + 1) + ')</span>';
        document.getElementById('info-accuracy').innerText = ex.required_accuracy;
        document.getElementById('info-detail').innerText = ex.exercise_detail ? ex.exercise_detail : 'ไม่มีรายละเอียด';
        document.getElementById('info-howto').innerText = ex.how_to ? ex.how_to : 'ไม่มีวิธีปฏิบัติ';

        let mediaContainer = document.getElementById('info-media-container');
        let file = ex.exercise_video ? ex.exercise_video : ex.exercise_image;

        if (file) {
            let filename = file.split('/').pop().split('\\').pop(); // ตัดเอาแค่ชื่อไฟล์
            let ext = filename.split('.').pop().toLowerCase();

            if (['mp4', 'webm', 'mov'].includes(ext)) {
                // ให้วิดีโอพยายามโหลดจากโฟลเดอร์ใหม่ก่อน ถ้าพังให้โหลดจากโฟลเดอร์เก่า
                mediaContainer.innerHTML = `
                <video class="w-full h-full object-cover" controls loop muted playsinline autoplay>
                    <source src="uploads/exercises/${filename}" type="video/${ext}">
                    <source src="uploads/${filename}" type="video/${ext}">
                </video>`;
            } else {
                mediaContainer.innerHTML = `<img src="uploads/exercises/${filename}" onerror="this.src='uploads/${filename}'; this.onerror=null; this.src='assets/images/no-image.png'" class="w-full h-full object-cover" alt="Exercise">`;
            }
        } else {
            mediaContainer.innerHTML = `<div class="w-full h-full flex items-center justify-center text-zinc-600 bg-zinc-900"><i class="fa-solid fa-image fa-3x"></i></div>`;
        }

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
            showConfirmButton: false,
            timer: 2500
        }).then(() => {
            // อ้างอิง Path ของ Actions ตามที่คุณจัดโฟลเดอร์ไว้
            window.location = `actions/complete_mission.php?mission_id=<?= $mission_id ?>&accuracy=${avgSim}`;
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
                color: '#dc2626',
                lineWidth: 3
            });
            drawLandmarks(canvasCtx, results.poseLandmarks, {
                color: '#ffffff',
                fillColor: '#dc2626',
                lineWidth: 1,
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
            const statusText = document.getElementById('statusText');

            if (sim >= ex.required_accuracy) {
                similarityHistory.push(sim);
                if (currentStepIdx < ex.steps.length - 1) {
                    currentStepIdx++;
                    statusText.innerText = `เยี่ยม! ต่อไปสเต็ป ${currentStepIdx + 1}`;
                    statusText.className = "text-xl font-bold text-white drop-shadow-[0_0_5px_currentColor] text-center mb-4";
                } else {
                    repCount++;
                    currentStepIdx = 0;
                    statusText.innerText = "ได้ 1 ครั้ง!";
                    statusText.className = "text-xl font-bold text-green-500 drop-shadow-[0_0_5px_rgba(34,197,94,0.8)] text-center mb-4";

                    if (repCount >= MAX_REPS_PER_SET) {
                        if (currentSet < ex.sets) {
                            currentSet++;
                            repCount = 0;
                            Swal.fire({
                                title: 'ยอดเยี่ยม! ขึ้นเซ็ตใหม่',
                                icon: 'success',
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
                                    confirmButtonColor: '#dc2626',
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
                statusText.innerText = `กำลังทำสเต็ป ${currentStepIdx + 1} (ความแม่นยำ: ${sim}%)`;
                statusText.className = "text-xl font-bold text-primary drop-shadow-[0_0_5px_currentColor] text-center mb-4";
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

    updateUI();
</script>
</body>

</html>let mediaContainer = document.getElementById('info-media-container');
let file = ex.exercise_video ? ex.exercise_video : ex.exercise_image;

if (file) {
let filename = file.split('/').pop().split('\\').pop(); // ตัดเอาแค่ชื่อไฟล์
let ext = filename.split('.').pop().toLowerCase();

if (['mp4', 'webm', 'mov'].includes(ext)) {
// ให้วิดีโอพยายามโหลดจากโฟลเดอร์ใหม่ก่อน ถ้าพังให้โหลดจากโฟลเดอร์เก่า
mediaContainer.innerHTML = `
<video class="w-full h-full object-cover" controls loop muted playsinline autoplay>
    <source src="uploads/exercises/${filename}" type="video/${ext}">
    <source src="uploads/${filename}" type="video/${ext}">
</video>`;
} else {
mediaContainer.innerHTML = `<img src="uploads/exercises/${filename}" onerror="this.src='uploads/${filename}'; this.onerror=null; this.src='assets/images/no-image.png'" class="w-full h-full object-cover" alt="Exercise">`;
}
} else {
mediaContainer.innerHTML = `<div class="w-full h-full flex items-center justify-center text-zinc-600 bg-zinc-900"><i class="fa-solid fa-image fa-3x"></i></div>`;
}