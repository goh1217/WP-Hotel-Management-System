<?php
session_start();
include('connect/connection.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel List - Customer Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../style.css">
    <style>
        .card-text {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .rating-text {
            font-weight: bold;
            color: #f39c12;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .rating-text i {
            margin-right: 5px;
        }
        
        .btn-view-more {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-view-more:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .text-muted-custom {
            color: #999 !important;
            font-style: italic;
        }
        
        .hotel-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
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
            }
            
            .wrapper {
                flex-direction: column;
            }
            
            .hotel-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 991.98px) {
            .hotel-grid {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            }
        }
        
        .page-title {
            color: #333;
            font-weight: bold;
            margin-bottom: 0;
        }
        
        .page-subtitle {
            color: #666;
            margin-bottom: 0;
        }
        
        .hotel-count {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
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
                        <i class="fas fa-hotel me-2"></i>
                        Available Hotels
                    </h2>
                    <p class="page-subtitle">Discover and book your perfect stay</p>
                </div>
                <div class="text-end">
                    <?php 
                    $cityFilter = "";
                    $sortOrder = "ORDER BY hotel_id ASC"; // Default sorting

                    if (isset($_GET['sort']) && $_GET['sort'] === 'rating_desc') {
                        $sortOrder = "ORDER BY average_rating DESC";
                    }

                    if (isset($_GET['city']) && !empty(trim($_GET['city']))) {
                        $city = mysqli_real_escape_string($connect, $_GET['city']);
                        $cityFilter = "WHERE address LIKE '%$city%'";
                    }

                    $count_query = mysqli_query($connect, "SELECT COUNT(*) as total FROM hotel $cityFilter");
                    $hotel_count = mysqli_fetch_assoc($count_query)['total'];
                    ?>
                    <div class="hotel-count">
                        <?php echo $hotel_count; ?> Hotels Available
                    </div>
                </div>
            </div>
        </div>
<form method="GET" class="mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-md-6">
            <label for="city" class="form-label">Search by Address</label>
            <input type="text" name="city" id="city" class="form-control"
                   placeholder="e.g. Johor Bahru, Kuala Lumpur" 
                   value="<?php echo isset($_GET['city']) ? htmlspecialchars($_GET['city']) : ''; ?>">
        </div>
        <div class="col-md-4">
            <label for="sort" class="form-label">Sort By</label>
            <select name="sort" id="sort" class="form-control">
                <option value="">-- Default (Hotel ID) --</option>
                <option value="rating_desc" <?php if (isset($_GET['sort']) && $_GET['sort'] === 'rating_desc') echo 'selected'; ?>>
                    Rating (High to Low)
                </option>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100">Search</button>
        </div>
        <div class="col-md-3">
            <a href="hotel.php" class="btn btn-secondary w-100">Reset</a>
        </div>
    </div>
</form>

        <!-- Hotels Grid -->
        <div class="hotel-grid">
            <?php 

            $result = mysqli_query($connect, "SELECT * FROM hotel $cityFilter $sortOrder");

            while ($row = mysqli_fetch_assoc($result)):
            ?>
            <div class="hotel-card">
                    <img src="<?php echo "Room Image/" . $row['hotel_image']; ?>" 
                         class="hotel-image w-100" 
                         alt="<?php echo htmlspecialchars($row['hotel_name']); ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($row['hotel_name']); ?></h5>
                        <p class="card-text">
                            <i class="fas fa-map-marker-alt me-2 text-muted"></i>
                            <?php echo htmlspecialchars($row['address']); ?>
                        </p>
                        <?php if (isset($row['average_rating']) && $row['average_rating'] !== null): ?>
                            <p class="rating-text">
                                <i class="fas fa-star"></i>
                                Rating: <?php echo number_format($row['average_rating'], 1); ?>/5
                            </p>
                        <?php else: ?>
                            <p class="text-muted-custom">
                                <i class="fas fa-star-o me-1"></i>
                                No ratings yet
                            </p>
                        <?php endif; ?>
                        <a href="view_categories.php?hotel_id=<?php echo $row['hotel_id']; ?>" 
                           class="btn-view-more">
                            <i class="fas fa-eye me-2"></i>
                            View Details
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <?php if ($result && mysqli_num_rows($result) == 0): ?>
        <div class="text-center py-5">
            <div class="welcome-header">
                <i class="fas fa-hotel fa-3x text-muted mb-3"></i>
                <h3 class="text-muted">No Hotels Available</h3>
                <p class="text-muted">Please check back later for available hotels.</p>
            </div>
        </div>
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
</script>
</body>
</html>