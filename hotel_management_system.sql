-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 01, 2025 at 06:08 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hotel_management_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `administrator`
--

CREATE TABLE `administrator` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `administrator`
--

INSERT INTO `administrator` (`admin_id`, `username`, `password`) VALUES
(1, 'admin1', '$2y$10$wniMsIZtaGFRYlcU2TMa1O1twPSbwl8S9jN/Qga44sLNhDd.81ApK'),
(2, 'admin2', 'admin456');

-- --------------------------------------------------------

--
-- Table structure for table `booking`
--

CREATE TABLE `booking` (
  `booking_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `package_id` int(11) DEFAULT NULL,
  `booking_date` date NOT NULL,
  `check_in_date` date NOT NULL,
  `check_out_date` date NOT NULL,
  `payment_status` enum('Paid','Unpaid') DEFAULT 'Unpaid',
  `booking_status` enum('confirmed','cancelled','pending','requesting','verifying') DEFAULT 'pending',
  `total_amount` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking`
--

INSERT INTO `booking` (`booking_id`, `customer_id`, `room_id`, `package_id`, `booking_date`, `check_in_date`, `check_out_date`, `payment_status`, `booking_status`, `total_amount`, `created_at`) VALUES
(10, 5, 4, 2, '2025-06-18', '2025-06-18', '2025-06-20', 'Paid', 'requesting', 446.50, '2025-06-18 07:26:13'),
(21, 5, 9, 20, '2025-07-01', '2025-07-01', '2025-07-03', 'Paid', 'confirmed', 544.00, '2025-07-01 03:21:07'),
(22, 5, 26, 13, '2025-07-01', '2025-07-02', '2025-07-05', 'Paid', 'confirmed', 940.50, '2025-07-01 03:23:48'),
(23, 5, 10, NULL, '2025-07-01', '2025-07-02', '2025-07-03', 'Paid', 'confirmed', 187.00, '2025-07-01 03:51:10'),
(24, 5, 1, NULL, '2025-07-01', '2025-07-02', '2025-07-05', 'Paid', 'confirmed', 840.00, '2025-07-01 03:52:07'),
(25, 5, 3, 3, '2025-07-01', '2025-07-03', '2025-07-05', 'Paid', 'confirmed', 608.00, '2025-07-01 04:03:19');

-- --------------------------------------------------------

--
-- Table structure for table `cancellation_reason`
--

CREATE TABLE `cancellation_reason` (
  `reason_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `reason_text` text NOT NULL,
  `account_number` varchar(30) DEFAULT NULL,
  `cancelled_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cancellation_reason`
--

INSERT INTO `cancellation_reason` (`reason_id`, `booking_id`, `reason_text`, `account_number`, `cancelled_at`) VALUES
(5, 10, 'got work', '12341234', '2025-06-18 07:34:14');

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `customer_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(100) NOT NULL,
  `status` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `remember_token` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`customer_id`, `name`, `email`, `phone`, `password`, `status`, `created_at`, `remember_token`) VALUES
(1, 'Alice Tan', 'alice@example.com', '0123456789', 'alicepass', 1, '2025-06-17 07:40:05', NULL),
(2, 'John Lim', 'john@example.com', '0198765432', 'johnpass', 1, '2025-06-17 07:40:05', NULL),
(3, 'Sarah Wong', 'sarah@example.com', '0187654321', 'sarahpass', 1, '2025-06-17 07:40:05', NULL),
(4, 'David Lee', 'david@example.com', '0176543210', 'davidpass', 1, '2025-06-17 07:40:05', NULL),
(5, 'goh chang zhe', 'changzhegoh@gmail.com', '0183672088', '$2y$10$wRfcHFF2V166DxONlGmfsurymyNboWAEGndy5aBdPEEp6njDK01vm', 1, '2025-06-17 08:00:05', '83ccb2d2444fc8cae6ef70ca21a79400');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `feedback_text` text NOT NULL,
  `rating` int(1) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `submitted_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `hotel_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedback_id`, `booking_id`, `customer_id`, `feedback_text`, `rating`, `submitted_date`, `created_at`, `updated_at`, `hotel_id`) VALUES
(8, 22, 5, 'not bad', 4, '2025-07-01', '2025-07-01 03:25:12', '2025-07-01 03:25:12', 8),
(9, 21, 5, 'good', 5, '2025-07-01', '2025-07-01 03:26:09', '2025-07-01 03:26:09', 3),
(11, 24, 5, 'its all right', 4, '2025-07-01', '2025-07-01 03:54:26', '2025-07-01 03:54:26', 1),
(12, 25, 5, 'good', 5, '2025-07-01', '2025-07-01 04:03:58', '2025-07-01 04:03:58', 1);

-- --------------------------------------------------------

--
-- Table structure for table `hotel`
--

CREATE TABLE `hotel` (
  `hotel_id` int(11) NOT NULL,
  `hotel_name` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `average_rating` decimal(3,2) DEFAULT 0.00,
  `hotel_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hotel`
--

INSERT INTO `hotel` (`hotel_id`, `hotel_name`, `address`, `phone_number`, `email`, `description`, `average_rating`, `hotel_image`) VALUES
(1, 'Sunrise Grand Hotel', '123 Jalan Bukit, Kuala Lumpur', '+60123456789', 'info@sunrisegrand.com', 'Luxury hotel in the heart of KL', 4.50, 'sunrisegrand_hotel.jpg'),
(2, 'Oceanview Resort', '456 Pantai Street, Langkawi', '+60198765432', 'booking@oceanview.com', 'Beautiful beachfront resort', 4.00, 'oceanview_resort.jpg'),
(3, 'Heritage Inn', '789 Jalan Merdeka, Malacca', '+60187654321', 'heritage@inn.com', 'Historic boutique hotel', 5.00, 'heritage_inn.webp'),
(4, 'Hilltop Lodge', '12 Genting Highlands, Pahang', '+60176543210', 'info@hilltop.com', 'Mountain retreat with scenic views', 4.60, 'hilltop_hotel.webp'),
(5, 'Urban Stay Suites', '88 Jalan Ampang, Kuala Lumpur', '+60111222333', 'contact@urbanstay.com', 'Modern hotel for business travelers', 4.25, 'urbanstay.jpg'),
(6, 'Tropical Paradise Resort', '34 Beachside Lane, Penang', '+60199887766', 'hello@tropicalparadise.com', 'Relaxing resort near the beach', 4.45, 'tropical_paradise.jpg'),
(7, 'Lakeside Villa', '7 Tasik Heights, Putrajaya', '+60187766554', 'info@lakesidevilla.com', 'Lakeside hotel with garden views', 4.10, 'lakeside_villa.jpg'),
(8, 'CityLink Hotel', '2 Jalan Tunku Abdul Rahman, Johor Bahru', '+60182345678', 'book@citylinkhotel.com', 'Affordable comfort in the city center', 4.00, 'citylink_hotel.jpg'),
(9, 'Mountain Breeze Chalet', 'Lot 16, Cameron Highlands, Pahang', '+60187778899', 'stay@mountainbreeze.com', 'Chalet surrounded by fresh mountain air', 4.50, 'mountain_breeze.jpg'),
(10, 'Riverfront Inn', '15 Sungai Street, Kuching, Sarawak', '+6082223344', 'booking@riverfrontinn.com', 'Charming inn along the riverfront', 4.30, 'riverfront_inn.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `package`
--

CREATE TABLE `package` (
  `package_id` int(11) NOT NULL,
  `package_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL CHECK (`price` > 0),
  `hotel_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `package`
--

INSERT INTO `package` (`package_id`, `package_name`, `description`, `price`, `hotel_id`) VALUES
(1, 'Room Service', '24/7 room delivery service', 25.00, 1),
(2, 'Airport Pickup', 'Complimentary airport pickup', 50.00, 1),
(3, 'Spa Treatment', 'Relaxing spa and wellness services', 120.00, 1),
(4, 'Tour Guide', 'Local area guided tours', 80.00, 1),
(5, 'Laundry Service', 'Professional cleaning service', 15.00, 2),
(6, 'Car Rental', 'Vehicle rental for guests', 75.00, 2),
(7, 'Work and Stay', '2-hour meeting room use + breakfast', 180.00, 5),
(8, 'Executive Boost', 'laundry service + late checkout', 160.00, 5),
(9, 'Beach Escape', 'Beach access, breakfast, yoga session, pool access', 260.00, 6),
(10, 'Honeymoon Bliss', 'romantic dinner, flower decoration', 290.00, 6),
(11, 'Romantic Retreat', 'Breakfast, sunset boat ride', 180.00, 7),
(12, 'Zen Weekend', 'Garden meditation, herbal tea set', 130.00, 7),
(13, 'City Explorer', 'City attraction pass, local breakfast', 150.00, 8),
(14, 'Commuter Saver', 'Free parking, early check-in', 80.00, 8),
(15, 'Highland Adventure', 'Guided jungle trail, BBQ dinner', 200.00, 9),
(16, 'Couple’s Cozy', 'Hot cocoa, massage coupon', 200.00, 9),
(17, 'River Romance', 'Sunset cruise, dinner for two', 200.00, 10),
(18, 'Local Discovery', 'Sarawak cultural village ticket', 100.00, 10),
(19, 'Cultural Comfort', 'Traditional local breakfast + heritage walking map', 50.00, 3),
(20, 'Business Heritage', 'Workspace desk + early check-in + express laundry', 100.00, 3),
(21, 'Hilltop Escape', 'Balcony view + complimentary breakfast', 150.00, 4),
(22, 'Nature and Fire', 'Hot chocolate set + s’mores kit', 100.00, 4);

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

CREATE TABLE `payment` (
  `payment_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment`
--

INSERT INTO `payment` (`payment_id`, `booking_id`, `payment_date`, `amount`, `method`) VALUES
(8, 10, '2025-06-18', 446.50, 'TNG QR'),
(19, 21, '2025-07-01', 544.00, 'TNG QR'),
(20, 22, '2025-07-01', 940.50, 'TNG QR'),
(21, 23, '2025-07-01', 187.00, 'TNG QR'),
(22, 24, '2025-07-01', 840.00, 'TNG QR'),
(23, 25, '2025-07-01', 608.00, 'TNG QR');

-- --------------------------------------------------------

--
-- Table structure for table `payment_proof`
--

CREATE TABLE `payment_proof` (
  `proof_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `image_name` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_proof`
--

INSERT INTO `payment_proof` (`proof_id`, `booking_id`, `image_name`, `uploaded_at`) VALUES
(6, 10, 'proof_68526a15526620.78613447.png', '2025-06-18 07:26:13'),
(17, 21, 'proof_686354230a16f7.03676945.png', '2025-07-01 03:21:07'),
(18, 22, 'proof_686354c4b00cd6.34876512.png', '2025-07-01 03:23:48'),
(19, 23, 'proof_68635b2e98a564.45955409.png', '2025-07-01 03:51:10'),
(20, 24, 'proof_68635b67750fd2.41266775.png', '2025-07-01 03:52:07'),
(21, 25, 'proof_68635e074ce811.38753039.png', '2025-07-01 04:03:19');

-- --------------------------------------------------------

--
-- Table structure for table `room`
--

CREATE TABLE `room` (
  `room_id` int(11) NOT NULL,
  `room_number` varchar(10) NOT NULL,
  `category_id` int(11) NOT NULL,
  `hotel_id` int(11) NOT NULL,
  `status` enum('available','unavailable') DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room`
--

INSERT INTO `room` (`room_id`, `room_number`, `category_id`, `hotel_id`, `status`) VALUES
(1, 'D101', 1, 1, 'available'),
(2, 'D102', 1, 1, 'available'),
(3, 'S201', 2, 1, 'available'),
(4, 'S202', 2, 1, 'available'),
(5, 'SV301', 3, 2, 'available'),
(6, 'SV302', 3, 2, 'available'),
(7, 'F401', 4, 2, 'available'),
(8, 'F402', 4, 2, 'available'),
(9, 'HD501', 5, 3, 'available'),
(10, 'HD502', 5, 3, 'available'),
(11, 'P601', 6, 4, 'available'),
(12, 'P602', 6, 4, 'available'),
(13, 'S001', 7, 5, 'available'),
(14, 'S002', 7, 5, 'available'),
(15, 'EX001', 8, 5, 'available'),
(16, 'EX002', 8, 5, 'available'),
(17, 'OV001', 9, 6, 'available'),
(18, 'OV002', 9, 6, 'available'),
(19, 'LR001', 10, 7, 'available'),
(20, 'LR002', 10, 7, 'available'),
(21, 'GS001', 11, 7, 'available'),
(22, 'GS002', 11, 7, 'available'),
(23, 'BR001', 12, 8, 'available'),
(24, 'BR002', 12, 8, 'available'),
(25, 'BR003', 12, 8, 'available'),
(26, 'SR001', 13, 8, 'available'),
(27, 'SR002', 13, 8, 'available'),
(28, 'SC001', 14, 9, 'available'),
(29, 'SC002', 14, 9, 'available'),
(30, 'RV001', 15, 10, 'available'),
(31, 'RV002', 15, 10, 'available'),
(32, 'BR001', 16, 10, 'available'),
(33, 'BR002', 16, 10, 'available'),
(34, 'DF001', 17, 9, 'available'),
(35, 'DF002', 17, 9, 'available'),
(36, 'GV001', 18, 6, 'available'),
(37, 'GV002', 18, 6, 'available'),
(38, 'CS001', 19, 3, 'available'),
(39, 'CS002', 19, 3, 'available'),
(40, 'CS003', 19, 3, 'available');

-- --------------------------------------------------------

--
-- Table structure for table `room_category`
--

CREATE TABLE `room_category` (
  `category_id` int(11) NOT NULL,
  `hotel_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL CHECK (`price` > 0),
  `discount` decimal(5,2) DEFAULT 0.00 CHECK (`discount` >= 0 and `discount` <= 50),
  `room_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_category`
--

INSERT INTO `room_category` (`category_id`, `hotel_id`, `category_name`, `description`, `price`, `discount`, `room_image`) VALUES
(1, 1, 'Deluxe Room', 'Spacious room with city view, king-sized bed and modern amenities.', 280.00, 0.00, 'sunrisegrand_deluxeroom.webp'),
(2, 1, 'Standard Room', 'Comfortable room with queen bed and private bathroom.', 200.00, 5.00, 'sunrisegrand_standardroom.jpg'),
(3, 2, 'Sea View Suite', 'Premium suite with ocean view and balcony.', 350.00, 0.00, 'oceanview_seaviewsuite.jpg'),
(4, 2, 'Family Room', 'Ideal for families with 2 double beds and extra space.', 300.00, 10.00, 'oceanvew_familyroom.jpg'),
(5, 3, 'Heritage Deluxe', 'Vintage-themed room with modern comfort.', 220.00, 15.00, 'heritage_heritagedeluxe.jpg'),
(6, 4, 'Panorama Room', 'Overlooks the hills, comes with private terrace.', 320.00, 0.00, 'hilltop_panoramaroom.jpg'),
(7, 5, 'Standard', 'Comfortable room with essential amenities', 180.00, 5.00, 'urbanstaysuites_standard.jpg'),
(8, 5, 'Executive', 'Larger room with work desk &amp; fast Wi-Fi', 250.00, 10.00, 'urbanstaysuites_executive.jpg'),
(9, 6, 'Ocean View Deluxe', 'Full ocean view, private balcony', 300.00, 10.00, 'tropicalparadiseresort_oceanviewdeluxe.jpg'),
(10, 7, 'Lakeside Room', 'Direct lake view, minimalist decor', 270.00, 5.00, 'lakesidevilla_lakesideroom.jpg'),
(11, 7, 'Garden Suite', 'Surrounded by gardens, includes bathtub', 270.00, 0.00, 'lakesidevilla_gardensuite.jpg'),
(12, 8, 'Budget Room', 'Compact and clean with city view', 130.00, 5.00, 'citylinkhotel_budgetroom.webp'),
(13, 8, 'Superior Room', 'Queen bed, coffee corner', 180.00, 5.00, 'citylinkhotel_superiorroom.jpg'),
(14, 9, 'Standard Chalet', 'Wooden interior, mountain view', 200.00, 5.00, 'mountainbreezechalet_standardchalet.jpg'),
(15, 10, 'River View', 'Large window with riverfront scenery', 200.00, 5.00, 'riverfrontinn_riverview.webp'),
(16, 10, 'Balcony Room', 'Private balcony overlooking the river', 250.00, 5.00, 'riverfrontinn_balconyroom.jpg'),
(17, 9, 'Deluxe Fireplace Chalet', 'Includes fireplace, cozy setting', 280.00, 10.00, 'mountainbreezechalet_deluxefireplacechalet.jpg'),
(18, 6, 'Garden View Room', 'Overlooks tropical gardens, queen bed', 220.00, 5.00, 'tropicalparadiseresort_gardenviewroom.webp'),
(19, 3, 'Classic Single', 'Colonial-style single bed, wooden furniture, vintage decor', 130.00, 5.00, 'heritageinn_classicsingle.jpg');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_room_availability`
-- (See below for the actual view)
--
CREATE TABLE `vw_room_availability` (
`room_id` int(11)
,`room_number` varchar(10)
,`hotel_id` int(11)
,`category_id` int(11)
,`check_date` date
,`status` varchar(9)
,`booking_id` int(11)
,`customer_id` int(11)
);

-- --------------------------------------------------------

--
-- Structure for view `vw_room_availability`
--
DROP TABLE IF EXISTS `vw_room_availability`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_room_availability`  AS SELECT `r`.`room_id` AS `room_id`, `r`.`room_number` AS `room_number`, `r`.`hotel_id` AS `hotel_id`, `r`.`category_id` AS `category_id`, `d`.`check_date` AS `check_date`, CASE WHEN `b`.`booking_id` is not null THEN 'booked' ELSE 'available' END AS `status`, `b`.`booking_id` AS `booking_id`, `b`.`customer_id` AS `customer_id` FROM ((`room` `r` join (select curdate() AS `check_date`) `d`) left join `booking` `b` on(`r`.`room_id` = `b`.`room_id` and `d`.`check_date` between `b`.`check_in_date` and `b`.`check_out_date` - interval 1 day and `b`.`booking_status` in ('confirmed','pending'))) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `administrator`
--
ALTER TABLE `administrator`
  ADD PRIMARY KEY (`admin_id`);

--
-- Indexes for table `booking`
--
ALTER TABLE `booking`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `package_id` (`package_id`),
  ADD KEY `idx_booking_status` (`booking_status`),
  ADD KEY `idx_booking_dates` (`check_in_date`,`check_out_date`);

--
-- Indexes for table `cancellation_reason`
--
ALTER TABLE `cancellation_reason`
  ADD PRIMARY KEY (`reason_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `hotel_id` (`hotel_id`);

--
-- Indexes for table `hotel`
--
ALTER TABLE `hotel`
  ADD PRIMARY KEY (`hotel_id`);

--
-- Indexes for table `package`
--
ALTER TABLE `package`
  ADD PRIMARY KEY (`package_id`),
  ADD UNIQUE KEY `uk_package_hotel` (`package_name`,`hotel_id`),
  ADD KEY `package_ibfk_1` (`hotel_id`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `payment_proof`
--
ALTER TABLE `payment_proof`
  ADD PRIMARY KEY (`proof_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `room`
--
ALTER TABLE `room`
  ADD PRIMARY KEY (`room_id`),
  ADD UNIQUE KEY `uk_room_hotel` (`room_number`,`hotel_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `hotel_id` (`hotel_id`);

--
-- Indexes for table `room_category`
--
ALTER TABLE `room_category`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `uk_category_hotel` (`category_name`,`hotel_id`),
  ADD KEY `hotel_id` (`hotel_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `administrator`
--
ALTER TABLE `administrator`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `booking`
--
ALTER TABLE `booking`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `cancellation_reason`
--
ALTER TABLE `cancellation_reason`
  MODIFY `reason_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `hotel`
--
ALTER TABLE `hotel`
  MODIFY `hotel_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `package`
--
ALTER TABLE `package`
  MODIFY `package_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `payment`
--
ALTER TABLE `payment`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `payment_proof`
--
ALTER TABLE `payment_proof`
  MODIFY `proof_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `room`
--
ALTER TABLE `room`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `room_category`
--
ALTER TABLE `room_category`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `booking`
--
ALTER TABLE `booking`
  ADD CONSTRAINT `booking_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`),
  ADD CONSTRAINT `booking_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `room` (`room_id`),
  ADD CONSTRAINT `booking_ibfk_3` FOREIGN KEY (`package_id`) REFERENCES `package` (`package_id`);

--
-- Constraints for table `cancellation_reason`
--
ALTER TABLE `cancellation_reason`
  ADD CONSTRAINT `cancellation_reason_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `booking` (`booking_id`);

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `booking` (`booking_id`),
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`),
  ADD CONSTRAINT `feedback_ibfk_3` FOREIGN KEY (`hotel_id`) REFERENCES `hotel` (`hotel_id`);

--
-- Constraints for table `package`
--
ALTER TABLE `package`
  ADD CONSTRAINT `package_ibfk_1` FOREIGN KEY (`hotel_id`) REFERENCES `hotel` (`hotel_id`);

--
-- Constraints for table `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `booking` (`booking_id`);

--
-- Constraints for table `payment_proof`
--
ALTER TABLE `payment_proof`
  ADD CONSTRAINT `payment_proof_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `booking` (`booking_id`);

--
-- Constraints for table `room`
--
ALTER TABLE `room`
  ADD CONSTRAINT `room_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `room_category` (`category_id`),
  ADD CONSTRAINT `room_ibfk_2` FOREIGN KEY (`hotel_id`) REFERENCES `hotel` (`hotel_id`);

--
-- Constraints for table `room_category`
--
ALTER TABLE `room_category`
  ADD CONSTRAINT `room_category_ibfk_1` FOREIGN KEY (`hotel_id`) REFERENCES `hotel` (`hotel_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
