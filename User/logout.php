<?php
session_start();
session_unset();
session_destroy();

// Clear remember-me cookies
setcookie('customer_email', '', time() - 3600, '/');
setcookie('customer_token', '', time() - 3600, '/');

header("Location: index.php");
exit();

?>