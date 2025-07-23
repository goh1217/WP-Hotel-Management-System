<?php
require_once '../config.php';

// Destroy the session
session_unset();
session_destroy();

// Redirect to login page
header("Location: index.php");
exit();
?>