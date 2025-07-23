<?php
session_start();
require_once('connect/connection.php');

if (!isset($_GET['category_id'])) {
    echo "No category selected.";
    exit;
}

$category_id = intval($_GET['category_id']);

// Fetch category info including discount_percent
$category_sql = "
    SELECT 
        category_name,
        room_image,
        description AS category_description,
        price,
        hotel_id,
        discount
    FROM room_category
    WHERE category_id = $category_id
";

$category_result = mysqli_query($connect, $category_sql);
$category = mysqli_fetch_assoc($category_result);

if (!$category) {
    echo "Category not found.";
    exit;
}

// Get hotel name for better context
$hotel_query = mysqli_query($connect, "SELECT hotel_name FROM hotel WHERE hotel_id = " . $category['hotel_id']);
$hotel_data = mysqli_fetch_assoc($hotel_query);

// Initialize variables
$room = null;
$discount = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $check_in = $_POST['check_in_date'];
    $check_out = $_POST['check_out_date'];
    $today = date('Y-m-d');

    // Validate dates
    if ($check_in < $today || $check_out <= $check_in) {
        echo "<script>alert('Invalid date selection.');</script>";
    } else {
        // Find one available room in this category
        $room_sql = "
            SELECT * 
            FROM room 
            WHERE category_id = $category_id
            AND status = 'Available'
            AND room_id NOT IN (
                SELECT room_id FROM booking 
                WHERE ('$check_in' <= check_out_date AND '$check_out' >= check_in_date AND booking_status != 'cancelled')
            )
            LIMIT 1
        ";

        $room_result = mysqli_query($connect, $room_sql);
        $room = mysqli_fetch_assoc($room_result);

        if ($room) {
            $discount = isset($category['discount']) ? $category['discount'] : 0;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category['category_name']); ?> - Room Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../style.css">
    <style>
        .room-result-card {
            background: linear-gradient(135deg, #e8f5e8 0%, #f0fff4 100%);
            border: 2px solid #28a745;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.1);
            animation: slideInUp 0.5s ease-out;
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
            
            .room-detail-card,
            .search-form-card {
                padding: 20px;
            }
            
            .room-info-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
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
                        <?php echo htmlspecialchars($category['category_name']); ?>
                    </h2>
                    <p class="page-subtitle">Room details and booking at <?php echo htmlspecialchars($hotel_data['hotel_name']); ?></p>
                </div>
            </div>
        </div>

        <!-- Breadcrumb -->
        <nav class="breadcrumb-custom">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="hotel.php">Hotels</a></li>
                <li class="breadcrumb-item"><a href="view_categories.php?hotel_id=<?php echo $category['hotel_id']; ?>">Room Categories</a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($category['category_name']); ?></li>
            </ol>
        </nav>

        <!-- Back Button -->
        <a href="view_categories.php?hotel_id=<?php echo $category['hotel_id']; ?>" class="btn-back">
            <i class="fas fa-arrow-left me-2"></i>Back to Hotel Page
        </a>

        <!-- Room Details -->
        <div class="room-detail-card">
            <h3 class="section-title"><?php echo htmlspecialchars($hotel_data['hotel_name']); ?></h3>

            <img src="<?php echo 'Room Image/' . htmlspecialchars($category['room_image']); ?>" 
                 class="room-img" 
                 alt="<?php echo htmlspecialchars($category['category_name']); ?>">
            
            <h3 class="section-title">
                <i class="fas fa-info-circle me-2"></i>Room Information
            </h3>
            
            <div class="room-info-grid">
                <div class="room-info-item">
                    <div class="room-info-label">Room Type</div>
                    <div class="room-info-value"><?php echo htmlspecialchars($category['category_name']); ?></div>
                </div>
                <div class="room-info-item">
                    <div class="room-info-label">Price per Night</div>
                    <div class="room-info-value price-highlight">RM <?php echo number_format($category['price'], 2); ?></div>
                </div>
                <div class="room-info-item">
                    <div class="room-info-label">Discount</div>
                    <div class="room-info-value">
                        <?php if ($category['discount'] > 0): ?>
                            <span class="discount-badge"><?php echo $category['discount']; ?>% OFF</span>
                        <?php else: ?>
                            <span class="text-muted">No discount</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="room-description">
                <h5><i class="fas fa-file-alt me-2"></i>Description</h5>
                <p class="text-muted"><?php echo htmlspecialchars($category['category_description']); ?></p>
            </div>
        </div>

        <!-- Search Form -->
        <div class="search-form-card">
            <h4 class="section-title">
                <i class="fas fa-calendar-alt me-2"></i>Check Availability
            </h4>
            
            <form method="POST">
                <?php $today = date('Y-m-d'); ?>
                <div class="row">
                    <div class="col-md-5">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-calendar-plus me-2"></i>Check-In Date
                            </label>
                            <input type="date" name="check_in_date" class="form-control"
                                   value="<?php echo isset($_POST['check_in_date']) ? $_POST['check_in_date'] : $today; ?>" 
                                   min="<?php echo $today; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-calendar-minus me-2"></i>Check-Out Date
                            </label>
                            <input type="date" name="check_out_date" class="form-control"
                                   value="<?php echo isset($_POST['check_out_date']) ? $_POST['check_out_date'] : $today; ?>" 
                                   min="<?php echo $today; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="mb-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn-search w-100">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Search Results -->
        <?php if ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
            <?php if ($room): ?>
                <div id="roomResult" class="room-result-card">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="mb-1">
                                <i class="fas fa-door-open me-2"></i>
                                Room Available: <?php echo htmlspecialchars($room['room_number']); ?>
                            </h5>
                            <p class="text-success mb-0">
                                <i class="fas fa-check-circle me-1"></i>
                                This room is available for your selected dates
                            </p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-success">Available</span>
                        </div>
                    </div>
                    
                    <?php
                    $base_price = $category['price'];
                    $discounted_price = $base_price * (1 - $discount / 100);
                    $nights = (strtotime($check_out) - strtotime($check_in)) / 86400;
                    $total_price = $discounted_price * $nights;
                    ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="room-info-item">
                                <div class="room-info-label">Duration</div>
                                <div class="room-info-value"><?php echo $nights; ?> night(s)</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="room-info-item">
                                <div class="room-info-label">Total Cost</div>
                                <div class="room-info-value price-highlight">RM <?php echo number_format($total_price, 2); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($discount > 0): ?>
                        <div class="mt-3 p-3 bg-warning bg-opacity-10 border border-warning rounded">
                            <i class="fas fa-tag me-2 text-warning"></i>
                            <strong>Special Discount Applied: <?php echo $discount; ?>% OFF</strong>
                            <br>
                            <small class="text-muted">
                                Original price: RM <?php echo number_format($base_price * $nights, 2); ?> 
                                | You save: RM <?php echo number_format(($base_price * $nights) - $total_price, 2); ?>
                            </small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <a href="booking.php?room_id=<?php echo $room['room_id']; ?>&check_in=<?php echo $check_in; ?>&check_out=<?php echo $check_out; ?>" 
                           class="btn-book-now">
                            <i class="fas fa-calendar-plus me-2"></i>Book This Room
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div id="noRoomAlert" class="no-room-alert">
                    <i class="fas fa-exclamation-triangle fa-2x text-danger mb-3"></i>
                    <h5 class="text-danger">No Rooms Available</h5>
                    <p class="mb-0">Sorry, no rooms in this category are available for the selected dates. Please try different dates or choose another room category.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
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

// Smooth scroll to results
window.onload = function() {
    const roomResult = document.getElementById('roomResult');
    const noRoomAlert = document.getElementById('noRoomAlert');
    
    if (roomResult) {
        roomResult.scrollIntoView({ behavior: 'smooth' });
    } else if (noRoomAlert) {
        noRoomAlert.scrollIntoView({ behavior: 'smooth' });
    }
};
</script>
</body>
</html>