<?php
session_start();
if(!isset($_SESSION['admin_id'])){
    header("Location: ../login.php");
    exit();
}

include "../config/config.php";

if(isset($_GET['id'])){
    $id = intval($_GET['id']);

    // กันลบ admin
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id=? AND role='member'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

header("Location: members.php");
exit();
?>
