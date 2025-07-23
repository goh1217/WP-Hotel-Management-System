<?php
// Q1: Establish connection to database
require_once("config.php");

// Q2: Combine all CREATE TABLE and INSERT INTO statements
$sql = "CREATE TABLE IF NOT EXISTS Administrator (
    admin_id INT NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(100) NOT NULL,
    PRIMARY KEY (admin_id)
);

CREATE TABLE IF NOT EXISTS Customer (
    customer_id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(100) NOT NULL,
    status TINYINT(1) DEFAULT 0,
    PRIMARY KEY (customer_id)
);

CREATE TABLE IF NOT EXISTS RoomCategory (
    category_id INT NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    description TEXT,
    PRIMARY KEY (category_id)
);

CREATE TABLE IF NOT EXISTS Tariff (
    tariff_id INT NOT NULL AUTO_INCREMENT,
    price_per_night DECIMAL(10, 2) NOT NULL,
    discount DECIMAL(5, 2),
    description TEXT,
    PRIMARY KEY (tariff_id)
);

CREATE TABLE IF NOT EXISTS Room (
    room_id INT NOT NULL AUTO_INCREMENT,
    room_number VARCHAR(10) NOT NULL UNIQUE,
    category_id INT NOT NULL,
    tariff_id INT NOT NULL,
    status ENUM('Available', 'Booked') DEFAULT 'Available',
    PRIMARY KEY (room_id),
    FOREIGN KEY (category_id) REFERENCES RoomCategory(category_id),
    FOREIGN KEY (tariff_id) REFERENCES Tariff(tariff_id)
);

CREATE TABLE IF NOT EXISTS Package (
    package_id INT NOT NULL AUTO_INCREMENT,
    package_name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (package_id)
);

CREATE TABLE IF NOT EXISTS Service (
    service_id INT NOT NULL AUTO_INCREMENT,
    service_name VARCHAR(100) NOT NULL,
    description TEXT,
    PRIMARY KEY (service_id)
);

CREATE TABLE IF NOT EXISTS Booking (
    booking_id INT NOT NULL AUTO_INCREMENT,
    customer_id INT NOT NULL,
    room_id INT NOT NULL,
    package_id INT NOT NULL,
    booking_date DATE NOT NULL,
    check_in_date DATE NOT NULL,
    check_out_date DATE NOT NULL,
    payment_status ENUM('Paid', 'Unpaid') DEFAULT 'Unpaid',
    PRIMARY KEY (booking_id),
    FOREIGN KEY (customer_id) REFERENCES Customer(customer_id),
    FOREIGN KEY (room_id) REFERENCES Room(room_id),
    FOREIGN KEY (package_id) REFERENCES Package(package_id)
);

CREATE TABLE IF NOT EXISTS Payment (
    payment_id INT NOT NULL AUTO_INCREMENT,
    booking_id INT NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    method VARCHAR(50) NOT NULL,
    PRIMARY KEY (payment_id),
    FOREIGN KEY (booking_id) REFERENCES Booking(booking_id)
);

CREATE TABLE IF NOT EXISTS Feedback (
    feedback_id INT NOT NULL AUTO_INCREMENT,
    customer_id INT NOT NULL,
    feedback_text TEXT NOT NULL,
    submitted_date DATE NOT NULL,
    PRIMARY KEY (feedback_id),
    FOREIGN KEY (customer_id) REFERENCES Customer(customer_id)
);

INSERT INTO Administrator (username, password)
VALUES ('admin1', 'admin123');

INSERT INTO Customer (name, email, phone, password)
VALUES 
('Alice Tan', 'alice@example.com', '0123456789', 'alicepass'),
('John Lim', 'john@example.com', '0198765432', 'johnpass');

INSERT INTO RoomCategory (category_name, description)
VALUES 
('Deluxe', 'Spacious room with king-size bed and sea view'),
('Standard', 'Affordable room with essential amenities');

INSERT INTO Tariff (price_per_night, discount, description)
VALUES 
(300.00, 10.00, '10% discount for early booking'),
(200.00, 0.00, 'Standard rate');

INSERT INTO Room (room_number, category_id, tariff_id, status)
VALUES 
('D101', 1, 1, 'Available'),
('S201', 2, 2, 'Booked');

INSERT INTO Package (package_name, description, price)
VALUES 
('Honeymoon Package', 'Includes candlelight dinner and spa', 1500.00),
('Family Package', 'Includes 2 nights stay with free breakfast', 1000.00);

INSERT INTO Service (service_name, description)
VALUES 
('Room Service', '24/7 room delivery service'),
('Airport Pickup', 'Complimentary airport pickup');

INSERT INTO Booking (customer_id, room_id, package_id, booking_date, check_in_date, check_out_date, payment_status)
VALUES 
(1, 1, 1, '2025-06-12', '2025-06-15', '2025-06-17', 'Paid'),
(2, 2, 2, '2025-06-11', '2025-06-13', '2025-06-14', 'Unpaid');

INSERT INTO Payment (booking_id, payment_date, amount, method)
VALUES 
(1, '2025-06-12', 540.00, 'Credit Card');

INSERT INTO Feedback (customer_id, feedback_text, submitted_date)
VALUES 
(1, 'Amazing experience! Highly recommended.', '2025-06-18'),
(2, 'Good service, but check-in was delayed.', '2025-06-14');

";

// Q3: Run the entire SQL batch
if (mysqli_multi_query($conn, $sql)) {
    echo "Tables and data created successfully.";
} else {
    echo "Error: " . mysqli_error($conn);
}

mysqli_close($conn);
?>
