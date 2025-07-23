<?php session_start() ?>

<link href="//maxcdn.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css" rel="stylesheet" id="bootstrap-css">
<script src="//maxcdn.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<!------ Include the above in your HEAD tag ---------->

<!doctype html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Google Fonts & Bootstrap -->
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Raleway:300,400,600" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">

    <!-- Optional Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css" />

    <!-- Custom Styles -->
    <style>
        body, html {
            height: 100%;
            margin: 0;
            background: linear-gradient(135deg, rgb(221, 233, 247) 0%, rgb(115, 158, 232) 100%);
            font-family: 'Raleway', sans-serif;
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
        .login-form {
            margin-top: 50px;
        }
        .navbar {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
    </style>
    <title>Verification</title>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container">
        <a class="navbar-brand font-weight-bold" href="#">OTP Verification</a>
    </div>
</nav>

<main class="login-form">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-white font-weight-bold">Verify Your Account</div>
                    <div class="card-body">
                        <form action="#" method="POST">
                            <div class="form-group row">
                                <label for="otp" class="col-md-4 col-form-label text-md-right">OTP Code</label>
                                <div class="col-md-6">
                                    <input type="text" id="otp" class="form-control" name="otp_code" required autofocus>
                                </div>
                            </div>

                            <div class="form-group row mb-0 mt-3">
                                <div class="col-md-6 offset-md-4">
                                    <input type="submit" class="btn btn-register" value="Verify" name="verify">
                                </div>
                            </div>
                        </form>
                        <div class="text-center mt-3">
                            <a href="register.php">← Back to Register</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>  
</main>

</body>
</html>
<?php 
include('connect/connection.php');

if(isset($_POST["verify"])) {
    $otp = $_SESSION['otp'];
    $otp_code = $_POST['otp_code'];

    if($otp != $otp_code){
        echo "<script>alert('Invalid OTP code');</script>";
    } else {
        $name = $_SESSION['name'];
        $email = $_SESSION['mail'];
        $phone = $_SESSION['phone'];
        $password = $_SESSION['password'];

        // Insert into customer table
        $insert = mysqli_query($connect, "INSERT INTO customer (name, phone, email, password, status) 
                                          VALUES ('$name', '$phone', '$email', '$password', 1)");

        if ($insert) {
            // Clear session after success
            unset($_SESSION['otp'], $_SESSION['mail'], $_SESSION['name'], $_SESSION['phone'], $_SESSION['password']);
            echo "<script>alert('Account verified and registered successfully!'); window.location='index.php';</script>";
        } else {
            echo "<script>alert('Database error during account creation');</script>";
        }
    }
}
?>
