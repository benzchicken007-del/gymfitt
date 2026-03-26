<?php
session_start();
require_once 'config/config.php';
require_once 'includes/level_system.php';

$is_guest = isset($_GET['guest']) ? true : false;
if (!$is_guest && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$exercise_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$mission_id = isset($_GET['mission_id']) ? intval($_GET['mission_id']) : null;

// ค้นหาตัวแปรเชื่อมต่อฐานข้อมูลอัตโนมัติ (รองรับ $pdo, $conn, $db, $mysqli)
$db_conn = null;
if (isset($pdo)) $db_conn = $pdo;
elseif (isset($conn)) $db_conn = $conn;
elseif (isset($db)) $db_conn = $db;
elseif (isset($mysqli)) $db_conn = $mysqli;

if (!$db_conn) {
    die("เกิดข้อผิดพลาด: ไม่พบตัวแปรเชื่อมต่อฐานข้อมูล โปรดตรวจสอบไฟล์ config/config.php");
}

$is_pdo = $db_conn instanceof PDO;

// ดึงข้อมูลท่าออกกำลังกาย
if ($is_pdo) {
    $stmt = $db_conn->prepare("SELECT * FROM exercises WHERE exercise_id = ?");
    $stmt->execute([$exercise_id]);
    $exercise = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = $db_conn->prepare("SELECT * FROM exercises WHERE exercise_id = ?");
    $stmt->bind_param("i", $exercise_id);
    $stmt->execute();
    $exercise = $stmt->get_result()->fetch_assoc();
}

if (!$exercise) {
    header("Location: " . ($is_guest ? "guest_home.php" : "home.php"));
    exit();
}

// ดึงข้อมูลขั้นตอน (Steps)
$steps = [];
if ($is_pdo) {
    $stmt2 = $db_conn->prepare("SELECT * FROM exercise_steps WHERE exercise_id=? ORDER BY step_number ASC");
    $stmt2->execute([$exercise_id]);
    while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        $steps[] = $row;
    }
} else {
    $stmt2 = $db_conn->prepare("SELECT * FROM exercise_steps WHERE exercise_id=? ORDER BY step_number ASC");
    $stmt2->bind_param("i", $exercise_id);
    $stmt2->execute();
    $result = $stmt2->get_result();
    while ($row = $result->fetch_assoc()) {
        $steps[] = $row;
    }
}

// ดึงข้อมูลภารกิจ (ถ้ามี)
$mission = null;
if ($mission_id) {
    $sql_mission = "
        SELECT m.*, me.sets as target_count 
        FROM missions m 
        JOIN mission_exercises me ON m.mission_id = me.mission_id 
        WHERE m.mission_id = ? AND me.exercise_id = ?
    ";

    if ($is_pdo) {
        $stmt = $db_conn->prepare($sql_mission);
        $stmt->execute([$mission_id, $exercise_id]);
        $mission = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db_conn->prepare($sql_mission);
        $stmt->bind_param("ii", $mission_id, $exercise_id);
        $stmt->execute();
        $mission = $stmt->get_result()->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ออกกำลังกาย: <?php echo htmlspecialchars($exercise['exercise_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
    <!-- MediaPipe & Camera -->
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
            background: rgba(0, 0, 0, 0.6);
            padding: 10px 30px;
            border-radius: 50px;
            font-size: 2.5rem;
            font-weight: bold;
            color: #38bdf8;
            border: 2px solid #38bdf8;
            z-index: 10;
        }
    </style>
</head>

<body>

    <div class="parent">
        <!-- 1: รูปภาพตัวอย่างท่า -->
        <div class="grid-item div1">
            <div class="bg-gray-800 p-2 text-xs font-bold uppercase tracking-wider text-gray-400">ตัวอย่างท่า</div>
            <?php
            // ตรวจสอบ Path ของรูปภาพให้ถูกต้อง
            $img_filename = basename($exercise['exercise_image']);
            $img_path = "uploads/exercises/" . $img_filename;
            if (!file_exists($img_path) && file_exists("uploads/" . $img_filename)) {
                $img_path = "uploads/" . $img_filename;
            }
            ?>
            <img src="<?php echo htmlspecialchars($img_path); ?>" alt="Exercise Step" class="demo-img object-contain p-2">
        </div>

        <!-- 2: วิดีโอสอน -->
        <div class="grid-item div2">
            <div class="bg-gray-800 p-2 text-xs font-bold uppercase tracking-wider text-gray-400">วิดีโอสาธิต</div>
            <?php
            if (!empty($exercise['exercise_video'])):
                // ตรวจสอบ Path ของวิดีโอให้ถูกต้องแบบเดียวกับโค้ดเก่า
                $vid_filename = basename($exercise['exercise_video']);
                $vid_path = "uploads/exercises/" . $vid_filename;
                if (!file_exists($vid_path) && file_exists("uploads/" . $vid_filename)) {
                    $vid_path = "uploads/" . $vid_filename;
                }
            ?>
                <video src="<?php echo htmlspecialchars($vid_path); ?>" autoplay loop muted playsinline controls class="demo-video"></video>
            <?php else: ?>
                <div class="flex flex-col items-center justify-center h-full text-gray-500 italic bg-gray-900">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                    ไม่มีวิดีโอสาธิต
                </div>
            <?php endif; ?>
        </div>

        <!-- 3: ระบบตัวจับ AI Pose (กล้อง) -->
        <div class="grid-item div3 bg-black">
            <div class="count-overlay" id="counterDisplay">0 / <?php echo $mission ? $mission['target_count'] : '∞'; ?></div>
            <video class="input_video" style="display:none;" autoplay playsinline></video>
            <canvas class="output_canvas"></canvas>

            <!-- ข้อความแจ้งเตือนสถานะแบบ Real-time -->
            <div id="statusText" class="absolute bottom-10 left-1/2 transform -translate-x-1/2 bg-gray-800/80 px-6 py-3 rounded-full text-white font-bold text-lg text-center w-max shadow-lg border border-gray-600 z-10">
                กำลังเตรียมกล้องและ AI...
            </div>
        </div>

        <!-- 4: รายละเอียดท่าออกกำลังกาย -->
        <div class="grid-item div4 flex flex-col">
            <h2 class="text-2xl font-bold text-blue-400 mb-2"><?php echo htmlspecialchars($exercise['exercise_name']); ?></h2>
            <?php if ($is_guest): ?>
                <span class="inline-block bg-yellow-600 text-white text-xs px-2 py-1 rounded mb-2 w-max">โหมดฝึกซ้อม (Guest)</span>
            <?php endif; ?>

            <div class="text-sm text-gray-300 mb-2 overflow-y-auto pr-2 flex-1">
                <?php if (!empty($exercise['exercise_detail'])): ?>
                    <p class="font-semibold text-yellow-500 mb-1">รายละเอียดท่า:</p>
                    <p class="mb-3 whitespace-pre-wrap text-xs text-gray-400"><?php echo htmlspecialchars($exercise['exercise_detail']); ?></p>
                <?php endif; ?>

                <?php if (!empty($exercise['how_to'])): ?>
                    <p class="font-semibold text-white mb-1">วิธีทำ:</p>
                    <p class="whitespace-pre-wrap text-sm text-gray-200"><?php echo htmlspecialchars($exercise['how_to']); ?></p>
                <?php endif; ?>

                <!-- แสดงข้อความคำแนะนำ msg_too_high และ msg_too_low แบบซ่อนไว้ก่อนให้ JS ควบคุม -->
                <?php if (!empty($exercise['msg_too_high']) || !empty($exercise['msg_too_low'])): ?>
                    <div id="feedbackContainer" class="mt-4 bg-gray-800/80 p-3 rounded-xl border border-gray-700/50 hidden">
                        <p class="font-bold text-red-400 mb-2">⚠️ คำแนะนำจัดท่าทาง:</p>
                        <div class="space-y-2 text-xs">
                            <?php if (!empty($exercise['msg_too_high'])): ?>
                                <p id="msgTooHigh" class="text-gray-300 leading-relaxed hidden">
                                    <span class="bg-red-900/50 text-red-300 px-2 py-1 rounded mr-1 font-bold">สูงไป!</span>
                                    <?php echo htmlspecialchars($exercise['msg_too_high']); ?>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($exercise['msg_too_low'])): ?>
                                <p id="msgTooLow" class="text-gray-300 leading-relaxed hidden">
                                    <span class="bg-orange-900/50 text-orange-300 px-2 py-1 rounded mr-1 font-bold">ต่ำไป!</span>
                                    <?php echo htmlspecialchars($exercise['msg_too_low']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mt-auto pt-3 border-t border-gray-700 flex justify-between items-center">
                <span class="text-sm text-gray-400">เกณฑ์ความแม่นยำ: </span>
                <span class="text-green-400 font-bold text-lg"><?php echo htmlspecialchars($exercise['required_accuracy']); ?>%</span>
            </div>
        </div>

        <!-- 5: ข้อมูลภารกิจ -->
        <div class="grid-item div5 flex flex-col justify-center">
            <div class="border-b border-gray-700 pb-2 mb-4">
                <h3 class="font-bold text-yellow-500 text-lg">ภารกิจที่เกี่ยวข้อง</h3>
            </div>
            <?php if ($mission && !$is_guest): ?>
                <div class="space-y-4">
                    <div class="flex justify-between items-center bg-gray-800 p-3 rounded-lg">
                        <span class="text-gray-400">เป้าหมาย:</span>
                        <span class="text-white font-bold text-xl"><?php echo $mission['target_count']; ?> ครั้ง</span>
                    </div>
                    <div class="flex justify-between items-center px-2">
                        <span class="text-gray-400">รางวัล XP:</span>
                        <span class="text-blue-400 font-bold">+<?php echo $mission['exp_reward']; ?> XP</span>
                    </div>
                    <div class="flex justify-between items-center px-2">
                        <span class="text-gray-400">รางวัล Tokens:</span>
                        <span class="text-yellow-400 font-bold"><?php echo $mission['token_reward']; ?> Tokens</span>
                    </div>
                    <div class="mt-4 bg-gray-900 rounded-full h-5 border border-gray-700 overflow-hidden relative">
                        <div id="missionProgress" class="bg-blue-600 h-full transition-all duration-300" style="width: 0%"></div>
                        <p class="absolute inset-0 flex items-center justify-center text-xs text-white font-bold drop-shadow-md" id="progressText">0%</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="flex flex-col items-center justify-center h-full text-gray-500 opacity-70">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-lg">โหมดฝึกซ้อมอิสระ</p>
                    <p class="text-sm">(ไม่มีภารกิจผูกมัด)</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- 6: ปุ่มควบคุม -->
        <div class="grid-item div6">
            <a href="<?php echo $is_guest ? 'guest_home.php' : 'home.php'; ?>" class="flex-1 text-center py-4 bg-gray-700 hover:bg-gray-600 rounded-xl font-bold transition duration-200">
                ย้อนกลับ
            </a>
            <button onclick="finishExercise()" class="flex-1 py-4 bg-green-600 hover:bg-green-500 rounded-xl font-bold transition duration-200 shadow-lg shadow-green-900/40 border border-green-500/50">
                บันทึกและจบการฝึก
            </button>
        </div>
    </div>

    <script>
        // ตัวแปรจากฐานข้อมูล
        const steps = <?php echo json_encode($steps); ?>;
        const REQUIRED_ACCURACY = <?php echo (int)$exercise['required_accuracy']; ?>;
        const isGuest = <?php echo $is_guest ? 'true' : 'false'; ?>;
        const targetCount = <?php echo $mission ? $mission['target_count'] : 999; ?>;

        // ตัวแปรการทำงาน
        let currentStepIdx = 0;
        let repCount = 0;
        let similarityHistory = [];

        // การตั้งค่า DOM
        const videoElement = document.querySelector('.input_video');
        const canvasElement = document.querySelector('.output_canvas');
        const canvasCtx = canvasElement.getContext('2d');
        const counterDisplay = document.getElementById('counterDisplay');
        const statusText = document.getElementById('statusText');

        // ฟังก์ชันคำนวณมุม 3 จุด
        function calculateAngle(a, b, c) {
            if (!a || !b || !c) return 0;
            const radians = Math.atan2(c.y - b.y, c.x - b.x) - Math.atan2(a.y - b.y, a.x - b.x);
            let angle = Math.abs(radians * 180.0 / Math.PI);
            return angle > 180 ? 360 - angle : angle;
        }

        // ฟังก์ชันคำนวณความแม่นยำ (เวอร์ชันเพิ่มความเข้มงวด/โหดขึ้น)
        // ฟังก์ชันคำนวณความแม่นยำเทียบกับ Template (อิงจากระบบเก่า 12 จุด)
        function calculateSimilarity(current, targetTemplateJson) {
            if (!targetTemplateJson) return 0;
            const target = JSON.parse(targetTemplateJson);

            // 1. ดึงค่าองศาจาก JSON
            const tA = target.angles || {
                le: 0,
                re: 0,
                ls: 0,
                rs: 0,
                lk: 0,
                rk: 0,
                lh: 0,
                rh: 0
            };

            const angleDiffs = [
                Math.abs(current.le - (tA.le || 0)),
                Math.abs(current.re - (tA.re || 0)),
                Math.abs(current.ls - (tA.ls || 0)),
                Math.abs(current.rs - (tA.rs || 0)),
                Math.abs(current.lk - (tA.lk || 0)),
                Math.abs(current.rk - (tA.rk || 0)),
                Math.abs(current.lh - (tA.lh || 0)),
                Math.abs(current.rh - (tA.rh || 0))
            ];

            let angleScore = 100 - (angleDiffs.reduce((a, b) => a + b) / angleDiffs.length);

            // 2. คำนวณพิกัดความสูง (Heights) ของมือและเท้า
            const tH = target.heights || {
                lw_y: 0,
                rw_y: 0,
                la_y: 0,
                ra_y: 0
            };

            const hDiffs = [
                Math.abs(current.lw_y - (tH.lw_y || 0)),
                Math.abs(current.rw_y - (tH.rw_y || 0)),
                Math.abs(current.la_y - (tH.la_y || 0)),
                Math.abs(current.ra_y - (tH.ra_y || 0))
            ];

            let heightScore = 100 - (hDiffs.reduce((a, b) => a + b) / hDiffs.length * 200);

            // 3. ถ่วงน้ำหนักคะแนน (มุม 70% + พิกัดความสูง 30%)
            let finalScore = (angleScore * 0.7) + (Math.max(0, heightScore) * 0.3);
            return Math.round(Math.max(0, Math.min(100, finalScore)));
        }
        // ฟังก์ชันประมวลผล Pose (ถูกเรียกทุก Frame)
        function onResults(results) {
            // วาดรูปพื้นหลัง
            canvasCtx.save();
            canvasCtx.clearRect(0, 0, canvasElement.width, canvasElement.height);
            canvasCtx.drawImage(results.image, 0, 0, canvasElement.width, canvasElement.height);

            if (results.poseLandmarks && steps.length > 0) {
                // วาดโครงกระดูก
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
                    le: calculateAngle(lm[11], lm[13], lm[15]), // ศอกซ้าย
                    re: calculateAngle(lm[12], lm[14], lm[16]), // ศอกขวา
                    ls: calculateAngle(lm[13], lm[11], lm[23]), // ไหล่ซ้าย
                    rs: calculateAngle(lm[14], lm[12], lm[24]), // ไหล่ขวา
                    lk: calculateAngle(lm[23], lm[25], lm[27]), // เข่าซ้าย
                    rk: calculateAngle(lm[24], lm[26], lm[28]), // เข่าขวา
                    lh: calculateAngle(lm[25], lm[23], lm[11]), // สะโพกซ้าย
                    rh: calculateAngle(lm[26], lm[24], lm[12]), // สะโพกขวา
                    lw_y: lm[15].y - lm[11].y, // ข้อมือซ้าย (จับความสูง)
                    rw_y: lm[16].y - lm[12].y, // ข้อมือขวา (จับความสูง)
                    la_y: lm[27].y - lm[23].y, // ข้อเท้าซ้าย (จับความสูง)
                    ra_y: lm[28].y - lm[24].y // ข้อเท้าขวา (จับความสูง)
                };

                // เทียบความแม่นยำกับสเต็ปปัจจุบัน
                let sim = calculateSimilarity(currentData, steps[currentStepIdx].angle_template);

                // ----------------------------------------------------------------------
                // ควบคุมการแสดงผลคำแนะนำจัดท่าทาง (Msg Too High / Msg Too Low)
                // ----------------------------------------------------------------------
                const feedbackContainer = document.getElementById('feedbackContainer');
                const msgTooHigh = document.getElementById('msgTooHigh');
                const msgTooLow = document.getElementById('msgTooLow');

                if (feedbackContainer) {
                    if (sim >= REQUIRED_ACCURACY) {
                        // ถ้าท่าทางถูกต้องแล้ว ให้ซ่อนคำแนะนำทั้งหมด
                        feedbackContainer.classList.add('hidden');
                        if (msgTooHigh) msgTooHigh.classList.add('hidden');
                        if (msgTooLow) msgTooLow.classList.add('hidden');
                    } else {
                        // ถ้ายังไม่ถูกต้อง แสดงกล่องคำแนะนำ
                        feedbackContainer.classList.remove('hidden');

                        let tA = {
                            le: 0,
                            re: 0,
                            lk: 0,
                            rk: 0
                        };
                        if (steps[currentStepIdx].angle_template) {
                            try {
                                const parsed = JSON.parse(steps[currentStepIdx].angle_template);
                                if (parsed.angles) tA = parsed.angles;
                            } catch (e) {}
                        }

                        // ประเมินผลรวมมุมปัจจุบัน เทียบกับผลรวมมุมเป้าหมาย 
                        // ถ้ากว้างกว่าเป้าหมาย = มักจะแปลว่าตัวสูงไป
                        // ถ้าแคบกว่าเป้าหมาย = มักจะแปลว่าตัวต่ำไป
                        let totalCurrent = currentData.le + currentData.re + currentData.lk + currentData.rk;
                        let totalTarget = (tA.le || 0) + (tA.re || 0) + (tA.lk || 0) + (tA.rk || 0);

                        if (totalCurrent > totalTarget) {
                            // โชว์ข้อความ "สูงไป" แล้วซ่อน "ต่ำไป"
                            if (msgTooHigh) msgTooHigh.classList.remove('hidden');
                            if (msgTooLow) msgTooLow.classList.add('hidden');

                            // ถ้าระบบไม่ได้ใส่ข้อความสูงไปมา แต่มีข้อความต่ำไป ก็ให้โชว์ต่ำไปแทน
                            if (!msgTooHigh && msgTooLow) msgTooLow.classList.remove('hidden');
                        } else {
                            // โชว์ข้อความ "ต่ำไป" แล้วซ่อน "สูงไป"
                            if (msgTooLow) msgTooLow.classList.remove('hidden');
                            if (msgTooHigh) msgTooHigh.classList.add('hidden');

                            // ถ้าระบบไม่ได้ใส่ข้อความต่ำไปมา แต่มีข้อความสูงไป ก็ให้โชว์สูงไปแทน
                            if (!msgTooLow && msgTooHigh) msgTooHigh.classList.remove('hidden');
                        }
                    }
                }

                // ----------------------------------------------------------------------
                // อัปเดตสถานะและเปลี่ยนสเต็ป
                // ----------------------------------------------------------------------
                if (sim >= REQUIRED_ACCURACY) {
                    similarityHistory.push(sim);

                    if (currentStepIdx < steps.length - 1) {
                        currentStepIdx++;
                        statusText.innerText = `เยี่ยมมาก! ทำขั้นตอนที่ ${currentStepIdx + 1} ต่อเลย`;
                        statusText.className = "absolute bottom-10 left-1/2 transform -translate-x-1/2 bg-green-600/90 px-6 py-3 rounded-full text-white font-bold text-lg text-center w-max shadow-lg border border-green-400 z-10 transition-colors";
                    } else {
                        repCount++;
                        currentStepIdx = 0;
                        statusText.innerText = "นับ 1 ครั้ง! กลับไปเริ่มขั้นตอนแรกใหม่";
                        statusText.className = "absolute bottom-10 left-1/2 transform -translate-x-1/2 bg-blue-600/90 px-6 py-3 rounded-full text-white font-bold text-lg text-center w-max shadow-lg border border-blue-400 z-10 transition-colors";
                    }
                } else {
                    statusText.innerText = `กำลังทำขั้นตอนที่ ${currentStepIdx + 1} (ความแม่นยำ: ${sim}%)`;
                    statusText.className = "absolute bottom-10 left-1/2 transform -translate-x-1/2 bg-red-600/90 px-6 py-3 rounded-full text-white font-bold text-lg text-center w-max shadow-lg border border-red-400 z-10 transition-colors";
                }

                // อัปเดตตัวเลขการนับ (UI)
                counterDisplay.innerText = `${repCount} / ${targetCount === 999 ? '∞' : targetCount}`;

                // อัปเดตหลอดความคืบหน้า (Progress Bar)
                if (targetCount !== 999) {
                    const progress = Math.min((repCount / targetCount) * 100, 100);
                    const progressBar = document.getElementById('missionProgress');
                    const progressText = document.getElementById('progressText');
                    if (progressBar) {
                        progressBar.style.width = progress + '%';
                        progressText.innerText = `${Math.round(progress)}%`;
                    }
                }
            }
            canvasCtx.restore();
        }

        // สร้างและกำหนดค่า MediaPipe Pose
        const pose = new Pose({
            locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/pose/${file}`
        });

        pose.setOptions({
            modelComplexity: 1,
            smoothLandmarks: true,
            minDetectionConfidence: 0.5,
            minTrackingConfidence: 0.5
        });
        pose.onResults(onResults);

        // เปิดกล้อง
        const camera = new Camera(videoElement, {
            onFrame: async () => {
                await pose.send({
                    image: videoElement
                });
            },
            width: 1280,
            height: 720
        });
        camera.start();

        // ปรับขนาด Canvas อัตโนมัติเมื่อขนาดจอเปลี่ยน
        function resizeCanvas() {
            const parent = canvasElement.parentElement;
            canvasElement.width = parent.clientWidth;
            canvasElement.height = parent.clientHeight;
        }
        window.addEventListener('resize', resizeCanvas);

        // ตั้งค่าขนาดเริ่มต้น
        setTimeout(resizeCanvas, 500);

        // ฟังก์ชันบันทึกและจบการออกกำลังกาย
        // ฟังก์ชันบันทึกและจบการออกกำลังกาย
        function finishExercise() {
            if (repCount === 0 && !isGuest) {
                Swal.fire({
                    icon: 'warning',
                    title: 'ยังไม่มีผลลัพธ์',
                    text: 'คุณต้องออกกำลังกายให้สำเร็จอย่างน้อย 1 ครั้งก่อนบันทึกผล',
                    background: '#1e293b',
                    color: '#fff'
                });
                return;
            }

            let avgSim = similarityHistory.length > 0 ?
                Math.round(similarityHistory.reduce((a, b) => a + b) / similarityHistory.length) :
                0;

            // กรณีเป็นโหมด Guest ไม่ต้องบันทึกเข้า DB แค่พากลับ
            if (isGuest) {
                window.location.href = 'guest_home.php?status=completed';
                return;
            }

            // กรณีล็อกอิน บันทึกข้อมูล
            Swal.fire({
                title: 'กำลังบันทึกข้อมูล...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // ส่งแบบ POST เพื่อบันทึกฐานข้อมูล
            const formData = new FormData();
            formData.append('mission_id', '<?php echo $mission_id; ?>');
            formData.append('exercise_id', '<?php echo $exercise_id; ?>');
            formData.append('count', repCount); // เผื่อ script ตัวหลังบ้านใช้ count
            formData.append('reps', repCount); // เผื่อ script ตัวหลังบ้านใช้ reps
            formData.append('accuracy', avgSim);
            formData.append('passed', 'true'); // เพิ่มการส่งค่า passed ให้ PHP

            fetch('actions/complete_mission.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success || data.status === 'success' || data.exp !== undefined) {

                        // ดึงรายละเอียดมาแสดง หรือใช้ค่าเริ่มต้น
                        let popupTitle = data.title || 'ยอดเยี่ยมมาก!';
                        let popupText = data.message ? `${data.message}\n(ความแม่นยำเฉลี่ย: ${avgSim}%)` : `บันทึกการออกกำลังกายสำเร็จ ความแม่นยำเฉลี่ย: ${avgSim}%`;
                        let redirectUrl = data.redirect || 'home.php';

                        Swal.fire({
                            icon: 'success',
                            title: popupTitle,
                            text: popupText,
                            background: '#1e293b',
                            color: '#fff',
                            confirmButtonColor: '#10b981'
                        }).then(() => {
                            // เปลี่ยนจาก profile.php เป็น home.php (หรือตามที่หลังบ้านส่งมา)
                            window.location.href = isGuest ? 'guest_home.php' : redirectUrl;
                        });
                    } else {
                        throw new Error(data.message || data.error || 'บันทึกไม่สำเร็จ');
                    }
                })
                .catch(err => {
                    console.error('Fetch error fallback:', err);
                    // Fallback หาก actions/complete_mission.php ของคุณรับเป็น GET แบบเก่า
                    window.location.href = `actions/complete_mission.php?exercise_id=<?php echo $exercise_id; ?>&accuracy=${avgSim}&reps=${repCount}&mission_id=<?php echo $mission_id; ?>&passed=true`;
                });
        }
    </script>

</body>

</html>