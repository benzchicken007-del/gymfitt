<?php
session_start();
$_SESSION['guest'] = true;
header("Location: guest_home.php");
exit();
?>
