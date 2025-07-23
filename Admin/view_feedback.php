<?php
require_once '../config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Count pending bookings that need attention
$pending_count = getPendingCount($conn);

// Get filter parameters
$filter_hotel_id = isset($_GET['filter_hotel']) ? intval($_GET['filter_hotel']) : 0;
$filter_category_id = isset($_GET['filter_category']) ? intval($_GET['filter_category']) : 0;
$filter_rating = isset($_GET['filter_rating']) ? intval($_GET['filter_rating']) : 0;

// Fetch all feedback with customer details, hotel and room information
$sql = "SELECT f.*, c.name as customer_name, c.email, 
               h.hotel_name, h.hotel_id,
               b.room_id,
               r.category_id,
               rc.category_name,
               b.booking_id
        FROM feedback f 
        JOIN customer c ON f.customer_id = c.customer_id 
        LEFT JOIN booking b ON f.booking_id = b.booking_id
        LEFT JOIN room r ON b.room_id = r.room_id
        LEFT JOIN room_category rc ON r.category_id = rc.category_id
        LEFT JOIN hotel h ON (f.hotel_id = h.hotel_id OR r.hotel_id = h.hotel_id)
        WHERE 1=1";

// Initialize arrays for prepared statement
$params = [];
$types = "";

// Apply hotel filter if selected - improved logic
if ($filter_hotel_id > 0) {
    $sql .= " AND (f.hotel_id = ? OR r.hotel_id = ?)";
    $params[] = $filter_hotel_id;
    $params[] = $filter_hotel_id;
    $types .= "ii";
}

// Apply category filter if selected
if ($filter_category_id > 0) {
    $sql .= " AND (r.category_id = ?)";
    $params[] = $filter_category_id;
    $types .= "i";
}

// Apply rating filter if selected
if ($filter_rating > 0) {
    $sql .= " AND (f.rating = ?)";
    $params[] = $filter_rating;
    $types .= "i";
}

$sql .= " ORDER BY f.updated_at DESC";

// Execute query properly with error handling
if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $feedback = mysqli_stmt_get_result($stmt);
        if (!$feedback) {
            error_log("Feedback query execution failed: " . mysqli_error($conn));
            $feedback = mysqli_query($conn, "SELECT f.*, c.name as customer_name, c.email FROM feedback f JOIN customer c ON f.customer_id = c.customer_id ORDER BY f.updated_at DESC");
        }
    } else {
        error_log("Feedback query preparation failed: " . mysqli_error($conn));
        $feedback = mysqli_query($conn, "SELECT f.*, c.name as customer_name, c.email FROM feedback f JOIN customer c ON f.customer_id = c.customer_id ORDER BY f.updated_at DESC");
    }
} else {
    $feedback = mysqli_query($conn, $sql);
    if (!$feedback) {
        error_log("Feedback query failed: " . mysqli_error($conn));
        $feedback = mysqli_query($conn, "SELECT f.*, c.name as customer_name, c.email FROM feedback f JOIN customer c ON f.customer_id = c.customer_id ORDER BY f.updated_at DESC");
    }
}

// Get feedback statistics
$total_feedback = mysqli_query($conn, "SELECT COUNT(*) as count FROM feedback");
$total_feedback_count = mysqli_fetch_assoc($total_feedback)['count'];

// Fetch hotels for filter dropdown
$hotels = mysqli_query($conn, "SELECT * FROM hotel ORDER BY hotel_name");

// Fetch categories for filter dropdown
$categories = mysqli_query($conn, "SELECT rc.*, h.hotel_name 
                                  FROM room_category rc 
                                  JOIN hotel h ON rc.hotel_id = h.hotel_id 
                                  ORDER BY h.hotel_name, rc.category_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Feedback - Hotel Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
            position: fixed; 
            top: 0;          
            left: 0;         
            width: 250px;    
            z-index: 1000;  
        }
        .sidebar .nav-link {
            color: white;
            border-radius: 10px;
            margin: 5px 0;
            padding: 10px 15px;           
            transition: all 0.3s ease;  
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2); 
            color: white;
            transform: translateX(5px); 
        }
        .main-content { 
            margin-left: 250px;  
            padding: 20px;
            min-height: 100vh;  
        }
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
        .feedback-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }
        .feedback-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 10px;
        }
        .customer-info {
            font-weight: bold;
            color: #333;
        }
        .feedback-date {
            color: #666;
            font-size: 0.9em;
        }
        .feedback-text {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            border: 1px solid #e0e0e0;
        }
        .rating-stars {
            color: #ffc107;
            margin-right: 5px;
        }
        .hotel-category-info {
            display: flex;
            align-items: center;
            margin-top: 10px;
            color: #666;
            font-size: 0.9em;
        }
        .hotel-info {
            margin-right: 15px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <div class="p-4">
                    <h4 class="text-center mb-4">
                        <i class="fas fa-hotel"></i> Admin Panel
                    </h4>
                    <hr class="text-white">
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
                        <a class="nav-link" href="manage_categories.php"><i class="fas fa-tags me-2"></i> Room Categories</a>
                        <a class="nav-link" href="manage_rooms.php"><i class="fas fa-bed me-2"></i> Room Details</a>
                        <a class="nav-link" href="manage_packages.php"><i class="fas fa-gift me-2"></i> Packages</a>
                        <a class="nav-link" href="view_bookings.php">
                            <i class="fas fa-calendar-check me-2"></i> Bookings
                            <?php if ($pending_count > 0): ?>
                                <span class="badge bg-danger ms-2"><?php echo $pending_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link active" href="view_feedback.php"><i class="fas fa-comments me-2"></i> Feedback</a>
                        <hr class="text-white">
                        <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="content-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-comments me-2"></i> Customer Feedback</h2>
                        <div>
                            <span class="badge bg-primary">Total Feedback: <?php echo $total_feedback_count; ?></span>
                        </div>
                    </div>
                    
                    <!-- Filter Section -->
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <form method="GET" id="filterForm" class="d-flex flex-column">
                                <label for="hotelFilter" class="form-label">Filter by Hotel:</label>
                                <select name="filter_hotel" class="form-select" id="hotelFilter" onchange="updateCategoryFilter()">
                                    <option value="0">All Hotels</option>
                                    <?php 
                                    mysqli_data_seek($hotels, 0);
                                    while ($hotel = mysqli_fetch_assoc($hotels)): 
                                    ?>
                                        <option value="<?php echo $hotel['hotel_id']; ?>" <?php echo ($filter_hotel_id == $hotel['hotel_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($hotel['hotel_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </form>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="categoryFilter" class="form-label">Filter by Room Category:</label>
                            <select name="filter_category" form="filterForm" class="form-select" id="categoryFilter" onchange="document.getElementById('filterForm').submit()">
                                <option value="0">All Categories</option>
                                <?php 
                                mysqli_data_seek($categories, 0);
                                while ($category = mysqli_fetch_assoc($categories)): 
                                    // Only show categories for the selected hotel, or all if no hotel selected
                                    if ($filter_hotel_id == 0 || $category['hotel_id'] == $filter_hotel_id):
                                ?>
                                    <option value="<?php echo $category['category_id']; ?>" <?php echo ($filter_category_id == $category['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['hotel_name'] . ' - ' . $category['category_name']); ?>
                                    </option>
                                <?php 
                                    endif;
                                endwhile; 
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="ratingFilter" class="form-label">Filter by Rating:</label>
                            <select name="filter_rating" form="filterForm" class="form-select" id="ratingFilter" onchange="document.getElementById('filterForm').submit()">
                                <option value="0">All Ratings</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($filter_rating == $i) ? 'selected' : ''; ?>>
                                        <?php echo $i; ?> Star<?php echo ($i > 1) ? 's' : ''; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <?php if (mysqli_num_rows($feedback) > 0): ?>
                        <?php while ($fb = mysqli_fetch_assoc($feedback)): ?>
                            <div class="feedback-card">
                                <div class="feedback-header">
                                    <div class="customer-info">
                                        <i class="fas fa-user me-2"></i>
                                        <?php echo $fb['customer_name']; ?>
                                        <small class="text-muted">
                                            (<i class="fas fa-envelope me-1"></i><?php echo $fb['email']; ?>)
                                        </small>
                                    </div>
                                    <div class="feedback-date">
                                        <i class="fas fa-calendar me-2"></i>
                                        <?php echo date('M d, Y g:i A', strtotime($fb['updated_at'])); ?>
                                    </div>
                                </div>
                                
                                <div class="feedback-text">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="rating-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php if ($i <= $fb['rating']): ?>
                                                    <i class="fas fa-star"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="rating-value"><?php echo $fb['rating']; ?>/5</div>
                                    </div>
                                    <i class="fas fa-quote-left me-2 text-muted"></i>
                                    <?php echo nl2br(htmlspecialchars($fb['feedback_text'])); ?>
                                    <i class="fas fa-quote-right ms-2 text-muted"></i>
                                </div>
                                
                                <div class="hotel-category-info">
                                    <?php if ($fb['hotel_name']): ?>
                                        <div class="hotel-info">
                                            <i class="fas fa-hotel me-1"></i> 
                                            <?php echo htmlspecialchars($fb['hotel_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($fb['category_name']): ?>
                                        <div class="category-info">
                                            <i class="fas fa-bed me-1"></i> 
                                            <?php echo htmlspecialchars($fb['category_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">No Feedback Yet</h4>
                            <p class="text-muted">Customer feedback will appear here once they start leaving reviews.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateCategoryFilter() {
            const hotelId = document.getElementById('hotelFilter').value;
            document.getElementById('categoryFilter').value = "0";
            document.getElementById('filterForm').submit();
        }
    </script>
</body>
</html>

