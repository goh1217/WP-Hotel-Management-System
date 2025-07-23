<?php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'hotel_management_system';

// Create connection
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Start session for both admin and user
session_start();

// Helper function to get pending booking count (updated to include cancellation requests)
function getPendingCount($conn) {
    $query = mysqli_query($conn, "
        SELECT COUNT(*) as count 
        FROM booking 
        WHERE (booking_status = 'requesting') 
        OR (booking_status = 'pending' AND payment_status = 'Paid')
    ");
    return mysqli_fetch_assoc($query)['count'];
}

// Get detailed breakdown of actions needed
function getActionBreakdown($conn) {
    $query = mysqli_query($conn, "
        SELECT 
        (SELECT COUNT(*) FROM booking WHERE booking_status = 'pending' AND payment_status = 'Paid') as verification_needed,
        (SELECT COUNT(*) FROM booking WHERE booking_status = 'requesting') as cancellation_requests,
        (SELECT COUNT(*) FROM booking WHERE booking_status = 'confirmed') as confirmed_bookings,
        (SELECT COUNT(*) FROM booking WHERE booking_status = 'cancelled') as cancelled_bookings,
        (SELECT COUNT(*) FROM booking WHERE payment_status = 'Unpaid') as unpaid_bookings,
        (SELECT COUNT(*) FROM booking WHERE booking_status = 'pending' AND payment_status = 'Unpaid') as pending_payment
    ");
    return mysqli_fetch_assoc($query);
}

// Simple input validation functions
function cleanInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function validateNumber($value, $min = 0) {
    if (!is_numeric($value) || $value < $min) {
        throw new Exception("Invalid number value");
    }
    return floatval($value);
}
?>