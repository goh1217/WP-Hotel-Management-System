<?php
include('connect/connection.php');
include('auto_login.php');
session_start();

if (isset($_POST["login"])) {
    $email = mysqli_real_escape_string($connect, trim($_POST['email']));
    $password = trim($_POST['password']);

    $sql = mysqli_query($connect, "SELECT * FROM customer WHERE email = '$email'");
    $count = mysqli_num_rows($sql);

    if ($count > 0) {
        $fetch = mysqli_fetch_assoc($sql);
        $hashpassword = $fetch["password"];

        if ($fetch["status"] == 0) {
            echo "<script>alert('Please verify email account before login.');</script>";
        } else if (password_verify($password, $hashpassword)) {
            $_SESSION['customer_id'] = $fetch['customer_id'];
            $_SESSION['customer_name'] = $fetch['name'];

            if (!empty($_POST['remember'])) {
                $token = bin2hex(random_bytes(16));
                mysqli_query($connect, "UPDATE customer SET remember_token = '$token' WHERE email = '$email'");
                setcookie('customer_email', $email, time() + (86400 * 7), "/");
                setcookie('customer_token', $token, time() + (86400 * 7), "/");
            } else {
                setcookie('customer_email', '', time() - 3600, "/");
                setcookie('customer_token', '', time() - 3600, "/");
                mysqli_query($connect, "UPDATE customer SET remember_token = NULL WHERE email = '$email'");
            }

            header("Location: hotel.php");
            exit();
        } else {
            echo "<script>alert('Email or password invalid, please try again.');</script>";
        }
    } else {
        echo "<script>alert('Account does not exist.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../style.css">
    <style>
        body, html {
            height: 100%;
            margin: 0;
            background: linear-gradient(135deg,rgb(221, 233, 247) 0%,rgb(115, 158, 232) 100%);
        }
        .right-side {
            flex: 1;
            padding: 50px 40px;
            background: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .form-control {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .btn-login {
            background: #667eea;
            color: #fff;
            border: none;
            padding: 12px;
            width: 100%;
            border-radius: 10px;
            font-weight: 600;
        }
        .btn-login:hover {
            background: rgb(56, 77, 170);
            color: #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .form-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }
        .form-links a {
            font-size: 0.9rem;
            text-decoration: none;
        }
        .form-links a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 991.98px) {
            .card-split {
                flex-direction: column;
                width: 95vw;
            }
        }
        .position-relative {
            position: relative;
        }
    </style>
</head>
<body>
<div class="main-container">
    <div class="card-split">
        <div class="left-side position-relative">
            <a href="../index.php" class="back-circle">
                <i class="bi bi-arrow-left"></i>
            </a>
            <i class="bi bi-person-circle mt-4"></i>
            <h1>Customer Login</h1>
            <p>Access your bookings and profile.</p>
        </div>
        <div class="right-side">
            <h2 class="mb-4">Login</h2>
            <form method="POST" action="#">
                <label for="email" class="form-label">E-Mail Address</label>
                <input type="email" class="form-control" name="email" required value="<?php echo isset($_COOKIE['customer_email']) ? $_COOKIE['customer_email'] : ''; ?>">

                <label for="password" class="form-label">Password</label>
                <div class="position-relative">
                    <input type="password" class="form-control" name="password" id="password" required>
                    <i class="bi bi-eye-slash" id="togglePassword"></i>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>

                <button type="submit" name="login" class="btn btn-login">Login</button>

                <div class="form-links mt-3">
                    <a href="recover_psw.php">Forgot Password?</a>
                </div>

                <div class="text-center mt-3">
                    Don't have an account? <a href="register.php">Register</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script>
    const toggle = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    toggle.addEventListener('click', function () {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.classList.toggle('bi-eye');
    });
</script>
</body>
</html>
