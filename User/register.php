<?php 
session_start();
include('connect/connection.php');

if(isset($_POST["register"])) {
    $name = trim($_POST["name"]);
    $phone = trim($_POST["phone"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Invalid email format'); window.history.back();</script>";
        exit();
    }

    $check_query = mysqli_query($connect, "SELECT * FROM customer WHERE email ='$email'");
    $rowCount = mysqli_num_rows($check_query);

    if(!empty($email) && !empty($password) && !empty($name) && !empty($phone)) {
        if($rowCount > 0) {
            echo "<script>alert('User with email already exists!'); window.history.back();</script>";
            exit();
        }
    $otp = rand(100000, 999999);
    $_SESSION['otp'] = $otp;
    $_SESSION['mail'] = $email;
    $_SESSION['name'] = $name;
    $_SESSION['phone'] = $phone;
    $_SESSION['password'] = password_hash($password, PASSWORD_BCRYPT);

    require "Mail/phpmailer/PHPMailerAutoload.php";
    $mail = new PHPMailer;

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 587;
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'tls';

    $mail->Username = 'fangyuanguu35@gmail.com';
    $mail->Password = 'vopl qjmm wdug womq';

    $mail->setFrom('fangyuanguu35@gmail.com', 'OTP Verification');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = "Your verify code";
    $mail->Body = "<p>Dear user, </p> <h3>Your verify OTP code is $otp <br></h3><br><br><p>Regards,</p><b>Our Team</b>";

    if(!$mail->send()) {
        echo "<script>alert('Failed to send OTP. Try again later.'); window.history.back();</script>";
        exit();
    } else {
        echo "<script>alert('OTP sent to $email'); window.location.replace('verification.php');</script>";
        exit();
    }
}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css" />
    <link rel="stylesheet" href="../style.css">
    <style>
        body, html {
            height: 100%;
            margin: 0;
            background: linear-gradient(135deg,rgb(221, 233, 247) 0%,rgb(115, 158, 232) 100%);
        }
        .form-control {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .btn-register {
            background: #667eea;
            color: #fff;
            border: none;
            padding: 12px;
            width: 100%;
            border-radius: 10px;
            font-weight: 600;
        }
        .btn-register:hover {
            background: rgb(56, 77, 170);
            color: #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .back-login {
            margin-top: 15px;
            text-align: center;
        }
        
        .position-relative {
            position: relative;
        }
        @media (max-width: 991.98px) {
            .card-split {
                flex-direction: column;
                width: 95vw;
            }
        }
    </style>
</head>
<body>
<div class="main-container">
    <div class="card-split">
        <div class="left-side">
            <i class="bi bi-person-plus"></i>
            <h1>Customer Register</h1>
            <p>Start your booking journey</p>
        </div>
        <div class="right-side">
            <h2 class="mb-4">Register</h2>
            <form action="" method="POST" name="register">
                <input type="text" name="name" class="form-control" placeholder="Full Name" required>
                <input type="text" name="phone" class="form-control" placeholder="Phone Number" required>
                <input type="email" name="email" class="form-control" placeholder="Email" required>
                <div class="position-relative">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <i class="bi bi-eye-slash" id="togglePassword"></i>
                </div>
                <button type="submit" name="register" class="btn btn-register">Register</button>
            </form>
            <div class="back-login">
                Already have an account? <a href="index.php">Login here</a>
            </div>
        </div>
    </div>
</div>
<script>
    const toggle = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    toggle.addEventListener('click', function () {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.classList.toggle('bi-eye');
        this.classList.toggle('bi-eye-slash');
    });
</script>
</body>
</html>
