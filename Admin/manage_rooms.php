<?php
require_once '../config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Count pending bookings that need attention
$pending_count = getPendingCount($conn);

$message = "";

// Get filter parameters
$filter_hotel_id = isset($_GET['filter_hotel']) ? intval($_GET['filter_hotel']) : 0;
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add':
                    $room_number = cleanInput($_POST['room_number']);
                    $category_id = validateNumber($_POST['category_id'], 1);
                    $hotel_id = validateNumber($_POST['hotel_id'], 1);
                    $status = cleanInput($_POST['status']);
                    
                    // Check if room number already exists in the same hotel
                    $check_sql = "SELECT COUNT(*) as count FROM room WHERE room_number = ? AND hotel_id = ?";
                    $check_stmt = mysqli_prepare($conn, $check_sql);
                    mysqli_stmt_bind_param($check_stmt, "si", $room_number, $hotel_id);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    $existing_count = mysqli_fetch_assoc($check_result)['count'];
                    
                    if ($existing_count > 0) {
                        throw new Exception("Room number '$room_number' already exists in this hotel. Please use a different room number.");
                    }
                    
                    $sql = "INSERT INTO room (room_number, category_id, hotel_id, status) VALUES (?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "siis", $room_number, $category_id, $hotel_id, $status);
                    mysqli_stmt_execute($stmt);
                    
                    $message = "Room added successfully!";
                    break;
                    
                case 'edit':
                    $room_id = validateNumber($_POST['room_id'], 1);
                    $room_number = cleanInput($_POST['room_number']);
                    $category_id = validateNumber($_POST['category_id'], 1);
                    $hotel_id = validateNumber($_POST['hotel_id'], 1);
                    $status = cleanInput($_POST['status']);
                    
                    // Check if room number already exists in the same hotel (excluding current room)
                    $check_sql = "SELECT COUNT(*) as count FROM room WHERE room_number = ? AND hotel_id = ? AND room_id != ?";
                    $check_stmt = mysqli_prepare($conn, $check_sql);
                    mysqli_stmt_bind_param($check_stmt, "sii", $room_number, $hotel_id, $room_id);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    $existing_count = mysqli_fetch_assoc($check_result)['count'];
                    
                    if ($existing_count > 0) {
                        throw new Exception("Room number '$room_number' already exists in this hotel. Please use a different room number.");
                    }
                    
                    $stmt = mysqli_prepare($conn, "UPDATE room SET room_number = ?, category_id = ?, hotel_id = ?, status = ? WHERE room_id = ?");
                    mysqli_stmt_bind_param($stmt, "siisi", $room_number, $category_id, $hotel_id, $status, $room_id);
                    mysqli_stmt_execute($stmt);
                    
                    $message = "Room updated successfully!";
                    break;
                    
                case 'delete':
                    $room_id = validateNumber($_POST['room_id'], 1);
                    
                    // Check for active or future bookings with better query
                    $check_sql = "
                        SELECT COUNT(*) as count, 
                               GROUP_CONCAT(CONCAT('Booking #', booking_id, ' (', check_in_date, ' to ', check_out_date, ')') SEPARATOR ', ') as booking_details
                        FROM booking 
                        WHERE room_id = ? 
                        AND booking_status IN ('confirmed', 'pending')
                        AND check_out_date >= CURDATE()
                    ";
                    $stmt = mysqli_prepare($conn, $check_sql);
                    mysqli_stmt_bind_param($stmt, "i", $room_id);
                    mysqli_stmt_execute($stmt);
                    $check_result = mysqli_stmt_get_result($stmt);
                    $booking_data = mysqli_fetch_assoc($check_result);
                    
                    if ($booking_data['count'] > 0) {
                        throw new Exception("Cannot delete room. It has active/future bookings: " . $booking_data['booking_details']);
                    }
                    
                    $stmt = mysqli_prepare($conn, "DELETE FROM room WHERE room_id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $room_id);
                    mysqli_stmt_execute($stmt);
                    
                    $message = "Room deleted successfully!";
                    break;
            }
        } catch (Exception $e) {
            error_log("Error in room management: " . $e->getMessage());
            $message = $e->getMessage();
        }
    }
}

// Build the SQL query for rooms with filtering
$sql = "SELECT r.*, rc.category_name, rc.price, rc.discount, h.hotel_name,
               r.status as permanent_status,
               CASE
                  WHEN (SELECT COUNT(*) FROM booking b 
                        WHERE b.room_id = r.room_id 
                        AND ? BETWEEN b.check_in_date AND DATE_SUB(b.check_out_date, INTERVAL 1 DAY)
                        AND b.booking_status IN ('confirmed', 'pending')) > 0 THEN 'booked'
                  ELSE 'available'
               END as booking_status,
               (SELECT b.booking_id FROM booking b 
                WHERE b.room_id = r.room_id 
                AND ? BETWEEN b.check_in_date AND DATE_SUB(b.check_out_date, INTERVAL 1 DAY)
                AND b.booking_status IN ('confirmed', 'pending')
                LIMIT 1) as current_booking,
               (SELECT c.name FROM booking b 
                JOIN customer c ON b.customer_id = c.customer_id
                WHERE b.room_id = r.room_id 
                AND ? BETWEEN b.check_in_date AND DATE_SUB(b.check_out_date, INTERVAL 1 DAY)
                AND b.booking_status IN ('confirmed', 'pending')
                LIMIT 1) as guest_name
        FROM room r 
        LEFT JOIN room_category rc ON r.category_id = rc.category_id 
        LEFT JOIN hotel h ON r.hotel_id = h.hotel_id";

// Apply hotel filter if selected
$where_conditions = [];
$params = [$filter_date, $filter_date, $filter_date];
$param_types = "sss";

if ($filter_hotel_id > 0) {
    $where_conditions[] = "r.hotel_id = ?";
    $params[] = $filter_hotel_id;
    $param_types .= "i";
}

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " ORDER BY r.room_id";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $param_types, ...$params);
mysqli_stmt_execute($stmt);
$rooms = mysqli_stmt_get_result($stmt);

$hotels = mysqli_query($conn, "SELECT * FROM hotel ORDER BY hotel_name");
$categories = mysqli_query($conn, "SELECT rc.*, h.hotel_name FROM room_category rc JOIN hotel h ON rc.hotel_id = h.hotel_id ORDER BY h.hotel_name, rc.category_name");
$hotels_for_filter = mysqli_query($conn, "SELECT * FROM hotel ORDER BY hotel_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rooms - Hotel Management System</title>
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
        .filter-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
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
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        <a class="nav-link" href="manage_categories.php">
                            <i class="fas fa-tags me-2"></i> Room Categories
                        </a>
                        <a class="nav-link active" href="manage_rooms.php">
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
                <div class="content-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-bed me-2"></i> Manage Room Details</h2>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal">
                            <i class="fas fa-plus me-2"></i> Add New Room
                        </button>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Filter Section - Updated to match manage_packages.php style -->
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <form method="GET" id="filterForm" class="d-flex flex-column">
                                <label for="hotelFilter" class="form-label">Filter by Hotel:</label>
                                <select name="filter_hotel" class="form-select" id="hotelFilter" onchange="this.form.submit()">
                                    <option value="0">All Hotels</option>
                                    <?php 
                                    mysqli_data_seek($hotels_for_filter, 0);
                                    while ($hotel = mysqli_fetch_assoc($hotels_for_filter)): 
                                    ?>
                                        <option value="<?php echo $hotel['hotel_id']; ?>" <?php echo ($filter_hotel_id == $hotel['hotel_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($hotel['hotel_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <label for="dateFilter" class="form-label">View Status for Date:</label>
                            <input type="date" name="filter_date" form="filterForm" class="form-control" id="dateFilter" 
                                   value="<?php echo $filter_date; ?>" onchange="document.getElementById('filterForm').submit()"
                                   min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                            <small class="text-muted mt-1 d-block">
                                <i class="fas fa-info-circle me-1"></i>
                                Showing availability for: <strong><?php echo date('F j, Y', strtotime($filter_date)); ?></strong>
                            </small>
                        </div>
                    </div>

                    <!-- Rooms Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Room ID</th>
                                    <th>Hotel</th>
                                    <th>Room Number</th>
                                    <th>Category</th>
                                    <th>Price/Night</th>
                                    <th>Discount</th>
                                    <th>Status (<?php echo date('M j', strtotime($filter_date)); ?>)</th>
                                    <th>Guest Info</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($room = mysqli_fetch_assoc($rooms)): ?>
                                <tr>
                                    <td><?php echo $room['room_id']; ?></td>
                                    <td><?php echo $room['hotel_name']; ?></td>
                                    <td><strong><?php echo $room['room_number']; ?></strong></td>
                                    <td><?php echo $room['category_name']; ?></td>
                                    <td>$<?php echo number_format($room['price'], 2); ?></td>
                                    <td><?php echo $room['discount'] > 0 ? $room['discount'] . '%' : '0%'; ?></td>
                                    <td>
                                        <?php 
                                        $book_status = $room['booking_status'];
                                        $book_badge_class = ($book_status == 'booked') ? 'bg-danger' : 'bg-success';
                                        ?>
                                        <span class="badge <?php echo $book_badge_class; ?>">
                                            <?php echo ucfirst($book_status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($room['guest_name'] && $room['current_booking']): ?>
                                            <small>
                                                <strong><?php echo htmlspecialchars($room['guest_name']); ?></strong><br>
                                                Booking #<?php echo $room['current_booking']; ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="editRoom(<?php echo $room['room_id']; ?>, '<?php echo $room['room_number']; ?>', <?php echo $room['category_id']; ?>, <?php echo $room['hotel_id']; ?>, '<?php echo $room['status']; ?>')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteRoom(<?php echo $room['room_id']; ?>, '<?php echo $room['room_number']; ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Room Modal -->
    <div class="modal fade" id="addRoomModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="hotel_id" class="form-label">Hotel</label>
                            <select class="form-control" name="hotel_id" id="add_hotel_id" required>
                                <option value="">Select Hotel</option>
                                <?php 
                                mysqli_data_seek($hotels, 0);
                                while ($hotel = mysqli_fetch_assoc($hotels)): 
                                ?>
                                    <option value="<?php echo $hotel['hotel_id']; ?>"><?php echo $hotel['hotel_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="room_number" class="form-label">Room Number</label>
                            <input type="text" class="form-control" name="room_number" required>
                        </div>
                        <div class="mb-3">
                            <label for="category_id" class="form-label">Category</label>
                            <select class="form-control" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php 
                                mysqli_data_seek($categories, 0);
                                while ($category = mysqli_fetch_assoc($categories)): 
                                ?>
                                    <option value="<?php echo $category['category_id']; ?>"><?php echo $category['hotel_name']; ?> - <?php echo $category['category_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Room Status</label>
                            <select class="form-control" name="status" required>
                                <option value="available">Available</option>
                                <option value="unavailable">Unavailable</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Room</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Room Modal -->
    <div class="modal fade" id="editRoomModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="room_id" id="edit_room_id">
                        <div class="mb-3">
                            <label for="edit_hotel_id" class="form-label">Hotel</label>
                            <select class="form-control" name="hotel_id" id="edit_hotel_id" required>
                                <option value="">Select Hotel</option>
                                <?php 
                                mysqli_data_seek($hotels, 0);
                                while ($hotel = mysqli_fetch_assoc($hotels)): 
                                ?>
                                    <option value="<?php echo $hotel['hotel_id']; ?>"><?php echo $hotel['hotel_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_room_number" class="form-label">Room Number</label>
                            <input type="text" class="form-control" name="room_number" id="edit_room_number" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_category_id" class="form-label">Category</label>
                            <select class="form-control" name="category_id" id="edit_category_id" required>
                                <option value="">Select Category</option>
                                <?php 
                                mysqli_data_seek($categories, 0);
                                while ($category = mysqli_fetch_assoc($categories)): 
                                ?>
                                    <option value="<?php echo $category['category_id']; ?>"><?php echo $category['hotel_name']; ?> - <?php echo $category['category_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Room Status</label>
                            <select class="form-control" name="status" id="edit_status" required>
                                <option value="available">Available</option>
                                <option value="unavailable">Unavailable</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Room</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Room Modal -->
    <div class="modal fade" id="deleteRoomModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="room_id" id="delete_room_id">
                        <p>Are you sure you want to delete room "<span id="delete_room_number"></span>"?</p>
                        <p class="text-danger"><small>This action cannot be undone.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Room</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store the current filter selection
        const currentFilterHotelId = <?php echo $filter_hotel_id; ?>;
        
        // Pre-select the hotel in the add form based on current filter
        document.addEventListener('DOMContentLoaded', function() {
            const addHotelDropdown = document.getElementById('add_hotel_id');
            if (currentFilterHotelId > 0) {
                addHotelDropdown.value = currentFilterHotelId;
            }
        });

        function editRoom(id, roomNumber, categoryId, hotelId, status) {
            document.getElementById('edit_room_id').value = id;
            document.getElementById('edit_room_number').value = roomNumber;
            document.getElementById('edit_category_id').value = categoryId;
            document.getElementById('edit_hotel_id').value = hotelId;
            document.getElementById('edit_status').value = status;
            new bootstrap.Modal(document.getElementById('editRoomModal')).show();
        }

        function deleteRoom(id, roomNumber) {
            document.getElementById('delete_room_id').value = id;
            document.getElementById('delete_room_number').textContent = roomNumber;
            new bootstrap.Modal(document.getElementById('deleteRoomModal')).show();
        }
    </script>
</body>
</html>