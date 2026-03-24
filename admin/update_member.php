<?php
session_start();

if(!isset($_SESSION['admin_id'])){
    header("Location: ../login.php");
    exit();
}

include "../config/config.php";

if($_SERVER["REQUEST_METHOD"] === "POST"){

    // เพิ่มการตรวจสอบค่า tokens ที่ส่งมาจากฟอร์ม
    if(isset($_POST['user_id']) && isset($_POST['username']) && isset($_POST['tokens'])){

        $id = intval($_POST['user_id']);
        $username = trim($_POST['username']);
        $tokens = intval($_POST['tokens']); // รับค่าจำนวนเหรียญ

        // ป้องกัน username ว่าง
        if($id > 0 && $username !== ""){

            // แก้ไขคำสั่ง SQL ให้ทำการอัปเดตทั้ง username และ tokens
            $stmt = $conn->prepare("UPDATE users SET username = ?, tokens = ? WHERE user_id = ?");
            $stmt->bind_param("sii", $username, $tokens, $id);

            if($stmt->execute()){
                $stmt->close();
                $conn->close();
                header("Location: members.php?success=1");
                exit();
            }else{
                echo "Update Error: " . $stmt->error;
                exit();
            }

        }else{
            echo "Invalid Data";
            exit();
        }
    }
}

header("Location: members.php");
exit();
?>