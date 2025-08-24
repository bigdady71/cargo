-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 23, 2025 at 08:39 PM
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
-- Database: `salameh_cargo`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'admin',
  `is_active` tinyint(1) DEFAULT 1,
  `failed_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `password_hash`, `role`, `is_active`, `failed_attempts`, `locked_until`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'hsyn', '$2y$10$ouxd/6nqFr6TT6vWz5qUGuFLiLA1JGN21B7jaPXoZh7zKYuhiNLO2', 'superadmin', 1, 0, NULL, '2025-08-17 22:19:37', '2025-08-11 13:31:07', '2025-08-17 22:19:37');

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `log_id` int(11) NOT NULL,
  `action_type` varchar(50) DEFAULT NULL,
  `actor_id` int(11) DEFAULT NULL COMMENT 'positive=user_id, negative=admin_id',
  `related_shipment_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`log_id`, `action_type`, `actor_id`, `related_shipment_id`, `details`, `timestamp`) VALUES
(1, 'user_created', -1, NULL, 'Created user housseinalzekra (ID: 1)', '2025-08-10 20:31:31'),
(2, 'twilio_error', 0, NULL, 'WhatsApp OTP error: Twilio credentials not configured', '2025-08-10 21:30:37'),
(3, 'twilio_error', 0, NULL, 'WhatsApp OTP error: Twilio credentials not configured', '2025-08-10 21:30:58'),
(4, 'twilio_error', 0, NULL, 'WhatsApp OTP error: Twilio credentials not configured', '2025-08-10 21:32:03'),
(5, 'twilio_error', 0, NULL, 'WhatsApp OTP error: Twilio credentials not configured', '2025-08-10 21:32:36'),
(6, 'twilio_error', 0, NULL, 'WhatsApp OTP error: Twilio credentials not configured properly', '2025-08-10 21:35:00'),
(7, 'twilio_error', 0, NULL, 'WhatsApp OTP error: Twilio credentials not configured properly', '2025-08-10 21:35:14'),
(8, 'twilio_error', 0, NULL, 'WhatsApp OTP error: Twilio credentials not configured properly', '2025-08-10 21:35:17'),
(9, 'twilio_error', 0, NULL, 'WhatsApp OTP error: Twilio credentials not configured properly', '2025-08-10 21:37:14'),
(10, 'twilio_error', 0, NULL, 'WhatsApp OTP error: Twilio credentials not configured properly', '2025-08-10 21:49:46'),
(11, 'twilio_error', 0, NULL, 'WhatsApp OTP error: Twilio credentials not configured properly', '2025-08-10 21:53:50'),
(12, 'shipments_import', -1, NULL, 'Imported 0 shipments (0 failed) from BTECH01YW25.xlsx', '2025-08-23 20:41:17'),
(13, 'shipments_import', -1, NULL, 'Imported 0 shipments (0 failed) from BTECH01YW25.xlsx', '2025-08-23 20:41:52');

-- --------------------------------------------------------

--
-- Table structure for table `shipments`
--

CREATE TABLE `shipments` (
  `shipment_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `container_number` varchar(100) DEFAULT NULL COMMENT 'Container number for containerised cargo.',
  `bl_number` varchar(100) DEFAULT NULL COMMENT 'Bill of lading number used by carriers.',
  `shipping_code` varchar(100) DEFAULT NULL COMMENT 'Internal shipping code or alternative identifier.',
  `product_description` text DEFAULT NULL,
  `cbm` decimal(10,2) DEFAULT 0.00,
  `cartons` int(11) DEFAULT 0,
  `weight` decimal(10,2) DEFAULT 0.00,
  `gross_weight` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `status` varchar(50) DEFAULT 'En Route',
  `origin` varchar(100) DEFAULT NULL,
  `destination` varchar(100) DEFAULT NULL,
  `pickup_date` datetime DEFAULT NULL,
  `delivery_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Timestamp of the last shipment status update.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shipment_scrapes`
--

CREATE TABLE `shipment_scrapes` (
  `scrape_id` int(11) NOT NULL,
  `shipment_id` int(11) DEFAULT NULL,
  `source_site` varchar(50) DEFAULT NULL,
  `status` varchar(100) DEFAULT NULL,
  `status_raw` text DEFAULT NULL,
  `scrape_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) NOT NULL,
  `shipping_code` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `id_number` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `phone`, `shipping_code`, `address`, `country`, `id_number`, `created_at`) VALUES
(1, 'housseinalzekra', 'housseinalzekra@gmail.com', '71706478', 'hz', 'baalbeck kayal stret', 'lebanon', '1', '2025-08-10 20:31:31');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_actor_id` (`actor_id`),
  ADD KEY `idx_related_shipment_id` (`related_shipment_id`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indexes for table `shipments`
--
ALTER TABLE `shipments`
  ADD PRIMARY KEY (`shipment_id`),
  ADD UNIQUE KEY `tracking_number` (`tracking_number`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_tracking_number` (`tracking_number`),
  ADD KEY `idx_container_number` (`container_number`),
  ADD KEY `idx_bl_number` (`bl_number`),
  ADD KEY `idx_shipping_code` (`shipping_code`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `shipment_scrapes`
--
ALTER TABLE `shipment_scrapes`
  ADD PRIMARY KEY (`scrape_id`),
  ADD KEY `idx_shipment_id` (`shipment_id`),
  ADD KEY `idx_source_site` (`source_site`),
  ADD KEY `idx_scrape_time` (`scrape_time`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD UNIQUE KEY `shipping_code` (`shipping_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `shipments`
--
ALTER TABLE `shipments`
  MODIFY `shipment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shipment_scrapes`
--
ALTER TABLE `shipment_scrapes`
  MODIFY `scrape_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `shipments`
--
ALTER TABLE `shipments`
  ADD CONSTRAINT `shipments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `shipment_scrapes`
--
ALTER TABLE `shipment_scrapes`
  ADD CONSTRAINT `shipment_scrapes_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`shipment_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
