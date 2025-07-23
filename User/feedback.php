<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once('connect/connection.php');

// Redirect if not logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: index.php");
    exit;
}

$customer_id = $_SESSION['customer_id'];

// Validate booking ID
if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
    echo "<div class='alert alert-danger'>Invalid booking.</div>";
    exit;
}

$booking_id = intval($_GET['booking_id']);

// Fetch booking details for context
$query = "
    SELECT b.booking_id, b.check_in_date, b.check_out_date, b.total_amount,
           r.hotel_id, r.room_number, 
           rc.category_name,
           h.hotel_name
    FROM Booking b 
    JOIN Room r ON b.room_id = r.room_id 
    JOIN room_category rc ON r.category_id = rc.category_id
    JOIN hotel h ON r.hotel_id = h.hotel_id
    WHERE b.booking_id = ? AND b.customer_id = ?
";
$stmt = $connect->prepare($query);
$stmt->bind_param("ii", $booking_id, $customer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<div class='alert alert-danger'>Booking not found or not authorized.</div>";
    exit;
}

$booking_details = $result->fetch_assoc();
$hotel_id = $booking_details['hotel_id'];

// Check if feedback already exists
$existing_feedback = null;
$stmt_check = $connect->prepare("SELECT feedback_text, rating FROM feedback WHERE customer_id = ? AND booking_id = ?");
$stmt_check->bind_param("ii", $customer_id, $booking_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    $existing_feedback = $result_check->fetch_assoc();
}

// Handle submission
$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $feedback_text = trim($_POST['feedback_text']);
    $rating = intval($_POST['rating']);
    $submitted_date = date('Y-m-d H:i:s');

    if ($rating < 1 || $rating > 5 || empty($feedback_text)) {
        $error_message = 'Please fill in all fields and choose a rating between 1 and 5.';
    } else {
        if ($existing_feedback) {
            // Update existing feedback
            $stmt = $connect->prepare("UPDATE feedback SET feedback_text = ?, rating = ?, submitted_date = ?, created_at = ?, updated_at = ?, hotel_id = ? WHERE customer_id = ? AND booking_id = ?");
            $stmt->bind_param("sisssiii", $feedback_text, $rating, $submitted_date, $submitted_date, $submitted_date, $hotel_id, $customer_id, $booking_id);
        } else {
            // Insert new feedback
            $stmt = $connect->prepare("INSERT INTO feedback (customer_id, feedback_text, submitted_date, created_at, updated_at, rating, booking_id, hotel_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssiii", $customer_id, $feedback_text, $submitted_date, $submitted_date, $submitted_date, $rating, $booking_id, $hotel_id);
        }

        if ($stmt->execute()) {
            // Update hotel average rating
            $stmt_avg = $connect->prepare("
                SELECT AVG(f.rating) AS avg_rating 
                FROM feedback f 
                JOIN Booking b ON f.booking_id = b.booking_id 
                JOIN Room r ON b.room_id = r.room_id 
                WHERE r.hotel_id = ?
            ");
            $stmt_avg->bind_param("i", $hotel_id);
            $stmt_avg->execute();
            $result_avg = $stmt_avg->get_result();

            if ($row_avg = $result_avg->fetch_assoc()) {
                $new_avg = round($row_avg['avg_rating'], 2);
                $stmt_update = $connect->prepare("UPDATE hotel SET average_rating = ? WHERE hotel_id = ?");
                $stmt_update->bind_param("di", $new_avg, $hotel_id);
                $stmt_update->execute();
            }

            echo "<script>alert('Thank you for your feedback!'); window.location.href='view_booking.php';</script>";
            exit;
        } else {
            $error_message = 'An error occurred while submitting your feedback. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $existing_feedback ? 'Edit' : 'Submit' ?> Feedback - <?= htmlspecialchars($booking_details['hotel_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../style.css">
    <style>    
        .booking-info-text {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .booking-info-text p {
            margin: 0;
            line-height: 1.6;
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
            
            .feedback-card {
                padding: 20px;
            }
            
            .star-rating {
                flex-direction: column;
            }
            
            .star-option {
                min-width: 100%;
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
                <a class="nav-link" href="hotel.php">
                    <i class="fas fa-hotel"></i>
                    Hotels
                </a>
                <a class="nav-link active" href="view_booking.php">
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
                        <i class="fas fa-star me-2"></i>
                        <?= $existing_feedback ? 'Edit Your Feedback' : 'Share Your Experience' ?>
                    </h2>
                    <p class="page-subtitle">Help us improve by sharing your thoughts about your stay</p>
                </div>
            </div>
        </div>

        <!-- Breadcrumb -->
        <nav class="breadcrumb-custom">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="view_booking.php">View Bookings</a></li>
                <li class="breadcrumb-item active"><?= $existing_feedback ? 'Edit Feedback' : 'Submit Feedback' ?></li>
            </ol>
        </nav>

        <!-- Back Button -->
        <a href="view_booking.php" class="btn-back">
            <i class="fas fa-arrow-left me-2"></i>Back to Bookings
        </a>

        <?php if (!empty($success_message)): ?>
            <div class="alert-success-custom">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Success:</strong> <?= $success_message ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert-error-custom">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Error:</strong> <?= $error_message ?>
            </div>
        <?php endif; ?>

        <!-- Feedback Form -->
        <div class="feedback-card">
            <h4 class="section-title">
                <i class="fas fa-comment-dots me-2"></i><?= $existing_feedback ? 'Edit Your Feedback' : 'Your Feedback' ?>
            </h4>
            
            <!-- Booking Information as Text -->
            <div class="booking-info-text mb-4">
                <p class="text-muted mb-2">
                    <strong>Booking Details:</strong> 
                    <?= htmlspecialchars($booking_details['hotel_name']) ?> - 
                    Room <?= htmlspecialchars($booking_details['room_number']) ?> (<?= htmlspecialchars($booking_details['category_name']) ?>) | 
                    <?= date('M j, Y', strtotime($booking_details['check_in_date'])) ?> to <?= date('M j, Y', strtotime($booking_details['check_out_date'])) ?> | 
                    Total: RM <?= number_format($booking_details['total_amount'], 2) ?>
                </p>
            </div>
            
            <form method="POST" id="feedbackForm">
                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-star me-2"></i>How would you rate your experience?
                    </label>
                    <div class="rating-container">
                        <div class="star-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label class="star-option <?= (isset($existing_feedback['rating']) && $existing_feedback['rating'] == $i) ? 'selected' : '' ?>" data-rating="<?= $i ?>">
                                    <input type="radio" name="rating" value="<?= $i ?>" <?= (isset($existing_feedback['rating']) && $existing_feedback['rating'] == $i) ? 'checked' : '' ?> required>
                                    <i class="fas fa-star star-icon"></i>
                                    <span><?= $i ?> Star<?= $i > 1 ? 's' : '' ?></span>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-edit me-2"></i>Tell us about your experience
                    </label>
                    <textarea name="feedback_text" class="form-control" rows="6" placeholder="Share your thoughts about the hotel, room, service, amenities, or anything else that would help future guests..." required maxlength="1000" id="feedbackText"><?= htmlspecialchars($existing_feedback['feedback_text'] ?? '') ?></textarea>
                    <div class="textarea-counter">
                        <span id="charCount"><?= strlen($existing_feedback['feedback_text'] ?? '') ?></span>/1000 characters
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-<?= $existing_feedback ? 'edit' : 'paper-plane' ?> me-2"></i>
                    <?= $existing_feedback ? 'Update Feedback' : 'Submit Feedback' ?>
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

// Star rating functionality
document.querySelectorAll('.star-option').forEach(option => {
    option.addEventListener('click', function() {
        // Remove selected class from all options
        document.querySelectorAll('.star-option').forEach(opt => opt.classList.remove('selected'));
        
        // Add selected class to clicked option
        this.classList.add('selected');
        
        // Check the radio button
        this.querySelector('input[type="radio"]').checked = true;
    });
});

// Character counter for textarea
const feedbackText = document.getElementById('feedbackText');
const charCount = document.getElementById('charCount');

feedbackText.addEventListener('input', function() {
    const currentLength = this.value.length;
    charCount.textContent = currentLength;
    
    // Change color when approaching limit
    if (currentLength > 800) {
        charCount.style.color = '#dc3545';
    } else if (currentLength > 600) {
        charCount.style.color = '#ffc107';
    } else {
        charCount.style.color = '#6c757d';
    }
});

// Form submission handling
document.getElementById('feedbackForm').addEventListener('submit', function(e) {
    const rating = document.querySelector('input[name="rating"]:checked');
    const feedback = document.getElementById('feedbackText').value.trim();
    
    if (!rating) {
        e.preventDefault();
        alert('Please select a rating before submitting.');
        return false;
    }
    
    // Show loading state
    const submitBtn = document.querySelector('.btn-submit');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
    submitBtn.disabled = true;
});
</script>
</body>
</html>