<?php
session_start();
require_once('connect/connection.php');

// Check if pending booking data exists
if (!isset($_SESSION['pending_booking'])) {
    header("Location: hotel.php");
    exit;
}

$booking_data = $_SESSION['pending_booking'];
$room_id = $booking_data['room_id'];
$package_id = $booking_data['package_id'];
$total_amount = $booking_data['total_amount'];
$check_in = $booking_data['check_in_date'];
$check_out = $booking_data['check_out_date'];
$booking_date = $booking_data['booking_date'];
$customer_id = $booking_data['customer_id'];

// Get room and hotel details for context
$room_query = mysqli_query($connect, "
    SELECT r.room_number, c.category_name, h.hotel_name, r.hotel_id, r.category_id
    FROM room r 
    JOIN room_category c ON r.category_id = c.category_id 
    JOIN hotel h ON r.hotel_id = h.hotel_id 
    WHERE r.room_id = $room_id
");
$room_details = mysqli_fetch_assoc($room_query);

// Calculate days
$days = (strtotime($check_out) - strtotime($check_in)) / 86400;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["upload"])) {
    $targetDir = "payment_proofs/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $imageFileType = strtolower(pathinfo($_FILES["proof"]["name"], PATHINFO_EXTENSION));
    $allowedTypes = ["jpg", "jpeg", "png", "gif"];

    if (in_array($imageFileType, $allowedTypes)) {
        $fileName = uniqid("proof_", true) . "." . $imageFileType;
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES["proof"]["tmp_name"], $targetFile)) {
            // Insert booking
            $stmt = $connect->prepare("INSERT INTO Booking (customer_id, room_id, package_id, booking_date, check_in_date, check_out_date, payment_status, total_amount) VALUES (?, ?, ?, ?, ?, ?, 'Paid', ?)");
            $stmt->bind_param("iiisssd", $customer_id, $room_id, $package_id, $booking_date, $check_in, $check_out, $total_amount);
            $stmt->execute();
            $booking_id = $stmt->insert_id;

            // Insert payment_proof
            $stmt2 = $connect->prepare("INSERT INTO payment_proof (booking_id, image_name) VALUES (?, ?)");
            $stmt2->bind_param("is", $booking_id, $fileName);
            $stmt2->execute();

            // Insert payment
            $payment_date = date('Y-m-d');
            $stmt3 = $connect->prepare("INSERT INTO Payment (booking_id, payment_date, amount, method) VALUES (?, ?, ?, 'TNG QR')");
            $stmt3->bind_param("isd", $booking_id, $payment_date, $total_amount);
            $stmt3->execute();

            unset($_SESSION['pending_booking']);

            echo "<script>
                alert('Payment successful! Your booking has been confirmed.');
                window.location.href='view_booking.php';
            </script>";
            exit;
        } else {
            $error_message = "Failed to upload payment proof. Please try again.";
        }
    } else {
        $error_message = "Only JPG, JPEG, PNG, and GIF files are allowed.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Complete Your Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../style.css">
    <style>
        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border: 2px solid #dc3545;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }
            
            .wrapper {
                flex-direction: column;
            }
            
            .payment-card,
            .upload-section {
                padding: 20px;
            }
            
            .qr-container {
                padding: 20px;
            }
            
            .qr-code {
                max-width: 250px;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <div class="sidebar-content">
            <h4>
                <i class="fas fa-user-circle me-2"></i>Customer Panel
            </h4>
            <hr class="text-white mb-4">
            
            <nav class="nav flex-column">
                <a class="nav-link active" href="hotel.php">
                    <i class="fas fa-hotel"></i>
                    Hotels
                </a>
                <a class="nav-link" href="view_booking.php">
                    <i class="fas fa-calendar-check"></i>
                    View Bookings
                </a>
                <hr class="text-white">
                <a class="nav-link" href="#" onclick="confirmLogout(event)">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </nav>
        </div>
    </div>

    <div class="main-content">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h2 class="page-title">
                        <i class="fas fa-credit-card me-2"></i>
                        Payment Gateway
                    </h2>
                    <p class="page-subtitle">Complete your payment for Room <?php echo htmlspecialchars($room_details['room_number']); ?> at <?php echo htmlspecialchars($room_details['hotel_name']); ?></p>
                </div>
            </div>
        </div>

        <!-- Breadcrumb -->
        <nav class="breadcrumb-custom">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="hotel.php">Hotels</a></li>
                <li class="breadcrumb-item"><a href="view_categories.php?hotel_id=<?php echo $room_details['hotel_id']; ?>">Room Categories</a></li>
                <li class="breadcrumb-item"><a href="room_detail.php?category_id=<?php echo $room_details['category_id']; ?>">Room Details</a></li>
                <li class="breadcrumb-item"><a href="booking.php?room_id=<?php echo $room_id; ?>&check_in=<?php echo $check_in; ?>&check_out=<?php echo $check_out; ?>">Booking</a></li>
                <li class="breadcrumb-item active">Payment</li>
            </ol>
        </nav>

        <!-- Back Button -->
        <a href="booking.php?room_id=<?php echo $room_id; ?>&check_in=<?php echo $check_in; ?>&check_out=<?php echo $check_out; ?>" class="btn-back">
            <i class="fas fa-arrow-left me-2"></i>Back to Booking
        </a>

        <?php if (isset($error_message)): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Error:</strong> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Booking Summary -->
        <div class="booking-summary">
            <h4 class="section-title">
                <i class="fas fa-file-invoice me-2"></i>Booking Summary
            </h4>
            
            <div class="summary-table">
                <table class="table">
                    <tbody>
                        <tr>
                            <td class="summary-header">
                                <i class="fas fa-hotel me-2"></i>Hotel
                            </td>
                            <td class="summary-value"><?php echo htmlspecialchars($room_details['hotel_name']); ?></td>
                        </tr>
                        <tr>
                            <td class="summary-header">
                                <i class="fas fa-door-open me-2"></i>Room
                            </td>
                            <td class="summary-value"><?php echo htmlspecialchars($room_details['room_number']) . ' - ' . htmlspecialchars($room_details['category_name']); ?></td>
                        </tr>
                        <tr>
                            <td class="summary-header">
                                <i class="fas fa-calendar-plus me-2"></i>Check-In
                            </td>
                            <td class="summary-value"><?php echo date('F j, Y (l)', strtotime($check_in)); ?></td>
                        </tr>
                        <tr>
                            <td class="summary-header">
                                <i class="fas fa-calendar-minus me-2"></i>Check-Out
                            </td>
                            <td class="summary-value"><?php echo date('F j, Y (l)', strtotime($check_out)); ?></td>
                        </tr>
                        <tr>
                            <td class="summary-header">
                                <i class="fas fa-moon me-2"></i>Duration
                            </td>
                            <td class="summary-value"><?php echo $days; ?> night(s)</td>
                        </tr>
                        <tr>
                            <td class="summary-header">
                                <i class="fas fa-money-bill-wave me-2"></i>Total Amount
                            </td>
                            <td class="summary-value">RM <?php echo number_format($total_amount, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- QR Code Section -->
        <div class="payment-card">
            <h4 class="section-title">
                <i class="fas fa-qrcode me-2"></i>Scan to Pay
            </h4>
            
            <div class="qr-container">
                <h5 class="mb-3">
                    <i class="fas fa-mobile-alt me-2"></i>TouchNGo eWallet Payment
                </h5>
                <p class="text-muted mb-3">Scan this QR code with your TouchNGo eWallet app</p>
                
                <img src="images/paymentQr.png" alt="TouchNGo QR Code" class="qr-code">
                
                <div class="mt-3">
                    <div class="d-flex justify-content-center align-items-center">
                        <span class="badge bg-success me-2">Amount to Pay:</span>
                        <strong class="h5 mb-0 text-success">RM <?php echo number_format($total_amount, 2); ?></strong>
                    </div>
                </div>
            </div>

            <!-- Payment Instructions -->
            <div class="payment-instructions">
                <div class="d-flex align-items-center">
                    <i class="fas fa-info-circle icon"></i>
                    <div>
                        <strong>Payment Instructions:</strong>
                        <p class="mb-0 mt-2">1. Scan the QR code below using your TouchNGo eWallet or preferred payment app<br>
                        2. Complete the payment for the exact amount shown<br>
                        3. Take a screenshot of the payment confirmation<br>
                        4. Upload the screenshot as proof of payment</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Proof Section -->
        <div class="upload-section">
            <h4 class="section-title">
                <i class="fas fa-upload me-2"></i>Upload Payment Proof
            </h4>
            
            <form method="POST" enctype="multipart/form-data" id="paymentForm">
                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-camera me-2"></i>Upload Payment Screenshot
                    </label>
                    <p class="text-muted small mb-3">Accepted formats: JPG, JPEG, PNG, GIF (Max size: 5MB)</p>
                    
                    <div class="file-upload-wrapper">
                        <input type="file" name="proof" class="file-upload-input" accept=".jpg,.jpeg,.png,.gif" required id="fileInput">
                        <button type="button" class="file-upload-button">
                            <i class="fas fa-cloud-upload-alt me-2"></i>
                            Choose Payment Screenshot
                        </button>
                    </div>
                    <div class="file-name-display" id="fileNameDisplay" style="display: none;">
                        <i class="fas fa-file-image me-2"></i>
                        <span id="fileName">No file selected</span>
                    </div>
                </div>

                <button type="submit" name="upload" class="btn-submit">
                    <i class="fas fa-check-circle me-2"></i>Submit Payment Proof & Complete Booking
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Custom Logout Confirmation Modal -->
<div class="logout-modal-overlay" id="logoutModal">
    <div class="logout-modal">
        <div class="logout-modal-header">
            <h5 class="logout-modal-title">Confirm Logout</h5>
            <button class="logout-modal-close" onclick="closeLogoutModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="logout-modal-body">
            <p class="logout-modal-message">Are you sure you want to logout?</p>
            <div class="logout-modal-buttons">
                <button class="logout-btn-cancel" onclick="closeLogoutModal()">Cancel</button>
                <button class="logout-btn-confirm" onclick="proceedLogout()">Yes, Logout</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmLogout(event) {
    event.preventDefault();
    
    // Show the custom modal
    document.getElementById('logoutModal').style.display = 'flex';
    
    // Add event listener to close modal when clicking overlay
    document.getElementById('logoutModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeLogoutModal();
        }
    });
}

function closeLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
}

function proceedLogout() {
    // Close modal and redirect to logout
    closeLogoutModal();
    window.location.href = 'logout.php';
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLogoutModal();
    }
});

// File upload handling
document.getElementById('fileInput').addEventListener('change', function() {
    const fileNameDisplay = document.getElementById('fileNameDisplay');
    const fileName = document.getElementById('fileName');
    
    if (this.files && this.files[0]) {
        fileName.textContent = this.files[0].name;
        fileNameDisplay.style.display = 'block';
    } else {
        fileNameDisplay.style.display = 'none';
    }
});

</script>
</body>
</html>