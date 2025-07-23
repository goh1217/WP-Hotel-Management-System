<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Management System - Landing Page</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body, html {
            height: 100%;
            margin: 0;
            background: linear-gradient(135deg,rgb(221, 233, 247) 0%,rgb(115, 158, 232) 100%);
        }
        .main-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .left-side h1 {
            font-size: 2.8rem;
            font-weight: bold;
            margin-bottom: 0;
            text-align: left;
            width: 100%;
        }
        .right-side {
            flex: 1 1 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 30px;
            background: #fff;
        }
        .role-btn {
            width: 220px;
            margin: 18px 0;
            padding: 16px 0;
            font-size: 1.2rem;
            border: none;
            border-radius: 8px;
            background: #667eea;
            color: #fff;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .role-btn:hover {
            background:rgb(56, 77, 170);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .login-heading {
            color: #222;
            font-weight: 600;
            font-size: 2rem;
        }
        @media (max-width: 991.98px) {
            .card-split {
                flex-direction: column;
                width: 95vw;
                min-width: 0;
                /* Remove fixed height to allow natural content flow */
                min-height: auto;
            }
            .left-side {
                padding: 40px 20px;
                /* Set specific height for mobile to prevent overlap */
                min-height: 180px;
                /* Ensure content stays within bounds */
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .right-side {
                padding: 40px 20px;
                /* Ensure adequate spacing */
                min-height: 300px;
            }
            .left-side h1 {
                font-size: 2rem;
                text-align: center;
                /* Reduce line height for better mobile spacing */
                line-height: 1.2;
            }
            .login-heading {
                font-size: 1.5rem;
                /* Add top margin to create space from left side */
                margin-top: 10px;
            }
            .role-btn {
                /* Make buttons more mobile-friendly */
                width: 100%;
                max-width: 280px;
                margin: 12px 0;
            }
        }
        
        /* Additional fix for very small screens */
        @media (max-width: 576px) {
            .card-split {
                width: 98vw;
                margin: 10px;
            }
            .left-side {
                min-height: 150px;
                padding: 30px 15px;
            }
            .right-side {
                padding: 30px 15px;
                min-height: 280px;
            }
            .left-side h1 {
                font-size: 1.8rem;
            }
            .login-heading {
                font-size: 1.3rem;
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="card-split">
            <div class="left-side">
                <h1>Welcome<br>to Hotel Management!</h1>
            </div>
            <div class="right-side">
                <h2 class="mb-4 login-heading">Login as</h2>
                <a href="User/index.php" class="role-btn">Customer</a>
                <a href="Admin/index.php" class="role-btn">Admin</a>
            </div>
        </div>
    </div>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>