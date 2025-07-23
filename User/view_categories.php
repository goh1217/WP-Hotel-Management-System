<?php
session_start();
include('connect/connection.php');

if (!isset($_GET['hotel_id'])) {
    echo "Invalid hotel.";
    exit;
}

$hotel_id = intval($_GET['hotel_id']);

// Get hotel details
$hotel_query = mysqli_query($connect, "SELECT * FROM hotel WHERE hotel_id = $hotel_id");
$hotel = mysqli_fetch_assoc($hotel_query);

// Fetch room categories for this hotel
$category_query = mysqli_query($connect, "SELECT * FROM room_category WHERE hotel_id = $hotel_id");

if (!$hotel) {
    echo "Hotel not found.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($hotel['hotel_name']); ?> - Room Categories</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../style.css">
    <style>
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
            
            .hotel-info-card,
            .feedback-section {
                padding: 20px;
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
                        <i class="fas fa-bed me-2"></i>
                        Room Categories
                    </h2>
                    <p class="page-subtitle">Choose your perfect room at <?php echo htmlspecialchars($hotel['hotel_name']); ?></p>
                </div>
                <div class="text-end">
                    <?php 
                    $count_query = mysqli_query($connect, "SELECT COUNT(*) as total FROM room_category WHERE hotel_id = $hotel_id");
                    $categories_count = mysqli_fetch_assoc($count_query)['total'];
                    ?>
                    <div class="categories-count">
                        <?php echo $categories_count; ?> Room Types Available
                    </div>
                </div>
            </div>
        </div>

        <!-- Back Button -->
        <a href="hotel.php" class="btn-back">
            <i class="fas fa-arrow-left me-2"></i>Back to Hotel List
        </a>

        <!-- Hotel Info -->
        <div class="hotel-info-card">
            <img src="Room Image/<?php echo htmlspecialchars($hotel['hotel_image']); ?>" class="hotel-img" alt="<?php echo htmlspecialchars($hotel['hotel_name']); ?>">
            <h2 class="hotel-title"><?php echo htmlspecialchars($hotel['hotel_name']); ?></h2>
            <p class="hotel-details">
                <i class="fas fa-map-marker-alt me-2 text-muted"></i>
                <strong>Address:</strong> <?php echo htmlspecialchars($hotel['address']); ?>
            </p>
            <div class="rating-display">
                <i class="fas fa-star text-warning me-2"></i>
                <strong>Average Rating:</strong> 
                <span class="text-warning">
                    <?php echo isset($hotel['average_rating']) ? number_format($hotel['average_rating'], 1) : "No ratings yet"; ?> / 5
                </span>
            </div>
        </div>

        <!-- Room Categories -->
        <div class="hotel-info-card">
            <h4 class="section-title">
                <i class="fas fa-door-open me-2"></i>Available Room Categories
            </h4>
            
            <?php if (mysqli_num_rows($category_query) == 0): ?>
                <div class="text-center py-5">
                    <i class="fas fa-bed fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Room Categories Available</h5>
                    <p class="text-muted">This hotel doesn't have any room categories set up yet.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php while ($room_category = mysqli_fetch_assoc($category_query)): ?>
                        <?php
                            $category_id = $room_category['category_id'];
                            $room_available = true; // just always allow user to continue
                        ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="room-category-card">
                                <img src="Room Image/<?php echo $room_category['room_image']; ?>" 
                                     class="room-img" 
                                     alt="<?php echo htmlspecialchars($room_category['category_name']); ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($room_category['category_name']); ?></h5>
                                    <p class="price-tag">
                                        <i class="fas fa-tag me-2"></i>
                                        RM<?php echo number_format($room_category['price'], 2); ?> per night
                                    </p>
                                    <a href="room_detail.php?category_id=<?php echo $room_category['category_id']; ?>" 
                                       class="btn-book-now">
                                        <i class="fas fa-calendar-plus me-2"></i>Book Now
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Feedback Section -->
        <div class="feedback-section">
            <h4 class="section-title">
                <i class="fas fa-comments me-2"></i>Customer Feedback
            </h4>
            
            <?php
            $feedback_query = mysqli_query($connect, "
                SELECT f.feedback_text, f.rating, f.created_at, c.name 
                FROM feedback f 
                JOIN customer c ON f.customer_id = c.customer_id 
                JOIN booking b ON f.booking_id = b.booking_id
                JOIN room r ON b.room_id = r.room_id
                WHERE r.hotel_id = $hotel_id
                ORDER BY f.created_at DESC
                LIMIT 10
            ");

            if (mysqli_num_rows($feedback_query) == 0): ?>
                <div class="no-feedback">
                    <i class="fas fa-comment-slash"></i>
                    <h5>No feedback available yet</h5>
                    <p>Be the first to leave a review for this hotel!</p>
                </div>
            <?php else: ?>
                <?php while ($fb = mysqli_fetch_assoc($feedback_query)): ?>
                    <div class="feedback-box">
                        <div class="feedback-author"><?php echo htmlspecialchars($fb['name']); ?></div>
                        <div class="feedback-date">
                            <i class="fas fa-calendar me-1"></i>
                            <?php echo date('F j, Y', strtotime($fb['created_at'])); ?>
                        </div>
                        <div class="feedback-rating">
                            <i class="fas fa-star me-1"></i>
                            Rating: <?php echo str_repeat("⭐", $fb['rating']); ?> (<?php echo $fb['rating']; ?>/5)
                        </div>
                        <div class="feedback-text"><?php echo nl2br(htmlspecialchars($fb['feedback_text'])); ?></div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
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
</script>
</body>
</html>