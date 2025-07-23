<?php
session_start();
require_once('connect/connection.php');

if (!isset($_SESSION['customer_id']) || !isset($_GET['booking_id'])) {
    echo "Unauthorized access.";
    exit;
}

$booking_id = intval($_GET['booking_id']);
$customer_id = $_SESSION['customer_id'];

$sql = "
    SELECT b.*, r.room_number, h.hotel_name, h.address, c.price, c.discount, cust.name,
           p.package_name, p.price AS package_price
    FROM Booking b
    JOIN Room r ON b.room_id = r.room_id
    JOIN Hotel h ON r.hotel_id = h.hotel_id
    JOIN customer cust ON b.customer_id = cust.customer_id
    LEFT JOIN Package p ON b.package_id = p.package_id
    JOIN room_category c ON r.category_id = c.category_id
    WHERE b.booking_id = $booking_id AND b.customer_id = $customer_id
";

$result = mysqli_query($connect, $sql);
if (mysqli_num_rows($result) !== 1) {
    echo "Booking not found.";
    exit;
}

$row = mysqli_fetch_assoc($result);
// Calculate nights
$nights = (strtotime($row['check_out_date']) - strtotime($row['check_in_date'])) / 86400;

// Base room price
$room_price = $row['price'] * $nights;

// Package total
$package_price = isset($row['package_price']) ? $row['package_price'] : 0;
$total_package_price = $package_price * $nights;

// Total before discount
$total_before_discount = $room_price + $total_package_price;

// Discount calculation
$discount_percent = isset($row['discount']) ? $row['discount'] : 0;
$discount_amount = ($total_before_discount * $discount_percent / 100);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Print Booking</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
        .receipt-box {
            margin-top: 40px;
            padding: 30px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, .15);
        }
    </style>
</head>
<body>
<div class="container receipt-box">
    <h2 class="text-center mb-4">Booking Receipt</h2>
    <table class="table table-bordered">
        <tr><th>Customer Name</th><td><?= $row['name'] ?></td></tr>
        <tr><th>Booking ID</th><td><?= $row['booking_id'] ?></td></tr>
        <tr><th>Hotel Name</th><td><?= htmlspecialchars($row['hotel_name']) ?></td></tr>
        <tr><th>Address</th><td><?= htmlspecialchars($row['address']) ?></td></tr>
        <tr><th>Room Number</th><td><?= htmlspecialchars($row['room_number']) ?></td></tr>
        <tr><th>Check-in Date</th><td><?= $row['check_in_date'] ?></td></tr>
        <tr><th>Check-out Date</th><td><?= $row['check_out_date'] ?></td></tr>
        <tr><th>Package</th><td><?= $row['package_name'] ?? 'None' ?></td></tr>
        <tr><th>Room Total Price (RM)</th><td><?= number_format($room_price, 2) ?></td></tr>
        <tr><th>Package Total Price (RM)</th><td><?= number_format($total_package_price, 2) ?></td></tr>
        <tr><th>Discount Amount (RM)</th><td><?= number_format($discount_amount, 2) ?></td></tr>
        <tr><th>Total Price (RM)</th><td><?= number_format($row['total_amount'], 2) ?></td></tr>
        <tr><th>Payment Status</th><td><?= $row['payment_status'] ?></td></tr>
        <tr><th>Booking Date</th><td><?= $row['booking_date'] ?></td></tr>
    </table>

    <div class="no-print text-center mt-4">
        <button onclick="window.print();" class="btn btn-success">🖨️ Print This Page</button>
        <a href="view_booking.php" class="btn btn-secondary">Back</a>
    </div>
</div>

<script>
window.onload = function() {
    window.print();
    window.onafterprint = function () {
        window.close();
    };
};
</script>

</body>
</html>
