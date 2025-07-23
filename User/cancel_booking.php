<?php
session_start();
require_once('connect/connection.php');

if (!isset($_SESSION['customer_id'])) {
    header("Location: index.php");
    exit;
}

$customer_id = $_SESSION['customer_id'];

// Validate booking_id
if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
    echo "<div class='alert alert-danger'>Invalid booking.</div>";
    exit;
}

$booking_id = intval($_GET['booking_id']);

// Check ownership (no need to check payment table here)
$check = $connect->prepare("
    SELECT booking_id 
    FROM Booking 
    WHERE booking_id = ? AND customer_id = ?
");
$check->bind_param("ii", $booking_id, $customer_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    echo "<div class='alert alert-danger'>Unauthorized or non-existent booking.</div>";
    exit;
}

// Handle cancellation form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['confirm_cancel'])) {
    $reason = trim($_POST['cancel_reason']);
    $account_number = trim($_POST['account_number']);

    if (empty($reason) || empty($account_number)) {
        $error_message = "Please provide both cancellation reason and account number.";
    } else {
        // ✅ Update booking status only
        $stmt = $connect->prepare("UPDATE booking SET booking_status = 'requesting' WHERE booking_id = ?");
        $stmt->bind_param("i", $booking_id);
        
        if ($stmt->execute()) {
            // Store reason and account number
            $stmt2 = $connect->prepare("
                INSERT INTO cancellation_reason (booking_id, reason_text, account_number, cancelled_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt2->bind_param("iss", $booking_id, $reason, $account_number);
            $stmt2->execute();

            // Use PHP header redirect instead of JavaScript
            $_SESSION['success_message'] = "Cancellation request submitted successfully. Your booking is now under review.";
            header("Location: view_booking.php");
            exit;
        } else {
            $error_message = "Failed to submit cancellation request. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Cancel Booking</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            max-width: 600px;
        }
        .cancel-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-top: 50px;
        }
        .form-header {
            text-align: center;
            margin-bottom: 30px;
            color: #dc3545;
        }
        .form-header i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px;
        }
        .form-control:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="cancel-form">
        <div class="form-header">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Cancel Booking</h3>
            <p class="text-muted">Please provide the details below to process your cancellation request</p>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="alert alert-warning">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Important:</strong> Your booking status will be set to <strong>Pending Review</strong> until our team processes your cancellation request.
        </div>

        <form method="POST" onsubmit="return validateForm()">
            <div class="form-group mb-4">
                <label for="cancel_reason" class="font-weight-bold">
                    <i class="fas fa-comment me-2"></i>Cancellation Reason:
                </label>
                <textarea name="cancel_reason" id="cancel_reason" class="form-control" rows="4" 
                          placeholder="Please provide a detailed reason for cancellation..." required></textarea>
                <small class="form-text text-muted">Minimum 10 characters required</small>
            </div>
            
            <div class="form-group mb-4">
                <label for="account_number" class="font-weight-bold">
                    <i class="fas fa-university me-2"></i>Bank Account Number for Refund:
                </label>
                <input type="text" name="account_number" id="account_number" class="form-control" 
                       placeholder="Enter your bank account number" required 
                       pattern="\d{5,20}" title="Enter a valid account number (5-20 digits)">
                <small class="form-text text-muted">Only numbers are allowed (5-20 digits)</small>
            </div>
            
            <div class="text-center">
                <button type="submit" name="confirm_cancel" class="btn btn-danger me-3">
                    <i class="fas fa-check me-2"></i>Submit Cancellation Request
                </button>
                <a href="view_booking.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Go Back
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Add Font Awesome for icons -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>

<script>
function validateForm() {
    const reason = document.getElementById('cancel_reason').value.trim();
    const accountNumber = document.getElementById('account_number').value.trim();
    
    if (reason.length < 10) {
        alert('Please provide a more detailed cancellation reason (minimum 10 characters).');
        return false;
    }
    
    if (!/^\d{5,20}$/.test(accountNumber)) {
        alert('Please enter a valid bank account number (5-20 digits only).');
        return false;
    }
    
    return confirm('Are you sure you want to submit this cancellation request?');
}
</script>

</body>
</html>