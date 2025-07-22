<?php
session_start();
session_unset();
session_destroy();

// Pastikan tidak ada output sebelum header
header("Location: login.php");
exit();
?>
