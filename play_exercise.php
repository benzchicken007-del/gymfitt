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

// ตั้งชื่อหน้าเว็บและเรียกใช้ Header
$page_title = "ฝึกซ้อมอิสระ: " . htmlspecialchars($exercise['exercise_name']);
include 'includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/@mediapipe/pose/pose.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if (!$is_guest) include 'includes/navbar.php'; ?>

<main class="bg-gray-50 dark:bg-transparent min-h-screen transition-colors duration-300 py-8 relative">
    <div class="fixed top-20 right-0 w-96 h-96 bg-red-600/10 blur-[100px] rounded-full pointer-events-none z-[-1] hidden dark:block"></div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="bg-white dark:bg-card rounded-3xl p-6 border border-gray-200 dark:border-red-900/30 shadow-xl dark:shadow-2xl flex flex-col md:flex-row gap-6 mb-8 relative overflow-hidden transition-colors">
            <div class="absolute -top-20 -right-20 w-48 h-48 bg-primary/10 blur-[50px] rounded-full"></div>

            <div class="flex-1 rounded-2xl overflow-hidden bg-gray-100 dark:bg-darker border border-gray-200 dark:border-red-900/40 aspect-video relative z-10 transition-colors">
                <?php
                $file = $exercise['exercise_video'] ? $exercise['exercise_video'] : $exercise['exercise_image'];
                $filename = basename($file);
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $filePath = "uploads/exercises/" . $filename;
                if (!file_exists($filePath) && file_exists("uploads/" . $filename)) $filePath = "uploads/" . $filename;

                if (!empty($filename)):
                    if (in_array($ext, ['mp4', 'webm', 'mov'])): ?>
                        <video src="<?= $filePath ?>" class="w-full h-full object-cover" controls loop muted playsinline autoplay></video>
                    <?php else: ?>
                        <img src="<?= $filePath ?>" class="w-full h-full object-cover" alt="Exercise">
                    <?php endif;
                else: ?>
                    <div class="w-full h-full flex items-center justify-center text-gray-400 dark:text-zinc-600">
                        <i class="fa-solid fa-image fa-3x"></i>
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex-[1.5] flex flex-col justify-center relative z-10">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2 transition-colors"><?= htmlspecialchars($exercise['exercise_name']) ?> <span class="text-lg text-gray-500 dark:text-zinc-500 font-normal">(Free Mode)</span></h1>
                <div class="inline-block px-4 py-1.5 rounded-full bg-red-50 dark:bg-primary/10 border border-red-200 dark:border-primary/30 text-primary text-sm font-bold w-fit mb-4 transition-colors">
                    <i class="fa-solid fa-crosshairs"></i> เกณฑ์ความแม่นยำ: <?= (int)$exercise['required_accuracy'] ?>%
                </div>
                <?php if (!empty($exercise['exercise_detail'])): ?>
                    <div class="text-yellow-600 dark:text-yellow-500 text-sm font-bold mb-1"><i class="fa-solid fa-circle-info"></i> รายละเอียดท่า:</div>
                    <div class="text-gray-600 dark:text-zinc-400 text-sm leading-relaxed mb-4 whitespace-pre-wrap transition-colors"><?= htmlspecialchars($exercise['exercise_detail']) ?></div>
                <?php endif; ?>
                <?php if (!empty($exercise['how_to'])): ?>
                    <div class="text-primary text-sm font-bold mb-1"><i class="fa-solid fa-person-running"></i> วิธีปฏิบัติ:</div>
                    <div class="text-gray-800 dark:text-white text-sm leading-relaxed whitespace-pre-wrap transition-colors"><?= htmlspecialchars($exercise['how_to']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2 transition-colors"><i class="fa-solid fa-video text-primary"></i> กล้องตรวจจับ AI</h2>

        <div class="relative w-full max-w-4xl mx-auto aspect-[4/3] rounded-3xl overflow-hidden bg-gray-200 dark:bg-darker border-2 border-primary shadow-lg dark:shadow-[0_0_30px_rgba(220,38,38,0.2)] transition-colors">
            <video class="input_video hidden" autoplay playsinline></video>
            <canvas class="output_canvas w-full h-full object-cover -scale-x-100 absolute top-0 left-0"></canvas>
        </div>

        <div class="bg-white dark:bg-card max-w-4xl mx-auto mt-6 p-6 rounded-3xl border border-gray-200 dark:border-red-900/30 text-center shadow-lg dark:shadow-xl transition-colors">
            <h3 id="statusText" class="text-lg font-bold text-primary mb-2">กำลังเตรียมกล้องและ AI...</h3>
            <div class="text-4xl font-black text-gray-900 dark:text-white transition-colors">
                จำนวนครั้ง: <span id="repVal" class="text-green-500">0</span>
            </div>
        </div>

        <div class="flex justify-center gap-4 mt-8">
            <button onclick="window.location='<?= $is_guest ? 'guest_home.php' : 'home.php' ?>'" class="bg-gray-200 dark:bg-darker border border-gray-300 dark:border-zinc-700 hover:bg-gray-300 dark:hover:bg-zinc-800 text-gray-800 dark:text-white font-bold py-3 px-6 rounded-xl transition">
                <i class="fa-solid fa-arrow-left mr-2"></i> ย้อนกลับ
            </button>
            <button onclick="finishExercise()" class="bg-primary hover:bg-red-600 text-white font-bold py-3 px-8 rounded-xl shadow-md dark:shadow-[0_0_15px_rgba(220,38,38,0.4)] transition transform hover:-translate-y-1">
                <i class="fa-solid fa-save mr-2"></i> บันทึกและจบการฝึก
            </button>
        </div>

    </div>
</main>

<script>
    const steps = <?= json_encode($steps) ?>;
    const REQUIRED_ACCURACY = <?= (int)$exercise['required_accuracy'] ?>;
    let currentStepIdx = 0;
    let repCount = 0;
    let similarityHistory = [];

    const videoElement = document.querySelector('.input_video');
    const canvasElement = document.querySelector('.output_canvas');
    const canvasCtx = canvasElement.getContext('2d');

    // (ส่วนของโค้ด AI คำนวณ MediaPipe เหมือนเดิม ไม่มีการเปลี่ยนแปลงครับ)
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

    function finishExercise() {
        let avgSim = similarityHistory.length > 0 ? Math.round(similarityHistory.reduce((a, b) => a + b) / similarityHistory.length) : 0;
        if (<?= $is_guest ? 'true' : 'false' ?>) window.location = 'guest_home.php?status=completed';
        else window.location = `actions/complete_mission.php?exercise_id=<?= $ex_id ?>&accuracy=${avgSim}&reps=${repCount}`;
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

            let sim = calculateSimilarity(currentData, steps[currentStepIdx].angle_template);
            const statusText = document.getElementById('statusText');
            const isDark = document.documentElement.classList.contains('dark'); // เช็คธีมสำหรับใส่สีตัวหนังสือ

            if (sim >= REQUIRED_ACCURACY) {
                similarityHistory.push(sim);
                if (currentStepIdx < steps.length - 1) {
                    currentStepIdx++;
                    statusText.innerText = `เยี่ยมมาก! ทำขั้นตอนที่ ${currentStepIdx + 1} ต่อเลย`;
                    statusText.className = isDark ? "text-lg font-bold text-white mb-2 drop-shadow-md" : "text-lg font-bold text-gray-900 mb-2";
                } else {
                    repCount++;
                    currentStepIdx = 0;
                    document.getElementById('repVal').innerText = repCount;
                    statusText.innerText = "นับ 1 ครั้ง! กลับไปเริ่มขั้นตอนแรกใหม่";
                    statusText.className = "text-lg font-bold text-green-500 mb-2";
                }
            } else {
                statusText.innerText = `กำลังทำขั้นตอนที่ ${currentStepIdx + 1} (ความแม่นยำ: ${sim}%)`;
                statusText.className = "text-lg font-bold text-primary mb-2";
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