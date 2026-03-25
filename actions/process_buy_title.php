<?php
session_start();
include "config/config.php";

if(!isset($_SESSION['user_id']) || !isset($_GET['title_id'])){
    header("Location: home.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$title_id = (int)$_GET['title_id'];

// 1. ตรวจสอบราคาฉายา
$title_query = $conn->query("SELECT price FROM shop_titles WHERE title_id = $title_id");
$title = $title_query->fetch_assoc();

if(!$title) {
    header("Location: home.php?buy_status=error");
    exit();
}

$price = (int)$title['price'];

// 2. ตรวจสอบ Token ของ User
$user_query = $conn->query("SELECT tokens FROM users WHERE user_id = $user_id");
$user = $user_query->fetch_assoc();

if($user['tokens'] < $price) {
    header("Location: home.php?buy_status=insufficient");
    exit();
}

// 3. เริ่มกระบวนการ Transaction (หักเงิน + เพิ่มฉายา + ตั้งเป็น Active)
$conn->begin_transaction();

try {
    // หักเงิน และตั้งฉายาที่ซื้อใหม่เป็น Active ทันที
    $conn->query("UPDATE users SET tokens = tokens - $price, title_active_id = $title_id WHERE user_id = $user_id");
    
    // บันทึกลงตารางผู้ครอบครองฉายา
    $conn->query("INSERT INTO user_titles (user_id, title_id) VALUES ($user_id, $title_id)");
    
    $conn->commit();
    header("Location: home.php?buy_status=success");
} catch (Exception $e) {
    $conn->rollback();
    header("Location: home.php?buy_status=error");
}
exit();