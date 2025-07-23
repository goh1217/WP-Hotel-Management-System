<?php
// if (!isset($_SESSION['customer_id']) && isset($_COOKIE['customer_email']) && isset($_COOKIE['customer_token'])) {
//     $email = $_COOKIE['customer_email'];
//     $token = $_COOKIE['customer_token'];

//     $sql = mysqli_query($connect, "SELECT * FROM customer WHERE email = '$email'");
//     $user = mysqli_fetch_assoc($sql);

//     if ($user && $user['remember_token'] === $token && $user['status'] == 1) {
//         $_SESSION['customer_id'] = $user['customer_id'];
//         $_SESSION['customer_name'] = $user['name'];
//         header("Location:hotel.php");
//         exit();
//     }
// }
if (!isset($_SESSION['customer_id']) && isset($_COOKIE['customer_email']) && isset($_COOKIE['customer_token'])) {
    $email = $_COOKIE['customer_email'];
    $token = $_COOKIE['customer_token'];

    $sql = mysqli_query($connect, "SELECT * FROM customer WHERE email = '$email'");
    $user = mysqli_fetch_assoc($sql);

    if ($user && $user['remember_token'] === $token && $user['status'] == 1) {
        $_SESSION['customer_id'] = $user['customer_id'];
        $_SESSION['customer_name'] = $user['name'];
        // Only redirect if on index.php
        if (basename($_SERVER['PHP_SELF']) == 'index.php') {
            header("Location:hotel.php");
            exit();
        }
        // On other pages, just restore session and continue
    }
}
?>
