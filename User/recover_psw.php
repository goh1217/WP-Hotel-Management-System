<?php session_start(); ?>
<link href="//maxcdn.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css" rel="stylesheet" id="bootstrap-css">
<script src="//maxcdn.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css" />

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="https://fonts.googleapis.com/css?family=Raleway:300,400,600" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <title>Password Recovery - Customer</title>
    <style>
        body, html {
            height: 100%;
            margin: 0;
            background: linear-gradient(135deg, rgb(221, 233, 247) 0%, rgb(115, 158, 232) 100%);
        }
        .form-control {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .btn-recover {
            background: #667eea;
            color: #fff;
            border: none;
            padding: 12px;
            width: 100%;
            border-radius: 10px;
            font-weight: 600;
        }
        .btn-recover:hover {
            background: rgb(56, 77, 170);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .back-link {
            margin-top: 20px;
            text-align: center;
        }
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
<div class="main-container">
    <div class="card-split">
        <div class="left-side">
            <i class="bi bi-lock"></i>
            <h1>Password Recovery</h1>
            <p>Reset access to your account securely</p>
        </div>
        <div class="right-side">
            <h2 class="mb-4">Recover Password</h2>
            <form action="#" method="POST" name="recover_psw">
                <label for="email_address" class="form-label">E-Mail Address</label>
                <input type="text" id="email_address" class="form-control" name="email" required autofocus>
                <button type="submit" class="btn btn-recover" name="recover">Recover</button>
                <div class="back-link">
                    <a href="index.php"><i class="bi bi-arrow-left"></i> Back to Login</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>

<?php 
if (isset($_POST["recover"])) {
    include('connect/connection.php');
    $email = $_POST["email"];

    $sql = mysqli_query($connect, "SELECT * FROM customer WHERE email='$email'");
    $fetch = mysqli_fetch_assoc($sql);

    if (mysqli_num_rows($sql) <= 0) {
        echo "<script>alert('Sorry, no such email exists');</script>";
    } else if ($fetch["status"] == 0) {
        echo "<script>
            alert('Sorry, your account must be verified before recovering your password!');
            window.location.replace('index.php');
        </script>";
    } else {
        $otp = rand(100000, 999999);
        $_SESSION['otp'] = $otp;
        $_SESSION['email'] = $email;

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
        $mail->Subject = "Your OTP for Password Reset";
        $mail->Body = "
            <b>Dear User</b><br>
            <p>Your One-Time Password (OTP) for resetting your password is:</p>
            <h2>$otp</h2>
            <p>This OTP is valid for only one attempt. Please do not share it with anyone.</p>
            <br>
            <p>Our Dear customers<br><b>Hope you have a good experience using our system</b></p>
        ";

        if (!$mail->send()) {
            echo "<script>alert('Invalid Email. OTP could not be sent.');</script>";
        } else {
            echo "<script>
                alert('OTP sent to your email. Please check your inbox.');
                window.location.replace('verify_forgotpassword.php');
            </script>";
        }
    }
}
?>
