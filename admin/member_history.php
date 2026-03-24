<?php
session_start();
if(!isset($_SESSION['admin_id'])){
    header("Location: ../login.php");
    exit();
}

include "../config/config.php";

if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
    header("Location: members.php");
    exit();
}

$id = intval($_GET['id']);

/* ===== ดึงชื่อผู้ใช้ ===== */
$stmtUser = $conn->prepare("SELECT username FROM users WHERE user_id=?");
$stmtUser->bind_param("i",$id);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();
$user = $resultUser->fetch_assoc();
$stmtUser->close();

if(!$user){
    header("Location: members.php");
    exit();
}

/* ===== ดึงประวัติ (ไม่ JOIN) ===== */
$stmtHistory = $conn->prepare("
    SELECT workout_date, mission_id, earned_exp
    FROM workout_history
    WHERE user_id=?
    ORDER BY workout_date DESC
");

if(!$stmtHistory){
    die("SQL Error: " . $conn->error);
}

$stmtHistory->bind_param("i",$id);
$stmtHistory->execute();
$history = $stmtHistory->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Workout History - GYMFITT</title>
<style>
body{
    background:#0f172a;
    color:white;
    font-family:sans-serif;
    padding:40px;
}
.card{
    background:#1e293b;
    padding:20px;
    border-radius:10px;
    margin-bottom:15px;
}
</style>
</head>
<body>

<h1>ประวัติการออกกำลังกาย: <?=htmlspecialchars($user['username'])?></h1>

<?php if($history->num_rows > 0){ ?>
    <?php while($row = $history->fetch_assoc()){ ?>
        <div class="card">
            <p>📅 วันที่: <?=htmlspecialchars($row['workout_date'])?></p>
            <p>🎯 ภารกิจ ID: <?=htmlspecialchars($row['mission_id'])?></p>
            <p>🔥 คะแนนที่ได้: <?=htmlspecialchars($row['earned_exp'])?></p>
        </div>
    <?php } ?>
<?php } else { ?>
    <div class="card">
        <p>ยังไม่มีประวัติการออกกำลังกาย</p>
    </div>
<?php } ?>

</body>
</html>
