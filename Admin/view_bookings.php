<?php
require_once '../config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Count pending bookings that need attention
$pending_count = getPendingCount($conn);

$message = "";
$message_type = "";

// Handle booking status updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $booking_id = mysqli_real_escape_string($conn, $_POST['booking_id']);
    
    // Add booking ID validation
    if (!is_numeric($booking_id) || $booking_id <= 0) {
        $message = "Invalid booking ID provided.";
        $message_type = "danger";
    } else {
        // Verify booking exists
        $verify_sql = "SELECT booking_id, booking_status FROM booking WHERE booking_id = ?";
        $verify_stmt = mysqli_prepare($conn, $verify_sql);
        mysqli_stmt_bind_param($verify_stmt, "i", $booking_id);
        mysqli_stmt_execute($verify_stmt);
        $verify_result = mysqli_stmt_get_result($verify_stmt);
        
        if (mysqli_num_rows($verify_result) == 0) {
            $message = "Booking not found.";
            $message_type = "danger";
        } else {
            $booking_data = mysqli_fetch_assoc($verify_result);
            
            try {
                switch ($_POST['action']) {
                    case 'verify_payment':
                        // Validate current status
                        if ($booking_data['booking_status'] !== 'pending') {
                            throw new Exception("Booking is not in pending status for payment verification.");
                        }
                        
                        mysqli_begin_transaction($conn);
                        
                        // Update booking status
                        $sql = "UPDATE booking SET booking_status = 'confirmed' WHERE booking_id = ?";
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "i", $booking_id);
                        mysqli_stmt_execute($stmt);
                        
                        mysqli_commit($conn);
                        $message = "Payment verified and booking confirmed!";
                        $message_type = "success";
                        break;
                        
                    case 'approve_cancellation':
                        // Validate current status
                        if ($booking_data['booking_status'] !== 'requesting') {
                            throw new Exception("Booking is not in cancellation request status.");
                        }
                        
                        mysqli_begin_transaction($conn);
                        
                        $sql = "UPDATE booking SET booking_status = 'cancelled' WHERE booking_id = ?";
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "i", $booking_id);
                        mysqli_stmt_execute($stmt);
                        
                        mysqli_commit($conn);
                        $message = "Cancellation approved and refund should be processed!";
                        $message_type = "success";
                        break;
                        
                    case 'reject_cancellation':
                        // Validate current status
                        if ($booking_data['booking_status'] !== 'requesting') {
                            throw new Exception("Booking is not in cancellation request status.");
                        }
                        
                        $sql = "UPDATE booking SET booking_status = 'confirmed' WHERE booking_id = ?";
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "i", $booking_id);
                        mysqli_stmt_execute($stmt);
                        
                        $message = "Cancellation rejected. Booking remains confirmed.";
                        $message_type = "info";
                        break;
                        
                    default:
                        throw new Exception("Invalid action specified.");
                }
            } catch (Exception $e) {
                mysqli_rollback($conn);
                error_log("Error in booking action: " . $e->getMessage());
                $message = "Error: " . $e->getMessage();
                $message_type = "danger";
            }
        }
    }
}

// Get filter parameters
$filter_hotel_id = isset($_GET['filter_hotel']) ? intval($_GET['filter_hotel']) : 0;
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : 'needs_attention';

// Build the SQL query with filtering
$sql = "SELECT b.*, c.name as customer_name, c.email, c.phone, 
               r.room_number, rc.category_name, h.hotel_name, h.hotel_id,
               p.package_name,
               cr.reason_text, cr.account_number, cr.cancelled_at,
               pp.image_name as payment_proof_image
        FROM booking b 
        JOIN customer c ON b.customer_id = c.customer_id 
        JOIN room r ON b.room_id = r.room_id 
        JOIN room_category rc ON r.category_id = rc.category_id 
        JOIN hotel h ON r.hotel_id = h.hotel_id
        LEFT JOIN package p ON b.package_id = p.package_id
        LEFT JOIN cancellation_reason cr ON b.booking_id = cr.booking_id
        LEFT JOIN payment_proof pp ON b.booking_id = pp.booking_id";

$where_conditions = [];
$params = [];
$param_types = "";

// Apply hotel filter
if ($filter_hotel_id > 0) {
    $where_conditions[] = "h.hotel_id = ?";
    $params[] = $filter_hotel_id;
    $param_types .= "i";
}

// Apply status filter
switch ($filter_status) {
    case 'needs_attention':
        $where_conditions[] = "(b.booking_status = 'requesting' OR (b.booking_status = 'pending' AND b.payment_status = 'Paid'))";
        break;
    case 'verification_needed':
        $where_conditions[] = "b.booking_status = 'pending' AND b.payment_status = 'Paid'";
        break;
    case 'cancellation_requested':
        $where_conditions[] = "b.booking_status = 'requesting'";
        break;
    case 'confirmed':
        $where_conditions[] = "b.booking_status = 'confirmed'";
        break;
    case 'cancelled':
        $where_conditions[] = "b.booking_status = 'cancelled'";
        break;
    case 'all':
        // No additional condition for all bookings
        break;
}

// Add WHERE clause if we have conditions
if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

// Add ORDER BY clause
$sql .= " ORDER BY 
            CASE 
                WHEN b.booking_status = 'pending' THEN 1
                WHEN b.booking_status = 'requesting' THEN 2
                ELSE 3
            END, 
            b.booking_date DESC";

// Execute query
if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    mysqli_stmt_execute($stmt);
    $bookings = mysqli_stmt_get_result($stmt);
} else {
    $bookings = mysqli_query($conn, $sql);
}

// Fetch all hotels for dropdown
$hotels = mysqli_query($conn, "SELECT * FROM hotel ORDER BY hotel_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - Hotel Management System</title>
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
        .table th { background-color: #f8f9fa; }
        .verification-row { background-color: #e3f2fd; }
        .cancellation-row { background-color: #fff3cd; }
        .cancellation-details { background-color: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 10px; }
        .payment-proof-modal .modal-body img { max-width: 100%; }
        .badge-verifying { background-color: #17a2b8; color: white; }
        .badge-requesting { background-color: #ffc107; color: black; }
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
                        <a class="nav-link active" href="view_bookings.php">
                            <i class="fas fa-calendar-check me-2"></i> Bookings
                            <?php if ($pending_count > 0): ?>
                                <span class="badge bg-danger ms-2"><?php echo $pending_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link" href="view_feedback.php"><i class="fas fa-comments me-2"></i> Feedback</a>
                        <hr class="text-white">
                        <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="content-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-calendar-check me-2"></i> Manage Bookings</h2>
                        <div>
                            <span class="badge bg-info me-2">Pending Verification: 
                                <?php 
                                $pending = mysqli_query($conn, "SELECT COUNT(*) as count FROM booking WHERE booking_status = 'pending' AND payment_status = 'Paid'");
                                echo mysqli_fetch_assoc($pending)['count'];
                                ?>
                            </span>
                            <span class="badge bg-warning me-2">Cancellation Requests: 
                                <?php 
                                $cancel_requests = mysqli_query($conn, "SELECT COUNT(*) as count FROM booking WHERE booking_status = 'requesting'");
                                echo mysqli_fetch_assoc($cancel_requests)['count'];
                                ?>
                            </span>
                            <span class="badge bg-success">Confirmed: 
                                <?php 
                                $confirmed = mysqli_query($conn, "SELECT COUNT(*) as count FROM booking WHERE booking_status = 'confirmed'");
                                echo mysqli_fetch_assoc($confirmed)['count'];
                                ?>
                            </span>
                        </div>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Filters -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <form method="GET" id="filterForm" class="d-flex flex-column">
                                <label for="hotelFilter" class="form-label">Filter by Hotel:</label>
                                <select name="filter_hotel" class="form-select mb-3" id="hotelFilter" onchange="this.form.submit()">
                                    <option value="0">All Hotels</option>
                                    <?php while ($hotel = mysqli_fetch_assoc($hotels)): ?>
                                        <option value="<?php echo $hotel['hotel_id']; ?>" <?php echo ($filter_hotel_id == $hotel['hotel_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($hotel['hotel_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="GET" id="statusFilterForm" class="d-flex flex-column">
                                <input type="hidden" name="filter_hotel" value="<?php echo $filter_hotel_id; ?>">
                                <label for="statusFilter" class="form-label">Filter by Status:</label>
                                <select name="filter_status" class="form-select mb-3" id="statusFilter" onchange="this.form.submit()">
                                    <option value="needs_attention" <?php echo ($filter_status == 'needs_attention') ? 'selected' : ''; ?>>Needs Attention (Default)</option>
                                    <option value="verification_needed" <?php echo ($filter_status == 'verification_needed') ? 'selected' : ''; ?>>Payment Verification Needed</option>
                                    <option value="cancellation_requested" <?php echo ($filter_status == 'cancellation_requested') ? 'selected' : ''; ?>>Cancellation Requested</option>
                                    <option value="confirmed" <?php echo ($filter_status == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="cancelled" <?php echo ($filter_status == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="all" <?php echo ($filter_status == 'all') ? 'selected' : ''; ?>>All Bookings</option>
                                </select>
                            </form>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Customer</th>
                                    <th>Hotel & Room</th>
                                    <th>Package</th>
                                    <th>Dates</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($booking = mysqli_fetch_assoc($bookings)): 
                                    $rowClass = '';
                                    if ($booking['booking_status'] == 'pending' && $booking['payment_status'] == 'Paid') {
                                        $rowClass = 'verification-row';
                                    } elseif ($booking['booking_status'] == 'requesting') {
                                        $rowClass = 'cancellation-row';
                                    }
                                ?>
                                <tr class="<?php echo $rowClass; ?>">
                                    <td>
                                        <strong>#<?php echo $booking['booking_id']; ?></strong>
                                        <?php if ($rowClass): ?>
                                            <i class="fas fa-exclamation-triangle text-warning ms-2" title="Needs attention"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $booking['customer_name']; ?><br>
                                        <small class="text-muted"><?php echo $booking['email']; ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo $booking['hotel_name']; ?></strong><br>
                                        Room <?php echo $booking['room_number']; ?> (<?php echo $booking['category_name']; ?>)
                                    </td>
                                    <td><?php echo $booking['package_name'] ? $booking['package_name'] : 'No Package'; ?></td>
                                    <td>
                                        <strong>In:</strong> <?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?><br>
                                        <strong>Out:</strong> <?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?>
                                    </td>
                                    <td>
                                        <?php if ($booking['total_amount']): ?>
                                            <strong>$<?php echo number_format($booking['total_amount'], 2); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">Calculating...</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($booking['booking_status'] == 'confirmed') {
                                            echo '<span class="badge bg-success">Confirmed</span>';
                                        } elseif ($booking['booking_status'] == 'cancelled') {
                                            echo '<span class="badge bg-danger">Cancelled</span>';
                                        } elseif ($booking['booking_status'] == 'requesting') {
                                            echo '<span class="badge badge-requesting">Cancellation Requested</span>';
                                        } elseif ($booking['booking_status'] == 'pending' && $booking['payment_status'] == 'Paid') {
                                            echo '<span class="badge badge-verifying">Payment Verification Needed</span>';
                                        } else {
                                            echo '<span class="badge bg-secondary">Processing</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($booking['booking_status'] == 'pending' && $booking['payment_status'] == 'Paid'): ?>
                                            <!-- Payment verification needed -->
                                            <?php if ($booking['payment_proof_image']): ?>
                                                <button class="btn btn-sm btn-info mb-1" onclick="viewPaymentProof('<?php echo htmlspecialchars($booking['payment_proof_image']); ?>')">
                                                    <i class="fas fa-image"></i> View Proof
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-success mb-1" onclick="updateBookingStatus(<?php echo $booking['booking_id']; ?>, 'verify_payment')">
                                                <i class="fas fa-check"></i> Verify Payment
                                            </button>
                                        <?php elseif ($booking['booking_status'] == 'requesting'): ?>
                                            <!-- Cancellation request -->
                                            <button class="btn btn-sm btn-danger mb-1" onclick="updateBookingStatus(<?php echo $booking['booking_id']; ?>, 'approve_cancellation')">
                                                <i class="fas fa-ban"></i> Approve Cancel
                                            </button>
                                            <button class="btn btn-sm btn-secondary mb-1" onclick="updateBookingStatus(<?php echo $booking['booking_id']; ?>, 'reject_cancellation')">
                                                <i class="fas fa-times"></i> Reject Cancel
                                            </button>
                                        <?php elseif ($booking['booking_status'] == 'confirmed'): ?>
                                            <span class="text-success"><i class="fas fa-check-circle"></i> Active Booking</span>
                                        <?php elseif ($booking['booking_status'] == 'cancelled'): ?>
                                            <span class="text-danger"><i class="fas fa-ban"></i> Cancelled</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <!-- Show cancellation details if exists -->
                                <?php if ($booking['booking_status'] == 'requesting' && $booking['reason_text']): ?>
                                <tr class="<?php echo $rowClass; ?>">
                                    <td colspan="8">
                                        <div class="cancellation-details">
                                            <h6><i class="fas fa-ban text-danger me-2"></i>Cancellation Request Details:</h6>
                                            <p><strong>Reason:</strong> <?php echo htmlspecialchars($booking['reason_text']); ?></p>
                                            <p><strong>Refund Account:</strong> <?php echo htmlspecialchars($booking['account_number']); ?></p>
                                            <p><strong>Requested:</strong> <?php echo date('M d, Y g:i A', strtotime($booking['cancelled_at'])); ?></p>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden form for status updates -->
    <form method="POST" id="statusUpdateForm" style="display: none;">
        <input type="hidden" name="booking_id" id="status_booking_id">
        <input type="hidden" name="action" id="status_action">
    </form>

    <!-- Payment Proof Modal -->
    <div class="modal fade payment-proof-modal" id="paymentProofModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment Proof</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="paymentProofImage" src="" alt="Payment Proof">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sync both filter forms
        document.getElementById('hotelFilter').addEventListener('change', function() {
            const statusFilter = document.getElementById('statusFilter');
            const form = document.getElementById('filterForm');
            
            // Add status filter value to hotel form
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'filter_status';
            statusInput.value = statusFilter.value;
            form.appendChild(statusInput);
            
            form.submit();
        });

        function updateBookingStatus(bookingId, action) {
            let confirmMsg = '';
            switch(action) {
                case 'verify_payment':
                    confirmMsg = 'Verify this payment and confirm the booking?';
                    break;
                case 'approve_cancellation':
                    confirmMsg = 'Approve this cancellation? The room will become available and refund should be processed.';
                    break;
                case 'reject_cancellation':
                    confirmMsg = 'Reject this cancellation request? The booking will remain active.';
                    break;
            }
            
            if (confirm(confirmMsg)) {
                document.getElementById('status_booking_id').value = bookingId;
                document.getElementById('status_action').value = action;
                document.getElementById('statusUpdateForm').submit();
            }
        }

        function viewPaymentProof(imageName) {
            document.getElementById('paymentProofImage').src = "../User/payment_proofs/" + imageName;
            var modal = new bootstrap.Modal(document.getElementById('paymentProofModal'));
            modal.show();
        }
    </script>
</body>
</html>