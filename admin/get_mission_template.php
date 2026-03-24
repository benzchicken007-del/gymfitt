<?php
include "config/config.php";

$mission_id = $_GET['id'];

$stmt = $conn->prepare("SELECT angle_template FROM missions WHERE id=?");
$stmt->bind_param("i",$mission_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo $row['angle_template'];
?>
