<?php
session_start();
require_once('connect/connection.php');

if (!isset($_SESSION['customer_id']) && isset($_COOKIE['customer_token'])) {
    $token = $_COOKIE['customer_token'];
    $result = mysqli_query($connect, "SELECT customer_id FROM customer WHERE remember_token = '" . mysqli_real_escape_string($connect, $token) . "'");
    if ($row = mysqli_fetch_assoc($result)) {
        $_SESSION['customer_id'] = $row['customer_id'];
    }
}

// Add this check:
if (!isset($_SESSION['customer_id'])) {
    header("Location: hotel.php");
    exit;
}

$customer_id = $_SESSION['customer_id'];

// Handle direct cancellation for 'Pending' bookings
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $booking_id = intval($_GET['cancel']);
    $check = mysqli_query($connect, "SELECT * FROM Booking WHERE booking_id = $booking_id AND customer_id = $customer_id AND payment_status IN ('Pending', 'Paid')");

    if (mysqli_num_rows($check) > 0) {
        mysqli_query($connect, "UPDATE Booking SET payment_status = 'Cancel' WHERE booking_id = $booking_id");
        echo "<script>alert('Booking cancelled.'); window.location.href='view_booking.php';</script>";
        exit;
    } else {
        echo "<script>alert('Booking cannot be cancelled.');</script>";
    }
}

// Fetch all bookings
$sql = "
    SELECT b.booking_id, b.check_in_date, b.check_out_date, b.booking_date, b.payment_status, b.booking_status,
           r.room_number,
           c.price, c.discount,
           h.hotel_name, h.address,
           p.package_name, p.price AS package_price
    FROM Booking b
    JOIN Room r ON b.room_id = r.room_id
    JOIN Hotel h ON r.hotel_id = h.hotel_id
    JOIN room_category c ON r.category_id = c.category_id
    LEFT JOIN Package p ON b.package_id = p.package_id
    WHERE b.customer_id = $customer_id
    ORDER BY b.created_at DESC
";

$result = mysqli_query($connect, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Customer Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../style.css">
    <style>
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background: white;
            color: #333;
            border: none;
            padding: 15px 12px;
            font-weight: 600;
            white-space: nowrap;
            border-bottom: 2px solid #e9ecef;
        }
        
        .table td {
            padding: 15px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
            transition: background-color 0.3s ease;
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
            
            .booking-table-container {
                padding: 20px;
            }
            
            .table th, .table td {
                padding: 10px 8px;
                font-size: 0.875rem;
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
                        <i class="fas fa-calendar-check me-2"></i>
                        My Bookings
                    </h2>
                    <p class="page-subtitle">View and manage your hotel reservations</p>
                </div>
                <div class="text-end">
                    <?php 
                    $count_query = mysqli_query($connect, "SELECT COUNT(*) as total FROM Booking WHERE customer_id = $customer_id");
                    $booking_count = mysqli_fetch_assoc($count_query)['total'];
                    ?>
                    <div class="booking-count">
                        <?php echo $booking_count; ?> Total Bookings
                    </div>
                </div>
            </div>
        </div>

        <?php if (mysqli_num_rows($result) == 0): ?>
            <div class="no-bookings">
                <i class="fas fa-calendar-times"></i>
                <h3 class="text-muted">No Bookings Found</h3>
                <p class="text-muted mb-4">You haven't made any hotel reservations yet.</p>
                <a href="hotel.php" class="btn-back">
                    <i class="fas fa-hotel me-2"></i>
                    Browse Hotels
                </a>
            </div>
        <?php else: ?>
            <!-- Bookings Table -->
            <div class="booking-table-container">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Hotel</th>
                                <th>Address</th>
                                <th>Room</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Package</th>
                                <th>Total (RM)</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)):
                                $base = $row['price'];
                                $discount = $row['discount'];
                                $pkg = $row['package_price'] ?? 0;
                                $days = (strtotime($row['check_out_date']) - strtotime($row['check_in_date'])) / 86400;
                                $room_total = ($base - ($base * $discount / 100)) * $days;
                                $total = ($base + $pkg) * $days *(1 - $discount/100);
                                ?>
                                <tr>
                                    <td><strong>#<?= $row['booking_id'] ?></strong></td>
                                    <td><?= htmlspecialchars($row['hotel_name']) ?></td>
                                    <td>
                                        <i class="fas fa-map-marker-alt me-1 text-muted"></i>
                                        <?= htmlspecialchars($row['address']) ?>
                                    </td>
                                    <td>
                                        <i class="fas fa-bed me-1 text-muted"></i>
                                        <?= htmlspecialchars($row['room_number']) ?>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($row['check_in_date'])) ?></td>
                                    <td><?= date('M j, Y', strtotime($row['check_out_date'])) ?></td>
                                    <td><?= $row['package_name'] ?? '<span class="text-muted">None</span>' ?></td>
                                    <td><strong>RM <?= number_format($total, 2) ?></strong></td>
                                    <td>
                                        <?php
                                        // Fixed status display logic
                                        if ($row['booking_status'] === 'requesting') {
                                            echo '<span class="badge badge-warning">Cancellation Requested</span>';
                                        } elseif ($row['payment_status'] === 'Paid' && $row['booking_status'] === 'confirmed') {
                                            echo '<span class="badge badge-success">Confirmed</span>';
                                        } elseif ($row['payment_status'] === 'Paid' && $row['booking_status'] === 'pending') {
                                            echo '<span class="badge badge-secondary">Verifying</span>';
                                        } elseif ($row['payment_status'] === 'Paid' && $row['booking_status'] === 'cancelled') {
                                            echo '<span class="badge badge-danger">Cancelled</span>';
                                        } else {
                                            echo '<span class="badge badge-dark">Processing</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        // Fixed actions logic - check booking_status first
                                        if ($row['booking_status'] === 'requesting') {
                                            echo '<span class="text-muted">No actions available</span>';
                                        } elseif ($row['payment_status'] === 'Paid' && $row['booking_status'] === 'confirmed') {
                                            // Show action buttons for confirmed bookings
                                            echo '<a href="cancel_booking.php?booking_id=' . $row['booking_id'] . '" ';
                                            echo 'class="btn btn-warning btn-sm" ';
                                            echo 'onclick="return confirm(\'Are you sure you want to cancel this booking?\');">';
                                            echo '<i class="fas fa-times me-1"></i>Cancel</a>';
                                            
                                            echo '<a href="feedback.php?booking_id=' . $row['booking_id'] . '" class="btn btn-primary btn-sm">';
                                            echo '<i class="fas fa-comment me-1"></i>Feedback</a>';
                                            
                                            echo '<a href="print_booking.php?booking_id=' . $row['booking_id'] . '" target="_blank" class="btn btn-info btn-sm">';
                                            echo '<i class="fas fa-print me-1"></i>Print</a>';
                                        } else {
                                            echo '<span class="text-muted">';
                                            echo '<i class="fas fa-clock me-1"></i>No actions available';
                                            echo '</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
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