<?php
session_start();
require_once('connect/connection.php');
include('auto_login.php'); 

if (!isset($_SESSION['customer_id'])) {
    echo "<script>
        alert('Session expired or not logged in. Please log in again.');
        window.location.href='index.php';
    </script>";
    exit;
}

// Check if room_id is passed
if (!isset($_GET['room_id'])) {
    echo "Missing room ID.";
    exit;
}

$room_id = intval($_GET['room_id']);
$customer_id = $_SESSION['customer_id'];

// Fetch room details
$sql = "
    SELECT 
        r.room_id,
        r.room_number,
        r.category_id,
        c.room_image,
        r.hotel_id,
        c.category_name,
        c.price,
        c.discount,
        h.hotel_name
    FROM room r
    JOIN room_category c ON r.category_id = c.category_id
    JOIN hotel h ON r.hotel_id = h.hotel_id
    WHERE r.room_id = $room_id
";

$result = mysqli_query($connect, $sql);
$room = mysqli_fetch_assoc($result);

if (!$room) {
    echo "Room not found.";
    exit;
}

$hotel_id = $room['hotel_id'];

// Fetch booked date ranges
$booked_ranges = [];
$booking_query = mysqli_query($connect, "
    SELECT check_in_date, check_out_date 
    FROM Booking 
    WHERE room_id = $room_id AND booking_status != 'cancelled'
");


while ($row = mysqli_fetch_assoc($booking_query)) {
    $booked_ranges[] = [
        'start' => $row['check_in_date'],
        'end' => $row['check_out_date']
    ];
}

// Fetch available packages
$packages = [];
$pkg_query = mysqli_query($connect, "SELECT package_id, package_name, price FROM Package WHERE hotel_id = $hotel_id");
while ($row = mysqli_fetch_assoc($pkg_query)) {
    $packages[] = $row;
}

// Handle form submission
$pkg_price = 0;
$package_id = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $check_in = $_POST['check_in_date'];
    $check_out = $_POST['check_out_date'];
    $booking_date = date('Y-m-d');
    $package_id = isset($_POST['package_id']) && $_POST['package_id'] !== "" ? intval($_POST['package_id']) : null;

    if ($check_out <= $check_in || $check_in < $booking_date) {
        echo "<script>alert('Invalid check-in/check-out dates.'); window.history.back();</script>";
        exit;
    }

    // Overlap check
    $overlap_query = mysqli_query($connect, "
    SELECT * FROM Booking 
    WHERE room_id = $room_id 
      AND booking_status != 'cancelled'
      AND ('$check_in' <= check_out_date AND '$check_out' >= check_in_date)
    ");

    if (mysqli_num_rows($overlap_query) > 0) {
        echo "<script>alert('Room is already booked during selected dates.'); window.history.back();</script>";
        exit;
    }

    // Package price
    if ($package_id) {
        $pkg_sql = mysqli_query($connect, "SELECT price FROM Package WHERE package_id = $package_id");
        $pkg_data = mysqli_fetch_assoc($pkg_sql);
        $pkg_price = $pkg_data ? $pkg_data['price'] : 0;
    }

    $base_price = $room['price'];
    $discount = $room['discount'];
    $days = (strtotime($check_out) - strtotime($check_in)) / 86400;
    $total_amount = ($base_price + $pkg_price) * $days * (1 - $discount / 100);

    $_SESSION['pending_booking'] = [
        'customer_id' => $customer_id,
        'room_id' => $room_id,
        'package_id' => $package_id,
        'booking_date' => $booking_date,
        'check_in_date' => $check_in,
        'check_out_date' => $check_out,
        'total_amount' => $total_amount
    ];

    header("Location: payment.php");
    exit;
}

// Get dates from URL parameters
if (!isset($_GET['check_in']) || !isset($_GET['check_out'])) {
    echo "Check-in and check-out dates missing.";
    exit;
}
$check_in = $_GET['check_in'];
$check_out = $_GET['check_out'];

// Calculate initial pricing
$days = (strtotime($check_out) - strtotime($check_in)) / 86400;
$base_price = $room['price'];
$discount = $room['discount'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Room <?php echo htmlspecialchars($room['room_number']); ?> - <?php echo htmlspecialchars($room['hotel_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../style.css">
    <style>
        .info-header {
            font-weight: 600;
            color: #6c757d;
            width: 40%;
            background: white;
            border-radius: 8px;
        }
        
        .info-value {
            font-weight: bold;
            color: #333;
            font-size: 1.1rem;
        }
        
        .price-highlight {
            color: #28a745;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .booking-details-card {
            background: linear-gradient(135deg, #e8f5e8 0%, #f0fff4 100%);
            border: 2px solid #28a745;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
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
            
            .booking-card {
                padding: 20px;
            }
            
            .room-info-table {
                padding: 15px;
            }
            
            .room-info-table .table td {
                padding: 12px 15px;
            }
            
            .info-header {
                width: 45%;
                font-size: 0.9rem;
            }
            
            .info-value {
                font-size: 1rem;
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
                        <i class="fas fa-calendar-plus me-2"></i>
                        Complete Your Booking
                    </h2>
                    <p class="page-subtitle">Confirm your reservation details for Room <?php echo htmlspecialchars($room['room_number']); ?></p>
                </div>
            </div>
        </div>

        <!-- Breadcrumb -->
        <nav class="breadcrumb-custom">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="hotel.php">Hotels</a></li>
                <li class="breadcrumb-item"><a href="view_categories.php?hotel_id=<?php echo $room['hotel_id']; ?>">Room Categories</a></li>
                <li class="breadcrumb-item"><a href="room_detail.php?category_id=<?php echo $room['category_id']; ?>">Room Details</a></li>
                <li class="breadcrumb-item active">Booking</li>
            </ol>
        </nav>

        <!-- Back Button -->
        <a href="javascript:history.back()" class="btn-back">
            <i class="fas fa-arrow-left me-2"></i>Back to Room Details
        </a>

        <!-- Room Details -->
        <div class="booking-card">
            <h3 class="section-title">
                <i class="fas fa-bed me-2"></i>Room Information
            </h3>
            
            <img src="<?php echo 'Room Image/' . htmlspecialchars($room['room_image']); ?>" 
                 class="room-img" 
                 alt="<?php echo htmlspecialchars($room['category_name']); ?>">
            
            <div class="room-info-table">
                <table class="table table-borderless">
                    <tbody>
                        <tr>
                            <td class="info-header">
                                <i class="fas fa-hotel me-2"></i>Hotel
                            </td>
                            <td class="info-value"><?php echo htmlspecialchars($room['hotel_name']); ?></td>
                        </tr>
                        <tr>
                            <td class="info-header">
                                <i class="fas fa-door-open me-2"></i>Room Number
                            </td>
                            <td class="info-value"><?php echo htmlspecialchars($room['room_number']); ?></td>
                        </tr>
                        <tr>
                            <td class="info-header">
                                <i class="fas fa-bed me-2"></i>Category
                            </td>
                            <td class="info-value"><?php echo htmlspecialchars($room['category_name']); ?></td>
                        </tr>
                        <tr>
                            <td class="info-header">
                                <i class="fas fa-tag me-2"></i>Price per Night
                            </td>
                            <td class="info-value price-highlight">RM <?php echo number_format($room['price'], 2); ?></td>
                        </tr>
                        <tr>
                            <td class="info-header">
                                <i class="fas fa-percent me-2"></i>Discount
                            </td>
                            <td class="info-value">
                                <?php if ($room['discount'] > 0): ?>
                                    <span class="discount-badge"><?php echo $room['discount']; ?>% OFF</span>
                                <?php else: ?>
                                    <span class="text-muted">No discount</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="info-header">
                                <i class="fas fa-calendar-alt me-2"></i>Duration
                            </td>
                            <td class="info-value"><?php echo $days; ?> night(s)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Booking Details -->
        <div class="booking-details-card">
            <h4 class="section-title">
                <i class="fas fa-calendar-alt me-2"></i>Booking Details
            </h4>
            
            <div class="date-display">
                <div class="row">
                    <div class="col-md-6">
                        <strong><i class="fas fa-calendar-plus me-2 text-success"></i>Check-In Date:</strong>
                        <div class="mt-1"><?php echo date('F j, Y (l)', strtotime($check_in)); ?></div>
                    </div>
                    <div class="col-md-6">
                        <strong><i class="fas fa-calendar-minus me-2 text-danger"></i>Check-Out Date:</strong>
                        <div class="mt-1"><?php echo date('F j, Y (l)', strtotime($check_out)); ?></div>
                    </div>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="check_in_date" value="<?php echo htmlspecialchars($check_in); ?>" id="checkIn">
                <input type="hidden" name="check_out_date" value="<?php echo htmlspecialchars($check_out); ?>" id="checkOut">

                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-gift me-2"></i>Select Package (Optional)
                    </label>
                    <select name="package_id" class="form-control" id="packageSelect">
                        <option value="">-- No Package --</option>
                        <?php foreach ($packages as $pkg): ?>
                            <option value="<?php echo $pkg['package_id']; ?>">
                                <?php echo htmlspecialchars($pkg['package_name']) . " (+RM " . number_format($pkg['price'], 2) . " per night)"; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($packages)): ?>
                        <small class="text-muted mt-2 d-block">
                            <i class="fas fa-info-circle me-1"></i>
                            No packages available for this hotel.
                        </small>
                    <?php endif; ?>
                </div>

                <div class="total-display">
                    <div class="total-amount">RM <span id="totalAmount">0.00</span></div>
                    <div class="total-label">Total Amount</div>
                </div>

                <button type="submit" class="btn-confirm" id="confirmBtn">
                    <i class="fas fa-credit-card me-2"></i>Proceed to Payment
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

// Booking calculation logic
const bookedRanges = <?php echo json_encode($booked_ranges); ?>;
const pricePerNight = <?php echo $room['price']; ?>;
const discount = <?php echo $room['discount']; ?>;
const packagePrices = {
    <?php foreach ($packages as $pkg): ?>
        "<?php echo $pkg['package_id']; ?>": <?php echo $pkg['price']; ?>,
    <?php endforeach; ?>
};

function isDateOverlap(start, end) {
    const s1 = new Date(start);
    const e1 = new Date(end);

    for (let range of bookedRanges) {
        const s2 = new Date(range.start);
        const e2 = new Date(range.end);

        if (s1 <= e2 && e1 >= s2) return true;
    }
    return false;
}

function updateTotal() {
    const checkIn = document.getElementById('checkIn').value;
    const checkOut = document.getElementById('checkOut').value;
    const packageId = document.getElementById('packageSelect').value;
    const totalDisplay = document.getElementById('totalAmount');
    const confirmBtn = document.getElementById('confirmBtn');

    if (checkIn && checkOut) {
        const start = new Date(checkIn);
        const end = new Date(checkOut);
        const days = (end - start) / (1000 * 60 * 60 * 24);

        if (days <= 0 || isDateOverlap(checkIn, checkOut)) {
            totalDisplay.innerHTML = '<span class="error-message">Date not available</span>';
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Dates Not Available';
            return;
        }

        const pkgPrice = packageId ? packagePrices[packageId] || 0 : 0;
        const total = (pricePerNight + pkgPrice) * days * (1 - discount / 100);
        totalDisplay.textContent = total.toFixed(2);
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>Proceed to Payment';
    } else {
        totalDisplay.textContent = "0.00";
    }
}

document.getElementById('packageSelect').addEventListener('change', updateTotal);
updateTotal(); // Run once on page load
</script>
</body>
</html>