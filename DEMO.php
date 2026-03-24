<?php 
include "config/config.php"; 
$mode = isset($_GET['mode']) ? $_GET['mode'] : "bronze";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GYMFITT - AI Demo</title>

    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/demo.css">

    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/pose/pose.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js" crossorigin="anonymous"></script>

</head>
<body>

<div class="top-bar">
    <button class="btn-back" onclick="goBack()">⬅ กลับ</button>
</div>

<h1 class="title">
<?= ($mode=="free") ? "Free Mode - Unlimited Reps" : "AI Mission: Dumbbell Curl x10" ?>
</h1>

<div class="container">
    <video id="video" width="640" height="480" autoplay muted playsinline style="display:none;"></video>
    <canvas id="canvas" width="640" height="480"></canvas>
</div>

<div id="stats">
    ครั้งที่ทำได้: <span id="reps">0</span>
    <?php if($mode!="free"){ ?> / 10 <?php } ?>
    <br>
    ความถูกต้อง: <span id="accuracy">0</span>%
</div>

<?php if($mode!="free"){ ?>
<div class="result-popup" id="resultPopup">
    <div class="result-box">
        <h2>🎉 ภารกิจสำเร็จ!</h2>
        <p id="resultText"></p>
        <button class="btn-close" onclick="closePopup()">ปิด</button>
    </div>
</div>
<?php } ?>

<script>
const mode = "<?= $mode ?>";
const video = document.getElementById('video');
const canvas = document.getElementById('canvas');
const ctx = canvas.getContext('2d');

let reps = 0;
let stage = "down";
let accuracyTotal = 0;

function goBack(){
    window.location.href = "guest_home.php";
}

function calculateAngle(a, b, c) {
    const radians =
        Math.atan2(c.y - b.y, c.x - b.x) -
        Math.atan2(a.y - b.y, a.x - b.x);
    let angle = Math.abs(radians * 180.0 / Math.PI);
    if (angle > 180) angle = 360 - angle;
    return angle;
}

const pose = new Pose({
    locateFile: (file) => {
        return `https://cdn.jsdelivr.net/npm/@mediapipe/pose/${file}`;
    }
});

pose.setOptions({
    modelComplexity: 1,
    smoothLandmarks: true,
    minDetectionConfidence: 0.5,
    minTrackingConfidence: 0.5
});

pose.onResults(results => {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(results.image, 0, 0, canvas.width, canvas.height);

    if(results.poseLandmarks){
        drawConnectors(ctx, results.poseLandmarks, POSE_CONNECTIONS,
            {color: '#00FFFF', lineWidth: 4});
        drawLandmarks(ctx, results.poseLandmarks,
            {color: '#FF0000', lineWidth: 2});

        const lm = results.poseLandmarks;
        const leftAngle = calculateAngle(lm[11], lm[13], lm[15]);
        const rightAngle = calculateAngle(lm[12], lm[14], lm[16]);

        ctx.fillStyle="yellow";
        ctx.font="bold 20px Arial";
        ctx.fillText("L: "+Math.round(leftAngle), 30, 40);
        ctx.fillText("R: "+Math.round(rightAngle), 30, 70);

        if(leftAngle > 160 && rightAngle > 160){
            stage = "down";
        }

        if(leftAngle < 40 && rightAngle < 40 && stage === "down"){
            stage = "up";
            reps++;

            let avgAngle = (leftAngle + rightAngle) / 2;
            let acc = Math.max(70, 100 - Math.abs(90-avgAngle));
            accuracyTotal += acc;

            document.getElementById("reps").innerText = reps;
            document.getElementById("accuracy").innerText =
                Math.round(accuracyTotal/reps);

            if(mode !== "free" && reps >= 10){
                finishMission();
            }
        }
    }
});

function finishMission(){
    let avgAccuracy = Math.round(accuracyTotal / reps);
    let calories = Math.floor(Math.random()*3)+2;

    document.getElementById("resultText").innerHTML =
        "ความถูกต้องเฉลี่ย: <b>"+avgAccuracy+"%</b><br><br>"+
        "คุณใช้พลังงานไปแล้ว <b>"+calories+" kcal</b><br><br>"+
        "ได้รับ +10 EXP";

    document.getElementById("resultPopup").classList.add("show");

    reps = 0;
    accuracyTotal = 0;
    document.getElementById("reps").innerText = 0;
    document.getElementById("accuracy").innerText = 0;
}

function closePopup(){
    document.getElementById("resultPopup").classList.remove("show");
}

// --- การเรียกใช้งานกล้องที่รองรับ Host จริง ---
const camera = new Camera(video, {
    onFrame: async () => {
        await pose.send({image: video});
    },
    width: 640,
    height: 480
});

window.onload = () => {
    // แจ้งเตือนหากไม่ได้ใช้ HTTPS
    if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
        alert("คำเตือน: ระบบ AI ต้องการ HTTPS เพื่อเข้าถึงกล้อง โปรดติดตั้ง SSL บนโฮสต์ของคุณ");
    }
    
    camera.start().catch(err => {
        console.error("Camera Error: ", err);
    });
};
</script>

</body>
</html>