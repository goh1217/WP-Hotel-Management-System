<?php
session_start();
include('connect/connection.php');

// Save token and email from URL to session (only on first load)
if (isset($_GET['token']) && isset($_GET['email'])) {
    $_SESSION['token'] = $_GET['token'];
    $_SESSION['email'] = $_GET['email'];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Password Reset</title>

    <!-- Bootstrap and CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container">
        <a class="navbar-brand" href="#">Password Reset Form</a>
    </div>
</nav>

<main class="login-form mt-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Reset Your Password</div>
                    <div class="card-body">
                        <form action="" method="POST">
                            <div class="form-group row">
                                <label for="password" class="col-md-4 col-form-label text-md-right">New Password</label>
                                <div class="col-md-6 position-relative">
                                    <input type="password" id="password" class="form-control" name="password" required autofocus>
                                    <i class="bi bi-eye-slash position-absolute" id="togglePassword" style="top: 50%; right: 10px; cursor: pointer;"></i>
                                </div>
                            </div>
                            <div class="col-md-6 offset-md-4 mt-3">
                                <input type="submit" value="Reset" name="reset" class="btn btn-primary">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    const toggle = document.getElementById('togglePassword');
    const password = document.getElementById('password');

    toggle.addEventListener('click', function () {
        const type = password.getAttribute("type") === "password" ? "text" : "password";
        password.setAttribute("type", type);
        this.classList.toggle("bi-eye");
        this.classList.toggle("bi-eye-slash");
    });
</script>

</body>
</html>

<?php
if (isset($_POST["reset"])) {
    $Email = $_SESSION['email'] ?? null;
    $token = $_SESSION['token'] ?? null;
    $psw = $_POST["password"];

    if ($Email && $psw) {
        $hash = password_hash($psw, PASSWORD_DEFAULT);
        $sql = mysqli_query($connect, "SELECT * FROM customer WHERE email='$Email'");
        $query = mysqli_num_rows($sql);

        if ($query > 0) {
            mysqli_query($connect, "UPDATE customer SET password='$hash' WHERE email='$Email'");
            // Optional: clear session values after successful reset
            unset($_SESSION['email']);
            unset($_SESSION['token']);
            ?>
            <script>
                alert("Your password has been successfully reset.");
                window.location.replace("index.php");
            </script>
            <?php
        } else {
            ?>
            <script>
                alert("Email not found in our records.");
            </script>
            <?php
        }
    } else {
        ?>
        <script>
            alert("Invalid reset request. Missing email or token.");
        </script>
        <?php
    }
}
?>
