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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add':
                    $package_name = cleanInput($_POST['package_name']);
                    $description = cleanInput($_POST['description']);
                    $price = validateNumber($_POST['price'], 0.01);
                    $hotel_id = validateNumber($_POST['hotel_id'], 1);
                    
                    // Check if package name already exists for this hotel
                    $check_sql = "SELECT COUNT(*) as count FROM package WHERE package_name = ? AND hotel_id = ?";
                    $check_stmt = mysqli_prepare($conn, $check_sql);
                    mysqli_stmt_bind_param($check_stmt, "si", $package_name, $hotel_id);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    $existing_count = mysqli_fetch_assoc($check_result)['count'];
                    
                    if ($existing_count > 0) {
                        throw new Exception("A package with this name already exists for the selected hotel");
                    }
                    
                    $sql = "INSERT INTO package (package_name, description, price, hotel_id) VALUES (?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "ssdi", $package_name, $description, $price, $hotel_id);
                    mysqli_stmt_execute($stmt);
                    
                    $message = "Package added successfully!";
                    $message_type = "success";
                    break;
                    
                case 'edit':
                    $package_id = validateNumber($_POST['package_id'], 1);
                    $package_name = cleanInput($_POST['package_name']);
                    $description = cleanInput($_POST['description']);
                    $price = validateNumber($_POST['price'], 0.01);
                    $hotel_id = validateNumber($_POST['hotel_id'], 1);
                    
                    // Check if package name already exists for this hotel (excluding current package)
                    $check_sql = "SELECT COUNT(*) as count FROM package WHERE package_name = ? AND hotel_id = ? AND package_id != ?";
                    $check_stmt = mysqli_prepare($conn, $check_sql);
                    mysqli_stmt_bind_param($check_stmt, "sii", $package_name, $hotel_id, $package_id);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    $existing_count = mysqli_fetch_assoc($check_result)['count'];
                    
                    if ($existing_count > 0) {
                        throw new Exception("A package with this name already exists for the selected hotel");
                    }
                    
                    $stmt = mysqli_prepare($conn, "UPDATE package SET package_name = ?, description = ?, price = ?, hotel_id = ? WHERE package_id = ?");
                    mysqli_stmt_bind_param($stmt, "ssdii", $package_name, $description, $price, $hotel_id, $package_id);
                    mysqli_stmt_execute($stmt);
                    
                    $message = "Package updated successfully!";
                    $message_type = "success";
                    break;
                    
                case 'delete':
                    $package_id = validateNumber($_POST['package_id'], 1);
                    
                    // Check if package is used in any bookings first
                    $check_sql = "SELECT COUNT(*) as count FROM booking WHERE package_id = ?";
                    $check_stmt = mysqli_prepare($conn, $check_sql);
                    mysqli_stmt_bind_param($check_stmt, "i", $package_id);
                    mysqli_stmt_execute($check_stmt);
                    $result = mysqli_stmt_get_result($check_stmt);
                    $row = mysqli_fetch_assoc($result);
                    
                    if ($row['count'] > 0) {
                        throw new Exception("Cannot delete package. It is used in " . $row['count'] . " booking(s).");
                    }
                    
                    $stmt = mysqli_prepare($conn, "DELETE FROM package WHERE package_id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $package_id);
                    mysqli_stmt_execute($stmt);
                    
                    $message = "Package deleted successfully!";
                    $message_type = "success";
                    break;
            }
        } catch (Exception $e) {
            error_log("Error in package management: " . $e->getMessage());
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Get selected hotel filter (if any)
$filter_hotel_id = isset($_GET['filter_hotel']) ? intval($_GET['filter_hotel']) : 0;

// Build the SQL query with hotel information and optional filter
$sql = "SELECT p.*, h.hotel_name 
        FROM package p
        LEFT JOIN hotel h ON p.hotel_id = h.hotel_id";

// Apply hotel filter if selected
if ($filter_hotel_id > 0) {
    $sql .= " WHERE p.hotel_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $filter_hotel_id);
    mysqli_stmt_execute($stmt);
    $packages = mysqli_stmt_get_result($stmt);
} else {
    // Add sorting by ID
    $sql .= " ORDER BY p.package_id ASC";
    $packages = mysqli_query($conn, $sql);
}

// Fetch all hotels for dropdowns
$hotels = mysqli_query($conn, "SELECT * FROM hotel ORDER BY hotel_name");
$hotels_for_filter = mysqli_query($conn, "SELECT * FROM hotel ORDER BY hotel_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Packages - Hotel Management System</title>
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
        .btn-action-group {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .btn-action {
            min-width: 80px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 3px;
            width: 85px;
        }
        .table td {
            vertical-align: middle;
            padding: 10px;
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
                        <a class="nav-link active" href="manage_packages.php"><i class="fas fa-gift me-2"></i> Packages</a>
                        <a class="nav-link" href="view_bookings.php">
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
                        <h2><i class="fas fa-gift me-2"></i> Manage Packages</h2>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPackageModal" id="addPackageBtn">
                            <i class="fas fa-plus me-2"></i> Add New Package
                        </button>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Hotel Filter -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <form method="GET" id="filterForm" class="d-flex flex-column">
                                <label for="hotelFilter" class="form-label">Filter by Hotel:</label>
                                <select name="filter_hotel" class="form-select" id="hotelFilter" onchange="this.form.submit()">
                                    <option value="0">All Hotels</option>
                                    <?php while ($hotel = mysqli_fetch_assoc($hotels_for_filter)): ?>
                                        <option value="<?php echo $hotel['hotel_id']; ?>" <?php echo ($filter_hotel_id == $hotel['hotel_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($hotel['hotel_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </form>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Package Name</th>
                                    <th>Hotel</th>
                                    <th>Description</th>
                                    <th>Price</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($package = mysqli_fetch_assoc($packages)): ?>
                                <tr>
                                    <td><?php echo $package['package_id']; ?></td>
                                    <td><strong><?php echo $package['package_name']; ?></strong></td>
                                    <td>
                                        <?php if ($package['hotel_name']): ?>
                                            <?php echo $package['hotel_name']; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No hotel assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $package['description']; ?></td>
                                    <td>$<?php echo number_format($package['price'], 2); ?></td>
                                    <td>
                                        <div class="btn-action-group">
                                            <button class="btn btn-sm btn-warning btn-action" onclick="editPackage(
                                                <?php echo $package['package_id']; ?>, 
                                                '<?php echo addslashes($package['package_name']); ?>', 
                                                '<?php echo addslashes($package['description']); ?>', 
                                                <?php echo $package['price']; ?>,
                                                <?php echo $package['hotel_id'] ? $package['hotel_id'] : 'null'; ?>
                                            )">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-danger btn-action" onclick="deletePackage(<?php echo $package['package_id']; ?>, '<?php echo addslashes($package['package_name']); ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
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

    <!-- Add Package Modal -->
    <div class="modal fade" id="addPackageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Package</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="package_name" class="form-label">Package Name</label>
                            <input type="text" class="form-control" name="package_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="hotel_id" class="form-label">Hotel</label>
                            <select class="form-select" name="hotel_id" id="add_hotel_id" required>
                                <option value="">Select Hotel</option>
                                <?php 
                                mysqli_data_seek($hotels, 0);
                                while ($hotel = mysqli_fetch_assoc($hotels)): 
                                ?>
                                    <option value="<?php echo $hotel['hotel_id']; ?>">
                                        <?php echo htmlspecialchars($hotel['hotel_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="price" class="form-label">Price</label>
                            <input type="number" step="0.01" class="form-control" name="price" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Package</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Package Modal -->
    <div class="modal fade" id="editPackageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Package</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="package_id" id="edit_package_id">
                        <div class="mb-3">
                            <label for="edit_package_name" class="form-label">Package Name</label>
                            <input type="text" class="form-control" name="package_name" id="edit_package_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_hotel_id" class="form-label">Hotel</label>
                            <select class="form-select" name="hotel_id" id="edit_hotel_id" required>
                                <option value="">Select Hotel</option>
                                <?php 
                                mysqli_data_seek($hotels, 0);
                                while ($hotel = mysqli_fetch_assoc($hotels)): 
                                ?>
                                    <option value="<?php echo $hotel['hotel_id']; ?>">
                                        <?php echo htmlspecialchars($hotel['hotel_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_price" class="form-label">Price</label>
                            <input type="number" step="0.01" class="form-control" name="price" id="edit_price" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Package</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Package Modal -->
    <div class="modal fade" id="deletePackageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Package</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="package_id" id="delete_package_id">
                        <p>Are you sure you want to delete the package "<span id="delete_package_name"></span>"?</p>
                        <p class="text-danger"><small>This action cannot be undone.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Package</button>
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
        document.getElementById('addPackageBtn').addEventListener('click', function() {
            const hotelDropdown = document.getElementById('add_hotel_id');
            if (currentFilterHotelId > 0) {
                hotelDropdown.value = currentFilterHotelId;
            }
        });

        function editPackage(id, name, description, price, hotelId) {
            document.getElementById('edit_package_id').value = id;
            document.getElementById('edit_package_name').value = name;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_price').value = price;
            
            // Handle the hotel dropdown selection
            const hotelDropdown = document.getElementById('edit_hotel_id');
            if (hotelId !== null) {
                hotelDropdown.value = hotelId;
            } else {
                hotelDropdown.value = '';
            }
            
            new bootstrap.Modal(document.getElementById('editPackageModal')).show();
        }

        function deletePackage(id, name) {
            document.getElementById('delete_package_id').value = id;
            document.getElementById('delete_package_name').textContent = name;
            new bootstrap.Modal(document.getElementById('deletePackageModal')).show();
        }
    </script>
</body>
</html>