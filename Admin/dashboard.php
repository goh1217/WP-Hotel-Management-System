<?php
require_once '../config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Get detailed action breakdown
$actions = getActionBreakdown($conn);
$total_actions_needed = $actions['verification_needed'] + $actions['cancellation_requests'];

// Use helper function for consistency
$pending_count = getPendingCount($conn);

// Get basic stats with corrected availability logic and error handling
$today = date('Y-m-d');
$stats_query = mysqli_query($conn, "
    SELECT
    (SELECT COUNT(*) FROM room) as total_rooms,
    (SELECT COUNT(*) 
     FROM room r 
     WHERE r.status = 'available' 
     AND r.room_id NOT IN (
         SELECT b.room_id 
         FROM booking b 
         WHERE '$today' BETWEEN b.check_in_date AND DATE_SUB(b.check_out_date, INTERVAL 1 DAY)
         AND b.booking_status IN ('confirmed', 'pending')
     )) as available_rooms_today,
    (SELECT COUNT(*) 
     FROM room r 
     WHERE r.room_id IN (
         SELECT b.room_id 
         FROM booking b 
         WHERE '$today' BETWEEN b.check_in_date AND DATE_SUB(b.check_out_date, INTERVAL 1 DAY)
         AND b.booking_status IN ('confirmed', 'pending')
     )) as occupied_rooms_today,
    (SELECT COUNT(*) FROM booking) as total_bookings,
    (SELECT COUNT(*) FROM customer) as total_customers,
    (SELECT COUNT(*) FROM feedback) as total_feedback
");

// Add error handling for statistics
if (!$stats_query) {
    error_log("Dashboard stats query failed: " . mysqli_error($conn));
    // Set default values
    $stats = [
        'total_rooms' => 0,
        'available_rooms_today' => 0,
        'occupied_rooms_today' => 0,
        'total_bookings' => 0,
        'total_customers' => 0,
        'total_feedback' => 0
    ];
} else {
    $stats = mysqli_fetch_assoc($stats_query);
    if (!$stats) {
        error_log("Dashboard stats fetch failed: " . mysqli_error($conn));
        $stats = [
            'total_rooms' => 0,
            'available_rooms_today' => 0,
            'occupied_rooms_today' => 0,
            'total_bookings' => 0,
            'total_customers' => 0,
            'total_feedback' => 0
        ];
    }
}

// Get upcoming availability for next 7 days with error handling
$upcoming_availability = [];
for ($i = 0; $i < 7; $i++) {
    $check_date = date('Y-m-d', strtotime("+$i days"));
    $availability_query = mysqli_query($conn, "
        SELECT COUNT(*) as available_count
        FROM room r 
        WHERE r.status = 'available' 
        AND r.room_id NOT IN (
            SELECT b.room_id 
            FROM booking b 
            WHERE '$check_date' BETWEEN b.check_in_date AND DATE_SUB(b.check_out_date, INTERVAL 1 DAY)
            AND b.booking_status IN ('confirmed', 'pending')
        )
    ");
    
    if ($availability_query) {
        $availability_data = mysqli_fetch_assoc($availability_query);
        $available_count = $availability_data ? $availability_data['available_count'] : 0;
    } else {
        error_log("Availability query failed for date $check_date: " . mysqli_error($conn));
        $available_count = 0;
    }
    
    $upcoming_availability[] = [
        'date' => $check_date,
        'day_name' => date('D', strtotime($check_date)),
        'available' => $available_count,
        'occupied' => $stats['total_rooms'] - $available_count
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Hotel Management System</title>
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
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        .welcome-header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        .urgent-badge {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            animation: pulse 2s infinite;
        }
        .action-required-card {
            border-left: 5px solid #dc3545;
            background: linear-gradient(135deg, #fff5f5 0%, #ffe6e6 100%);
        }
        .breakdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            background: white;
            border-left: 4px solid transparent;
        }
        .breakdown-item.verification {
            border-left-color: #17a2b8;
            background: linear-gradient(135deg, #e3f2fd 0%, #f3f9ff 100%);
        }
        .breakdown-item.cancellation {
            border-left-color: #ffc107;
            background: linear-gradient(135deg, #fff8e1 0%, #fffef7 100%);
        }
        .availability-day-card {
            transition: all 0.3s ease;
            background: white;
        }
        .availability-day-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .progress {
            border-radius: 10px;
            overflow: hidden;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
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
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        <a class="nav-link" href="manage_categories.php">
                            <i class="fas fa-tags me-2"></i> Room Categories
                        </a>
                        <a class="nav-link" href="manage_rooms.php">
                            <i class="fas fa-bed me-2"></i> Room Details
                        </a>
                        <a class="nav-link" href="manage_packages.php">
                            <i class="fas fa-gift me-2"></i> Packages
                        </a>
                        <a class="nav-link" href="view_bookings.php">
                            <i class="fas fa-calendar-check me-2"></i> Bookings
                            <?php if ($pending_count > 0): ?>
                                <span class="badge bg-danger ms-2"><?php echo $pending_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link" href="view_feedback.php">
                            <i class="fas fa-comments me-2"></i> Feedback
                        </a>
                        <hr class="text-white">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Welcome Header -->
                <div class="welcome-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2><i class="fas fa-tachometer-alt me-2"></i> Dashboard</h2>
                            <p class="text-muted mb-0">Welcome back! Here's what's happening with your hotels today.</p>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">Room availability for:</small>
                            <div class="fw-bold text-primary"><?php echo date('F j, Y'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Action Required Alert -->
                <?php if ($total_actions_needed > 0): ?>
                <div class="stat-card action-required-card">
                    <div class="d-flex align-items-center mb-3">
                        <div class="stat-icon urgent-badge me-3">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div>
                            <h4 class="text-danger mb-1">
                                <i class="fas fa-bell"></i> Action Required
                            </h4>
                            <p class="mb-0 text-muted">You have <?php echo $total_actions_needed; ?> booking(s) that need your immediate attention</p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <?php if ($actions['verification_needed'] > 0): ?>
                        <div class="col-md-6">
                            <div class="breakdown-item verification">
                                <div>
                                    <i class="fas fa-credit-card text-info me-2"></i>
                                    <strong>Payment Verification Needed</strong>
                                    <br><small class="text-muted">Customers have paid and awaiting confirmation</small>
                                </div>
                                <span class="badge bg-info fs-6"><?php echo $actions['verification_needed']; ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($actions['cancellation_requests'] > 0): ?>
                        <div class="col-md-6">
                            <div class="breakdown-item cancellation">
                                <div>
                                    <i class="fas fa-ban text-warning me-2"></i>
                                    <strong>Cancellation Requests</strong>
                                    <br><small class="text-muted">Customers requesting to cancel bookings</small>
                                </div>
                                <span class="badge bg-warning text-dark fs-6"><?php echo $actions['cancellation_requests']; ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-3 d-flex gap-2">
                        <?php if ($actions['verification_needed'] > 0): ?>
                        <a href="view_bookings.php?filter_status=verification_needed" class="btn btn-info">
                            <i class="fas fa-credit-card me-2"></i>Verify Payments (<?php echo $actions['verification_needed']; ?>)
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($actions['cancellation_requests'] > 0): ?>
                        <a href="view_bookings.php?filter_status=cancellation_requested" class="btn btn-warning">
                            <i class="fas fa-ban me-2"></i>Review Cancellations (<?php echo $actions['cancellation_requests']; ?>)
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <i class="fas fa-bed"></i>
                                </div>
                                <div class="ms-3">
                                    <h3 class="mb-0"><?php echo $stats['total_rooms']; ?></h3>
                                    <p class="text-muted mb-0">Total Rooms</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="ms-3">
                                    <h3 class="mb-0"><?php echo $stats['available_rooms_today']; ?></h3>
                                    <p class="text-muted mb-0">Available Today</p>
                                    <small class="text-success">
                                        <i class="fas fa-calendar me-1"></i><?php echo date('M j'); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="ms-3">
                                    <h3 class="mb-0"><?php echo $stats['occupied_rooms_today']; ?></h3>
                                    <p class="text-muted mb-0">Occupied Today</p>
                                    <small class="text-danger">
                                        <i class="fas fa-percentage me-1"></i><?php echo $stats['total_rooms'] > 0 ? round(($stats['occupied_rooms_today'] / $stats['total_rooms']) * 100, 1) : 0; ?>% occupancy
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="ms-3">
                                    <h3 class="mb-0"><?php echo $stats['total_bookings']; ?></h3>
                                    <p class="text-muted mb-0">Total Bookings</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                                    <i class="fas fa-check-double"></i>
                                </div>
                                <div class="ms-3">
                                    <h3 class="mb-0"><?php echo $actions['confirmed_bookings']; ?></h3>
                                    <p class="text-muted mb-0">Confirmed Bookings</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <div class="ms-3">
                                    <h3 class="mb-0"><?php echo $stats['total_feedback']; ?></h3>
                                    <p class="text-muted mb-0">Customer Feedback</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Room Availability Forecast -->
                <div class="stat-card">
                    <h4 class="mb-3"><i class="fas fa-chart-line me-2"></i>7-Day Room Availability Forecast</h4>
                    <div class="row">
                        <?php foreach ($upcoming_availability as $day): ?>
                        <div class="col-md-12 col-lg-6 col-xl-3 mb-3">
                            <div class="availability-day-card p-3 border rounded <?php echo $day['date'] === $today ? 'border-primary bg-light' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h6 class="mb-0 <?php echo $day['date'] === $today ? 'text-primary fw-bold' : ''; ?>">
                                            <?php echo $day['day_name']; ?>
                                            <?php if ($day['date'] === $today): ?>
                                                <span class="badge bg-primary ms-1">Today</span>
                                            <?php endif; ?>
                                        </h6>
                                        <small class="text-muted"><?php echo date('M j', strtotime($day['date'])); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <div class="text-success fw-bold"><?php echo $day['available']; ?> available</div>
                                        <small class="text-danger"><?php echo $day['occupied']; ?> occupied</small>
                                    </div>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $stats['total_rooms'] > 0 ? ($day['available'] / $stats['total_rooms']) * 100 : 0; ?>%"></div>
                                    <div class="progress-bar bg-danger" style="width: <?php echo $stats['total_rooms'] > 0 ? ($day['occupied'] / $stats['total_rooms']) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Availability considers confirmed and pending bookings. Rooms with 'unavailable' status are excluded.
                        </small>
                    </div>
                </div>

                <!-- Booking Status Summary -->
                <div class="stat-card">
                    <h4 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Booking Status Breakdown</h4>
                    <div class="row text-center">
                        <div class="col-md-2">
                            <h5 class="text-success"><?php echo $actions['confirmed_bookings']; ?></h5>
                            <p class="text-muted">Confirmed</p>
                        </div>
                        <div class="col-md-2">
                            <h5 class="text-info"><?php echo $actions['verification_needed']; ?></h5>
                            <p class="text-muted">Need Verification</p>
                        </div>
                        <div class="col-md-2">
                            <h5 class="text-warning"><?php echo $actions['cancellation_requests']; ?></h5>
                            <p class="text-muted">Cancel Requests</p>
                        </div>
                        <div class="col-md-2">
                            <h5 class="text-secondary"><?php echo $actions['pending_payment']; ?></h5>
                            <p class="text-muted">Pending Payment</p>
                        </div>
                        <div class="col-md-2">
                            <h5 class="text-danger"><?php echo $actions['cancelled_bookings']; ?></h5>
                            <p class="text-muted">Cancelled</p>
                        </div>
                        <div class="col-md-2">
                            <h5 class="text-primary"><?php echo $stats['total_bookings']; ?></h5>
                            <p class="text-muted">Total</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="stat-card">
                    <h4 class="mb-3"><i class="fas fa-rocket me-2"></i>Quick Actions</h4>
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="view_bookings.php?filter_status=verification_needed" class="btn btn-info w-100">
                                <i class="fas fa-credit-card me-2"></i> Verify Payments
                                <?php if ($actions['verification_needed'] > 0): ?>
                                    <span class="badge bg-dark ms-2"><?php echo $actions['verification_needed']; ?></span>
                                <?php endif; ?>
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="view_bookings.php?filter_status=cancellation_requested" class="btn btn-warning w-100">
                                <i class="fas fa-ban me-2"></i> Review Cancellations
                                <?php if ($actions['cancellation_requests'] > 0): ?>
                                    <span class="badge bg-dark ms-2"><?php echo $actions['cancellation_requests']; ?></span>
                                <?php endif; ?>
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="manage_rooms.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-plus me-2"></i> Add Room
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="view_feedback.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-comments me-2"></i> View Feedback
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>