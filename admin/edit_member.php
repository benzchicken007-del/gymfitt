<?php
session_start();
if(!isset($_SESSION['admin_id'])){
    header("Location: ../login.php");
    exit();
}

include "../config/config.php";

/* ===== ตรวจสอบ id ===== */
if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
    header("Location: members.php");
    exit();
}

$id = intval($_GET['id']);

/* ===== บันทึกข้อมูล ===== */
if(isset($_POST['save'])){

    $level  = $_POST['level'];
    $exp    = intval($_POST['exp']);
    $tokens = intval($_POST['tokens']); // รับค่า tokens เพิ่มเติม

    // อนุญาตเฉพาะ level ที่กำหนด
    if($level !== 'admin' && $level !== 'member'){
        $level = 'member';
    }

    // อัปเดต users เพิ่ม tokens เข้าไปด้วย
    $stmt = $conn->prepare("UPDATE users SET level=?, exp=?, tokens=? WHERE user_id=?");
    $stmt->bind_param("siii", $level, $exp, $tokens, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: members.php");
    exit();
}

/* ===== ดึงข้อมูลสมาชิก ===== */
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if(!$user){
    header("Location: members.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Edit Member - GYMFITT</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<style>

/* ===== THEME VARIABLES ===== */
:root{
    --red-main:#ef4444;
    --red-dark:#b91c1c;
    --red-deep:#7f1d1d;

    --blue-main:#38bdf8;
    --blue-dark:#0ea5e9;
    --blue-deep:#0284c7;

    --dark-bg:#0f0f0f;
    --dark-card:#1a1a1a;
    --dark-soft:#1f1f1f;

    --light-bg:#f8fafc;
    --light-soft:#e2e8f0;
}

/* ===== BASE ===== */
body{
    margin:0;
    font-family:Poppins,sans-serif;
    background:linear-gradient(135deg,var(--dark-bg),var(--dark-card));
    color:white;
    transition:0.3s;
}

/* ===== HEADER ===== */
header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:18px 40px;
    background:linear-gradient(90deg,#111,var(--dark-soft));
    border-bottom:1px solid var(--red-deep);
    position:relative;
}

.logo{
    font-size:22px;
    font-weight:600;
    color:var(--red-main);
}

.hamburger{
    font-size:24px;
    cursor:pointer;
}

/* ===== DROPDOWN ===== */
.dropdown{
    display:none;
    position:absolute;
    right:40px;
    top:70px;
    background:#1e1e1e;
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 0 20px rgba(239,68,68,0.4);
    z-index:1000;
}

.dropdown a{
    display:block;
    padding:12px 20px;
    text-decoration:none;
    color:white;
}

.dropdown a:hover{
    background:var(--red-deep);
}

.dropdown.show{
    display:block;
}

/* ===== FORM CARD ===== */
.container{
    display:flex;
    justify-content:center;
    align-items:center;
    padding:60px 20px;
}

.card{
    background:var(--dark-soft);
    padding:40px;
    border-radius:20px;
    width:400px;
    box-shadow:0 0 25px rgba(239,68,68,0.2);
}

.card h2{
    margin-top:0;
    margin-bottom:25px;
}

label{
    display:block;
    margin-top:15px;
    font-size:14px;
    opacity:0.8;
}

input, select{
    width:100%;
    padding:10px;
    margin-top:5px;
    border-radius:8px;
    border:none;
    background: #2a2a2a;
    color: white;
}

.light-mode input, .light-mode select{
    background: #f1f5f9;
    color: black;
    border: 1px solid #cbd5e1;
}

button{
    margin-top:25px;
    width:100%;
    padding:12px;
    border:none;
    border-radius:10px;
    background:linear-gradient(90deg,var(--red-main),var(--red-dark));
    color:white;
    font-weight:600;
    cursor:pointer;
    transition:0.2s;
}

button:hover{
    transform:scale(1.05);
}

/* ===== LIGHT MODE ===== */
.light-mode{
    background:linear-gradient(135deg,var(--light-bg),var(--light-soft));
    color:black;
}

.light-mode header{
    background:linear-gradient(90deg,#ffffff,#e0f2fe);
    border-bottom:1px solid var(--blue-main);
}

.light-mode .logo{
    color:var(--blue-deep);
}

.light-mode .card{
    background:white;
    box-shadow:0 10px 25px rgba(14,165,233,0.2);
}

.light-mode button{
    background:linear-gradient(90deg,var(--blue-main),var(--blue-dark));
}

.light-mode .dropdown{
    background:white;
}

.light-mode .dropdown a{
    color:black;
}

</style>
</head>
<body>

<header>
    <div class="logo">GYMFITT ADMIN</div>
    <div class="hamburger" onclick="toggleMenu()">☰</div>

    <div class="dropdown" id="menu">
        <a href="dashboard.php">📊 Dashboard</a>
        <a href="members.php">👥 สมาชิก</a>
        <a href="#" onclick="toggleTheme()">🎨 เปลี่ยนธีม</a>
        <a href="logout.php">🚪 Logout</a>
    </div>
</header>

<div class="container">
    <div class="card">
        <h2>แก้ไข <?=htmlspecialchars($user['username'])?></h2>

        <form method="POST">

            <label>Level (Role)</label>
            <select name="level">
                <option value="member" <?=$user['level']=='member'?'selected':''?>>Member</option>
                <option value="admin" <?=$user['level']=='admin'?'selected':''?>>Admin</option>
            </select>

            <label>EXP</label>
            <input type="number" name="exp" value="<?=htmlspecialchars($user['exp'])?>" required>

            <label>Tokens (Coins)</label>
            <input type="number" name="tokens" value="<?=htmlspecialchars($user['tokens'] ?? 0)?>" required>

            <button name="save">บันทึกการแก้ไข</button>
        </form>
    </div>
</div>

<script>
function toggleMenu(){
    document.getElementById("menu").classList.toggle("show");
}

function toggleTheme(){
    document.body.classList.toggle("light-mode");
    localStorage.setItem("admin-theme", document.body.classList.contains("light-mode") ? "light" : "dark");
}

// ตรวจสอบธีมที่บันทึกไว้
(function initTheme() {
    const savedTheme = localStorage.getItem("admin-theme");
    if (savedTheme === "light") document.body.classList.add("light-mode");
})();
</script>

</body>
</html>