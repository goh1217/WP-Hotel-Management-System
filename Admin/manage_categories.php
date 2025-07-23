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

$message = "";
$message_type = "";

// Function to generate unique room numbers
function generateUniqueRoomNumber($hotel_id, $room_code, $conn) {
    $attempts = 1;
    $max_attempts = 1000;
    
    do {
        $room_number = strtoupper($room_code) . sprintf('%03d', $attempts);
        
        $check_sql = "SELECT COUNT(*) as count FROM room WHERE room_number = ? AND hotel_id = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "si", $room_number, $hotel_id);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        $exists = mysqli_fetch_assoc($result)['count'] > 0;
        
        if (!$exists) {
            return $room_number;
        }
        
        $attempts++;
    } while ($attempts <= $max_attempts);
    
    throw new Exception("Unable to generate unique room number after $max_attempts attempts");
}

// Function to validate image upload with improved security and higher size limit
function validateImageUpload($file) {
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $max_size = 15 * 1024 * 1024;

    
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception("Only JPEG, JPG, PNG, and WebP images are allowed.");
    }
    
    if ($file['size'] > $max_size) {
        throw new Exception("Image size must be less than 15MB. Current size: " . round($file['size'] / (1024 * 1024), 2) . "MB");
    }
    
    // Check if file is actually an image
    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        throw new Exception("File is not a valid image.");
    }
    
    return true;
}

// Function to check if an image file actually exists with better path handling
function imageExists($filename) {
    if (empty($filename)) return false;
    $imagePath = '../User/Room Image/' . $filename;
    return file_exists($imagePath) && is_readable($imagePath);
}

// Function to generate filename based on hotel and category
function generateFileName($hotel_name, $category_name, $extension) {
    $hotel_clean = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $hotel_name));
    $category_clean = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $category_name));
    return $hotel_clean . '_' . $category_clean . '.' . $extension;
}

// Function to handle image upload with better error handling
function handleImageUpload($hotel_name, $category_name, $old_image = null) {
    $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
    $upload_dir = '../User/Room Image/';
    
    // Check if upload directory exists, create if it doesn't
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception("Failed to create upload directory");
        }
    }
    
    if (!isset($_FILES['room_image']) || $_FILES['room_image']['error'] == UPLOAD_ERR_NO_FILE) {
        return $old_image;
    }
    
    if ($_FILES['room_image']['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini (try a smaller image)',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the maximum allowed size',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        
        $error_message = isset($upload_errors[$_FILES['room_image']['error']]) 
            ? $upload_errors[$_FILES['room_image']['error']] 
            : 'Unknown upload error';
            
        throw new Exception("Upload error: " . $error_message);
    }
    
    // Validate the uploaded image
    validateImageUpload($_FILES['room_image']);
    
    $file_extension = strtolower(pathinfo($_FILES['room_image']['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_types)) {
        throw new Exception("Only JPG, JPEG, PNG, and WEBP files are allowed");
    }
    
    // Generate new filename
    $new_filename = generateFileName($hotel_name, $category_name, $file_extension);
    $target_path = $upload_dir . $new_filename;
    
    // Delete old image if it exists and is different from new one
    if ($old_image && $old_image !== $new_filename && file_exists($upload_dir . $old_image)) {
        if (!unlink($upload_dir . $old_image)) {
            // Log error instead of throwing exception, as this is not critical
            error_log("Failed to delete old image: " . $upload_dir . $old_image);
        }
    }
    
    if (!move_uploaded_file($_FILES['room_image']['tmp_name'], $target_path)) {
        throw new Exception("Failed to upload image to: " . $target_path . ". Please check folder permissions.");
    }
    
    // Verify the uploaded file
    if (!file_exists($target_path)) {
        throw new Exception("Image upload failed - file not found after upload");
    }
    
    return $new_filename;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $category_name = cleanInput($_POST['category_name']);
                    $description = cleanInput($_POST['description']);
                    $hotel_id = validateNumber($_POST['hotel_id'], 1);
                    $price = validateNumber($_POST['price'], 0.01);
                    $room_count = validateNumber($_POST['room_count'], 1);
                    $discount = validateNumber($_POST['discount'], 0);
                    $room_code = cleanInput($_POST['room_code']);
                    
                    if (empty($category_name) || empty($description) || empty($room_code)) {
                        throw new Exception("All fields are required");
                    }
                    
                    if ($room_count < 1) {
                        throw new Exception("Room count must be at least 1");
                    }
                    
                    // Validate room code format (should start with letter(s) and can end with numbers)
                    if (!preg_match('/^[A-Za-z]+[0-9]*$/', $room_code)) {
                        throw new Exception("Room code must start with letters and can optionally end with numbers (e.g., 'D', 'DLX', 'SV101')");
                    }
                    
                    // Check if category name already exists for this hotel
                    $check_sql = "SELECT COUNT(*) as count FROM room_category WHERE category_name = ? AND hotel_id = ?";
                    $check_stmt = mysqli_prepare($conn, $check_sql);
                    mysqli_stmt_bind_param($check_stmt, "si", $category_name, $hotel_id);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    $existing_count = mysqli_fetch_assoc($check_result)['count'];
                    
                    if ($existing_count > 0) {
                        throw new Exception("A category with this name already exists for the selected hotel");
                    }
                    
                    // Get hotel name for filename generation
                    $hotel_query = mysqli_query($conn, "SELECT hotel_name FROM hotel WHERE hotel_id = ?");
                    $hotel_stmt = mysqli_prepare($conn, "SELECT hotel_name FROM hotel WHERE hotel_id = ?");
                    mysqli_stmt_bind_param($hotel_stmt, "i", $hotel_id);
                    mysqli_stmt_execute($hotel_stmt);
                    $hotel_result = mysqli_stmt_get_result($hotel_stmt);
                    $hotel_data = mysqli_fetch_assoc($hotel_result);
                    
                    if (!$hotel_data) {
                        throw new Exception("Invalid hotel selected");
                    }
                    
                    $room_image = handleImageUpload($hotel_data['hotel_name'], $category_name);
                    
                    if (!$room_image) {
                        throw new Exception("Room image is required");
                    }
                    
                    // Start transaction
                    mysqli_begin_transaction($conn);
                    
                    // Insert category
                    $sql = "INSERT INTO room_category (hotel_id, category_name, description, price, discount, room_image) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "issdds", $hotel_id, $category_name, $description, $price, $discount, $room_image);
                    
                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception("Error adding category: " . mysqli_error($conn));
                    }
                    
                    $category_id = mysqli_insert_id($conn);
                    
                    // Create rooms for this category using unique room numbers
                    for ($i = 1; $i <= $room_count; $i++) {
                        $room_number = generateUniqueRoomNumber($hotel_id, $room_code, $conn);
                        $room_sql = "INSERT INTO room (room_number, category_id, hotel_id, status) VALUES (?, ?, ?, 'available')";
                        $room_stmt = mysqli_prepare($conn, $room_sql);
                        mysqli_stmt_bind_param($room_stmt, "sii", $room_number, $category_id, $hotel_id);
                        
                        if (!mysqli_stmt_execute($room_stmt)) {
                            throw new Exception("Error creating room $room_number: " . mysqli_error($conn));
                        }
                    }
                    
                    mysqli_commit($conn);
                    $message = "Room category added successfully with $room_count rooms!";
                    $message_type = "success";
                    
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    error_log("Error adding category: " . $e->getMessage());
                    $message = "Error: " . $e->getMessage();
                    $message_type = "danger";
                }
                break;
                
            case 'edit':
                try {
                    $category_id = mysqli_real_escape_string($conn, $_POST['category_id']);
                    $category_name = mysqli_real_escape_string($conn, trim($_POST['category_name']));
                    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
                    $hotel_id = mysqli_real_escape_string($conn, $_POST['hotel_id']);
                    $price = mysqli_real_escape_string($conn, $_POST['price']);
                    $room_count = mysqli_real_escape_string($conn, $_POST['room_count']);
                    $discount = mysqli_real_escape_string($conn, $_POST['discount']);
                    $delete_current_image = isset($_POST['delete_current_image']) ? $_POST['delete_current_image'] : 'no';
                    
                    if (empty($category_name) || empty($description) || empty($hotel_id) || empty($price) || empty($room_count)) {
                        throw new Exception("All fields are required");
                    }
                    
                    if ($room_count < 1) {
                        throw new Exception("Room count must be at least 1");
                    }
                    
                    // Check if category name already exists for this hotel (excluding current category)
                    $check_sql = "SELECT COUNT(*) as count FROM room_category WHERE category_name = ? AND hotel_id = ? AND category_id != ?";
                    $check_stmt = mysqli_prepare($conn, $check_sql);
                    mysqli_stmt_bind_param($check_stmt, "sii", $category_name, $hotel_id, $category_id);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    $existing_count = mysqli_fetch_assoc($check_result)['count'];
                    
                    if ($existing_count > 0) {
                        throw new Exception("A category with this name already exists for the selected hotel");
                    }
                    
                    // Get current category data
                    $current_query = mysqli_query($conn, "SELECT rc.room_image, h.hotel_name FROM room_category rc JOIN hotel h ON rc.hotel_id = h.hotel_id WHERE rc.category_id = '$category_id'");
                    $current_data = mysqli_fetch_assoc($current_query);
                    
                    if (!$current_data) {
                        throw new Exception("Category not found");
                    }
                    
                    // Get new hotel name
                    $hotel_query = mysqli_query($conn, "SELECT hotel_name FROM hotel WHERE hotel_id = '$hotel_id'");
                    $hotel_data = mysqli_fetch_assoc($hotel_query);
                    
                    if (!$hotel_data) {
                        throw new Exception("Invalid hotel selected");
                    }
                    
                    // Handle image
                    $room_image = $current_data['room_image'];
                    
                    // If user wants to delete current image
                    if ($delete_current_image === 'yes') {
                        if ($room_image && file_exists('../User/Room Image/' . $room_image)) {
                            unlink('../User/Room Image/' . $room_image);
                        }
                        $room_image = null;
                    }
                    
                    // Handle new image upload
                    if (isset($_FILES['room_image']) && $_FILES['room_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $room_image = handleImageUpload($hotel_data['hotel_name'], $category_name, $room_image);
                    }
                    
                    // Start transaction
                    mysqli_begin_transaction($conn);
                    
                    // Update category
                    $sql = "UPDATE room_category SET category_name = ?, description = ?, hotel_id = ?, price = ?, discount = ?, room_image = ? WHERE category_id = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "ssddssi", $category_name, $description, $hotel_id, $price, $discount, $room_image, $category_id);
                    
                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception("Error updating category: " . mysqli_error($conn));
                    }
                    
                    // Get current room count
                    $current_room_query = mysqli_query($conn, "SELECT COUNT(*) as current_count FROM room WHERE category_id = '$category_id'");
                    $current_room_count = mysqli_fetch_assoc($current_room_query)['current_count'];
                    
                    // Adjust room count
                    if ($room_count > $current_room_count) {
                        // Add more rooms
                        $rooms_to_add = $room_count - $current_room_count;
                        
                        for ($i = 1; $i <= $rooms_to_add; $i++) {
                            $room_number = generateUniqueRoomNumber($hotel_id, $room_code, $conn);
                            $room_sql = "INSERT INTO room (room_number, category_id, hotel_id, status) VALUES ('$room_number', '$category_id', '$hotel_id', 'available')";
                            if (!mysqli_query($conn, $room_sql)) {
                                throw new Exception("Error adding new rooms: " . mysqli_error($conn));
                            }
                        }
                    } elseif ($room_count < $current_room_count) {
                        // Remove excess rooms (only if they're available and not booked)
                        $rooms_to_remove = $current_room_count - $room_count;
                        
                        // Get rooms that can be safely removed
                        $available_rooms_query = mysqli_query($conn, "
                            SELECT r.room_id, r.room_number 
                            FROM room r
                            WHERE r.category_id = '$category_id' 
                            AND r.status = 'available'
                            AND r.room_id NOT IN (
                                SELECT DISTINCT b.room_id 
                                FROM booking b 
                                WHERE b.room_id = r.room_id 
                                AND b.booking_status IN ('confirmed', 'pending', 'requesting')
                                AND b.check_out_date >= CURDATE()
                            )
                            ORDER BY r.room_id DESC 
                            LIMIT $rooms_to_remove
                        ");
                        
                        $available_count = mysqli_num_rows($available_rooms_query);
                        
                        if ($available_count < $rooms_to_remove) {
                            // Get detailed booking information for error message
                            $booked_rooms_query = mysqli_query($conn, "
                                SELECT r.room_number, 
                                       COUNT(b.booking_id) as booking_count,
                                       MIN(b.check_in_date) as earliest_checkin,
                                       MAX(b.check_out_date) as latest_checkout
                                FROM room r
                                LEFT JOIN booking b ON r.room_id = b.room_id
                                WHERE r.category_id = '$category_id' 
                                AND (b.booking_status IN ('confirmed', 'pending', 'requesting') 
                                     AND b.check_out_date >= CURDATE())
                                GROUP BY r.room_id, r.room_number
                            ");
                            
                            $booking_details = [];
                            while ($booked_room = mysqli_fetch_assoc($booked_rooms_query)) {
                                $booking_details[] = "Room {$booked_room['room_number']} has active bookings until {$booked_room['latest_checkout']}";
                            }
                            
                            $booked_details = !empty($booking_details) ? 
                                "<br>Active bookings:<br>- " . implode("<br>- ", $booking_details) : "";
                                
                            throw new Exception("Cannot reduce room count from $current_room_count to $room_count. 
                                You need to remove $rooms_to_remove rooms, but only $available_count are available.$booked_details");
                        }
                        
                        // Remove the available rooms
                        while ($room = mysqli_fetch_assoc($available_rooms_query)) {
                            $delete_room_sql = "DELETE FROM room WHERE room_id = ?";
                            $delete_stmt = mysqli_prepare($conn, $delete_room_sql);
                            mysqli_stmt_bind_param($delete_stmt, "i", $room['room_id']);
                            if (!mysqli_stmt_execute($delete_stmt)) {
                                throw new Exception("Error removing room {$room['room_number']}: " . mysqli_error($conn));
                            }
                        }
                    }
                    
                    mysqli_commit($conn);
                    $message = "Room category updated successfully!";
                    $message_type = "success";
                    
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $message = "Error: " . $e->getMessage();
                    $message_type = "danger";
                }
                break;
                
            case 'delete':
                try {
                    $category_id = mysqli_real_escape_string($conn, $_POST['category_id']);
                    
                    // Begin transaction
                    mysqli_begin_transaction($conn);
                    
                    // Check if any rooms in this category are booked
                    $check_sql = "SELECT COUNT(*) as count,
                                 GROUP_CONCAT(DISTINCT CONCAT('Room ', r.room_number, ': ', 
                                    DATE_FORMAT(b.check_in_date, '%b %d, %Y'), ' to ', 
                                    DATE_FORMAT(b.check_out_date, '%b %d, %Y'), 
                                    ' (', b.booking_status, ')') SEPARATOR '<br>') as booking_details
                                 FROM room r 
                                 JOIN booking b ON r.room_id = b.room_id 
                                 WHERE r.category_id = ? 
                                 AND b.booking_status IN ('confirmed', 'pending', 'requesting')
                                 AND b.check_out_date >= CURDATE()";
                    $check_stmt = mysqli_prepare($conn, $check_sql);
                    mysqli_stmt_bind_param($check_stmt, "i", $category_id);
                    mysqli_stmt_execute($check_stmt);
                    $result = mysqli_stmt_get_result($check_stmt);
                    $row = mysqli_fetch_assoc($result);
                    
                    if ($row['count'] > 0) {
                        throw new Exception("Cannot delete category. It has rooms with active or future bookings:<br>" . $row['booking_details']);
                    }
                    
                    // Get image filename to delete
                    $img_query = "SELECT room_image FROM room_category WHERE category_id = ?";
                    $img_stmt = mysqli_prepare($conn, $img_query);
                    mysqli_stmt_bind_param($img_stmt, "i", $category_id);
                    mysqli_stmt_execute($img_stmt);
                    $img_result = mysqli_stmt_get_result($img_stmt);
                    $img_data = mysqli_fetch_assoc($img_result);
                    $image_to_delete = $img_data['room_image'];
                    
                    // First delete all rooms in this category that don't have bookings
                    $room_sql = "DELETE FROM room WHERE category_id = ?";
                    $room_stmt = mysqli_prepare($conn, $room_sql);
                    mysqli_stmt_bind_param($room_stmt, "i", $category_id);
                    mysqli_stmt_execute($room_stmt);
                    
                    // Then delete the category
                    $cat_sql = "DELETE FROM room_category WHERE category_id = ?";
                    $cat_stmt = mysqli_prepare($conn, $cat_sql);
                    mysqli_stmt_bind_param($cat_stmt, "i", $category_id);
                    
                    if (mysqli_stmt_execute($cat_stmt)) {
                        // Delete the image file if exists
                        if ($image_to_delete && file_exists('../User/Room Image/' . $image_to_delete)) {
                            unlink('../User/Room Image/' . $image_to_delete);
                        }
                        mysqli_commit($conn);
                        $message = "Room category deleted successfully!";
                        $message_type = "success";
                    } else {
                        throw new Exception("Error deleting category: " . mysqli_error($conn));
                    }
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $message = "Error: " . $e->getMessage();
                    $message_type = "danger";
                }
                break;
        }
    }
}

// Fetch all room categories with hotel names and room counts
$sql = "SELECT rc.*, h.hotel_name, COUNT(r.room_id) as room_count 
        FROM room_category rc 
        LEFT JOIN hotel h ON rc.hotel_id = h.hotel_id
        LEFT JOIN room r ON rc.category_id = r.category_id";

// Apply hotel filter if selected
if ($filter_hotel_id > 0) {
    $sql .= " WHERE rc.hotel_id = $filter_hotel_id";
}

$sql .= " GROUP BY rc.category_id, rc.hotel_id, rc.category_name, rc.description, rc.price, rc.discount, rc.room_image, h.hotel_name
          ORDER BY rc.category_id ASC";

$categories_result = mysqli_query($conn, $sql);

if (!$categories_result) {
    error_log("SQL Error: " . mysqli_error($conn));
    echo "<!-- SQL Error: " . mysqli_error($conn) . " -->";
}

$categories = [];
while ($category = mysqli_fetch_assoc($categories_result)) {
    $category['image_exists'] = imageExists($category['room_image']);
    // Debug: Add this temporarily to see what's happening
    // error_log("Category " . $category['category_id'] . ": " . $category['category_name'] . " - Image: " . ($category['room_image'] ?: 'NULL') . " - Exists: " . ($category['image_exists'] ? 'Yes' : 'No'));
    $categories[] = $category;
}

// Debug: Add this temporarily to check the count
// error_log("Total categories fetched: " . count($categories));

$hotels = mysqli_query($conn, "SELECT * FROM hotel ORDER BY hotel_name");
$hotels_for_filter = mysqli_query($conn, "SELECT * FROM hotel ORDER BY hotel_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Room Categories - Hotel Management System</title>
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
        .table th { 
            background-color: #f8f9fa; 
        }
        .room-image-preview {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e9ecef;
        }
        .image-upload-preview {
            max-width: 200px;
            max-height: 200px;
            display: none;
            margin-top: 10px;
            border-radius: 8px;
            border: 2px solid #e9ecef;
        }
        .no-image-placeholder {
            width: 80px;
            height: 60px;
            background-color: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #6c757d;
            cursor: pointer;
        }
        .image-container {
            position: relative;
            display: inline-block;
        }
        .delete-image-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220, 53, 69, 0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        .delete-image-btn:hover {
            background: rgba(220, 53, 69, 1);
            transform: scale(1.1);
        }
        .no-current-image-text {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #6c757d;
        }
        .no-current-image-text i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
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
                        <a class="nav-link active" href="manage_categories.php"><i class="fas fa-tags me-2"></i> Room Categories</a>
                        <a class="nav-link" href="manage_rooms.php"><i class="fas fa-bed me-2"></i> Room Details</a>
                        <a class="nav-link" href="manage_packages.php"><i class="fas fa-gift me-2"></i> Packages</a>
                        <a class="nav-link" href="view_bookings.php"><i class="fas fa-calendar-check me-2"></i> Bookings
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
                        <h2><i class="fas fa-tags me-2"></i> Manage Room Categories</h2>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="fas fa-plus me-2"></i> Add New Category
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
                    </div>

                    <!-- Categories Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Image</th>
                                    <th>Hotel</th>
                                    <th>Category Name</th>
                                    <th>Description</th>
                                    <th>Price</th>
                                    <th>Discount</th>
                                    <th>Room Count</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($categories) > 0): ?>
                                    <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td><?php echo $category['category_id']; ?></td>
                                        <td>
                                            <?php if ($category['room_image'] && $category['image_exists']): ?>
                                                <img src="../User/Room Image/<?php echo htmlspecialchars($category['room_image']); ?>"
                                                     class="room-image-preview" alt="Room Image">
                                            <?php else: ?>
                                                <div class="no-image-placeholder">
                                                    <?php if (empty($category['room_image'])): ?>
                                                        <div class="text-center">
                                                            <i class="fas fa-image-slash text-muted"></i>
                                                            <small class="d-block text-muted">No Image</small>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-center">
                                                            <i class="fas fa-exclamation-triangle text-warning"></i>
                                                            <small class="d-block text-muted">Missing</small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($category['hotel_name'] ?: 'Unknown Hotel'); ?></td>
                                        <td><strong><?php echo htmlspecialchars($category['category_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars(substr($category['description'], 0, 60)); ?><?php if (strlen($category['description']) > 60): ?>...<?php endif; ?></td>
                                        <td><strong>$<?php echo number_format($category['price'], 2); ?></strong></td>
                                        <td><?php echo $category['discount']; ?>%</td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $category['room_count']; ?> rooms</span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" onclick="editCategory(
                                                <?php echo $category['category_id']; ?>, 
                                                '<?php echo addslashes($category['category_name']); ?>', 
                                                '<?php echo addslashes($category['description']); ?>', 
                                                <?php echo $category['hotel_id']; ?>, 
                                                <?php echo $category['price']; ?>, 
                                                '<?php echo addslashes($category['room_image'] ?: ''); ?>', 
                                                <?php echo $category['room_count']; ?>,
                                                <?php echo $category['image_exists'] ? 'true' : 'false'; ?>,
                                                <?php echo $category['discount']; ?>
                                            )">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteCategory(<?php echo $category['category_id']; ?>, '<?php echo addslashes($category['category_name']); ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <div class="no-current-image-text">
                                                <i class="fas fa-tags fa-2x mb-2 text-muted"></i>
                                                <p class="text-muted">No room categories found. Add your first category to get started!</p>
                                                <?php if ($filter_hotel_id > 0): ?>
                                                    <p class="text-muted"><small>Try selecting "All Hotels" to see all categories.</small></p>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Add Category Modal -->
                <div class="modal fade" id="addCategoryModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fas fa-plus me-2"></i> Add New Room Category</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" enctype="multipart/form-data" id="addCategoryForm">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="add">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="hotel_id" class="form-label"><i class="fas fa-building me-1"></i> Hotel *</label>
                                            <select class="form-select" name="hotel_id" required>
                                                <option value="">Select Hotel</option>
                                                <?php 
                                                mysqli_data_seek($hotels, 0);
                                                while ($hotel = mysqli_fetch_assoc($hotels)): 
                                                ?>
                                                    <option value="<?php echo $hotel['hotel_id']; ?>"><?php echo htmlspecialchars($hotel['hotel_name']); ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="category_name" class="form-label"><i class="fas fa-tag me-1"></i> Category Name *</label>
                                            <input type="text" class="form-control" name="category_name" required placeholder="e.g., Deluxe Room">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="room_code" class="form-label"><i class="fas fa-code me-1"></i> Room Code *</label>
                                            <input type="text" class="form-control" name="room_code" required 
                                                   placeholder="e.g., D, DLX, SV" 
                                                   pattern="^[A-Za-z]+[0-9]*$"
                                                   title="Must start with letters, can optionally end with numbers">
                                            <div class="form-text">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Room numbers will be: [Code]1, [Code]2, etc. (e.g., D1, D2, D3...)
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="room_count" class="form-label"><i class="fas fa-bed me-1"></i> Number of Rooms *</label>
                                            <input type="number" min="1" class="form-control" name="room_count" required placeholder="1">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="description" class="form-label"><i class="fas fa-align-left me-1"></i> Description *</label>
                                            <textarea class="form-control" name="description" rows="3" required placeholder="Describe the room category features and amenities..."></textarea>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="price" class="form-label"><i class="fas fa-dollar-sign me-1"></i> Price per Night ($) *</label>
                                            <input type="number" step="0.01" min="0" class="form-control" name="price" required placeholder="0.00">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="discount" class="form-label"><i class="fas fa-percent me-1"></i> Discount (%) *</label>
                                            <select class="form-select" name="discount" required>
                                                <option value="0">No Discount (0%)</option>
                                                <option value="5">5%</option>
                                                <option value="10">10%</option>
                                                <option value="15">15%</option>
                                                <option value="20">20%</option>
                                                <option value="25">25%</option>
                                                <option value="30">30%</option>
                                                <option value="35">35%</option>
                                                <option value="40">40%</option>
                                                <option value="45">45%</option>
                                                <option value="50">50%</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="room_image" class="form-label"><i class="fas fa-camera me-1"></i> Room Image *</label>
                                            <input type="file" class="form-control" name="room_image" accept="image/jpeg,image/jpg,image/png,image/webp" required onchange="previewImage(this, 'add_preview')">
                                            <div class="form-text">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Accepted formats: JPG, JPEG, PNG, WEBP (Max: 15MB)
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="image_preview" class="form-label"><i class="fas fa-image me-1"></i> Image Preview</label>
                                        <img id="add_preview" class="image-upload-preview" style="display: none;">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Add Category
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Edit Category Modal -->
                <div class="modal fade" id="editCategoryModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Room Category</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" enctype="multipart/form-data" id="editCategoryForm">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="edit">  
                                    <input type="hidden" name="category_id" id="edit_category_id">      
                                    <input type="hidden" name="delete_current_image" id="delete_current_image" value="no">  
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="edit_hotel_id" class="form-label"><i class="fas fa-building me-1"></i> Hotel *</label>
                                            <select class="form-select" name="hotel_id" id="edit_hotel_id" required>
                                                <option value="">Select Hotel</option>
                                                <?php 
                                                mysqli_data_seek($hotels, 0);
                                                while ($hotel = mysqli_fetch_assoc($hotels)): 
                                                ?>
                                                    <option value="<?php echo $hotel['hotel_id']; ?>"><?php echo htmlspecialchars($hotel['hotel_name']); ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="edit_category_name" class="form-label"><i class="fas fa-tag me-1"></i> Category Name *</label>
                                            <input type="text" class="form-control" name="category_name" id="edit_category_name" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="edit_description" class="form-label"><i class="fas fa-align-left me-1"></i> Description *</label>
                                            <textarea class="form-control" name="description" id="edit_description" rows="3" required></textarea>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="edit_price" class="form-label"><i class="fas fa-dollar-sign me-1"></i> Price per Night ($) *</label>
                                            <input type="number" step="0.01" min="0" class="form-control" name="price" id="edit_price" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="edit_discount" class="form-label"><i class="fas fa-percent me-1"></i> Discount (%) *</label>
                                            <select class="form-select" name="discount" id="edit_discount" required>
                                                <option value="0">No Discount (0%)</option>
                                                <option value="5">5%</option>
                                                <option value="10">10%</option>
                                                <option value="15">15%</option>
                                                <option value="20">20%</option>
                                                <option value="25">25%</option>
                                                <option value="30">30%</option>
                                                <option value="35">35%</option>
                                                <option value="40">40%</option>
                                                <option value="45">45%</option>
                                                <option value="50">50%</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="edit_room_count" class="form-label"><i class="fas fa-bed me-1"></i> Number of Rooms *</label>
                                            <input type="number" min="1" class="form-control" name="room_count" id="edit_room_count" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="edit_room_image" class="form-label"><i class="fas fa-camera me-1"></i> New Room Image (Optional)</label>
                                            <input type="file" class="form-control" name="room_image" accept="image/jpeg,image/jpg,image/png,image/webp" onchange="previewImage(this, 'edit_preview')">
                                            <div class="form-text">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Accepted formats: JPG, JPEG, PNG, WEBP (Max: 15MB)
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-image me-1"></i> Current Image</label>
                                        <div id="image_display_area"></div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="edit_room_image" class="form-label"><i class="fas fa-camera me-1"></i> New Room Image (Optional)</label>
                                        <div class="form-text">
                                            Accepted formats: JPG, JPEG, PNG, WEBP (Max: 15MB). Leave empty to keep current image.
                                        </div>
                                        <img id="edit_preview" class="image-upload-preview" style="display: none;">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </button>
                                    <button type="submit" class="btn btn-warning" onclick="return confirmUpdate()">
                                        <i class="fas fa-save me-1"></i> Update Category
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Delete Category Modal -->
                <div class="modal fade" id="deleteCategoryModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header border-0">
                                <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i> Delete Room Category</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body text-center">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="category_id" id="delete_category_id">
                                    
                                    <div class="mb-3">
                                        <i class="fas fa-trash-alt fa-4x text-danger mb-3"></i>
                                        <h5>Are you sure?</h5>
                                        <p class="mb-2">You are about to delete the category:</p>
                                        <p class="fw-bold text-primary"><span id="delete_category_name"></span></p>
                                    </div>
                                    
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Warning:</strong> This action cannot be undone and will also delete the associated image file.
                                        <p id="delete_warning" class="mt-2 mb-0"></p>
                                    </div>
                                </div>
                                <div class="modal-footer justify-content-center">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </button>
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-trash me-1"></i> Delete Category
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }
        
        function editCategory(id, name, description, hotelId, price, image, roomCount, imageExists, discount) {
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_category_name').value = name;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_hotel_id').value = hotelId;
            document.getElementById('edit_price').value = price;
            document.getElementById('edit_room_count').value = roomCount;
            document.getElementById('edit_discount').value = discount;
            document.getElementById('delete_current_image').value = 'no';
            
            const imageDisplayArea = document.getElementById('image_display_area');
            if (image && imageExists) {
                imageDisplayArea.innerHTML = `
                    <div class="image-container">
                        <img src="../User/Room Image/${image}" class="image-upload-preview" style="display: block;" alt="Current Image">
                        <button type="button" class="delete-image-btn" onclick="deleteCurrentImage()" title="Delete Image">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
            } else {
                imageDisplayArea.innerHTML = `
                    <div class="no-current-image-text">
                        <i class="fas fa-image fa-2x mb-2 text-muted"></i><br>
                        <span>No current image</span>
                    </div>
                `;
            }
            
            new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
        }
        
        function deleteCurrentImage() {
            Swal.fire({
                title: 'Delete Current Image',
                text: 'Are you sure you want to delete the current image?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete_current_image').value = 'yes';
                    
                    // Replace image with "No current image" placeholder
                    const imageDisplayArea = document.getElementById('image_display_area');
                    imageDisplayArea.innerHTML = `
                        <div class="no-current-image-text">
                            <i class="fas fa-image fa-2x mb-2 text-muted"></i><br>
                            <span>No current image</span>
                        </div>
                    `;
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Image Marked for Deletion',
                        text: 'The image will be deleted when you update the category.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            });
        }
        
        function confirmUpdate() {
            Swal.fire({
                title: 'Update Category?',
                text: 'Are you sure you want to update this room category? Changes to room count may affect room availability.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, update it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('editCategoryForm').submit();
                }
            });
            return false; // Prevent default form submission
        }
        
        function deleteCategory(id, name) {
            document.getElementById('delete_category_id').value = id;
            document.getElementById('delete_category_name').textContent = name;
            
            // Additional warning text about booked rooms
            document.getElementById('delete_warning').innerHTML = 
                'This will delete all available rooms in this category. Rooms that are currently booked or have pending bookings will prevent deletion.';
            
            new bootstrap.Modal(document.getElementById('deleteCategoryModal')).show();
        }
        
        // Add real-time room code preview
        document.addEventListener('DOMContentLoaded', function() {
            const roomCodeInput = document.querySelector('input[name="room_code"]');
            const roomCountInput = document.querySelector('input[name="room_count"]');
            
            function updateRoomPreview() {
                const code = roomCodeInput.value.toUpperCase();
                const count = parseInt(roomCountInput.value) || 1;
                const helpText = roomCodeInput.nextElementSibling;
                
                if (code) {
                    let examples = [];
                    for (let i = 1; i <= Math.min(count, 3); i++) {
                        examples.push(code + i);
                    }
                    if (count > 3) {
                        examples.push('...');
                    }
                    helpText.innerHTML = `<i class="fas fa-info-circle me-1"></i>Room numbers will be: ${examples.join(', ')}`;
                } else {
                    helpText.innerHTML = `<i class="fas fa-info-circle me-1"></i>Room numbers will be: [Code]1, [Code]2, etc. (e.g., D1, D2, D3...)`;
                }
            }
            
            if (roomCodeInput && roomCountInput) {
                roomCodeInput.addEventListener('input', updateRoomPreview);
                roomCountInput.addEventListener('input', updateRoomPreview);
            }
        });
    </script>
</body>
</html>