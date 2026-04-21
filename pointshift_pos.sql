-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 21, 2026 at 11:06 AM
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
-- Database: `pointshift_pos`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Potato Chips', 'Classic and flavored potato crisps', '2026-04-19 08:24:27'),
(2, 'Corn Snacks', 'Puffed, roasted, and fried corn snacks (Cornik, etc.)', '2026-04-19 08:24:27'),
(3, 'Nuts & Beans', 'Peanuts, mixed nuts, green peas, and roasted beans', '2026-04-19 08:24:27'),
(4, 'Crackers & Biscuits', 'Prawn crackers, fish crackers, and cropek', '2026-04-19 08:24:27'),
(5, 'Sweet Snacks', 'Chocolates, wafers, candies, and sweet biscuits', '2026-04-19 08:24:27');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_reports`
--

CREATE TABLE `inventory_reports` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `change_type` enum('Added','Removed') NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0 COMMENT 'Legacy column - same as quantity_changed',
  `quantity_changed` int(11) NOT NULL DEFAULT 0,
  `previous_quantity` int(11) DEFAULT NULL,
  `new_quantity` int(11) DEFAULT NULL,
  `remarks` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `parent_message_id` int(11) DEFAULT NULL COMMENT 'For threaded conversations',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT 'cash',
  `amount_received` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `total_amount`, `subtotal`, `discount_percent`, `discount_amount`, `tax_amount`, `payment_method`, `amount_received`, `status`, `created_at`) VALUES
(5, 'ORD-20260419-915', 2, 336.00, 300.00, 0.00, 0.00, 36.00, 'cash', 500.00, 'completed', '2026-04-19 08:27:07'),
(6, 'ORD-20260419-378', 2, 336.00, 300.00, 0.00, 0.00, 36.00, 'cash', 350.00, 'completed', '2026-04-19 08:45:13'),
(7, 'ORD-20260419-211', 2, 136.64, 122.00, 0.00, 0.00, 14.64, 'cash', 200.00, 'completed', '2026-04-19 09:54:26'),
(8, 'ORD-20260419-545', 2, 267.68, 239.00, 0.00, 0.00, 28.68, 'cash', 300.00, 'completed', '2026-04-19 09:56:04'),
(9, 'ORD-20260421-360', 2, 136.64, 122.00, 0.00, 0.00, 14.64, 'cash', 200.00, 'completed', '2026-04-21 07:03:11'),
(10, 'ORD-20260421-037', 2, 358.40, 320.00, 0.00, 0.00, 38.40, 'cash', 500.00, 'completed', '2026-04-21 07:36:00'),
(11, 'ORD-20260421-157', 2, 38.08, 34.00, 0.00, 0.00, 4.08, 'cash', 50.00, 'completed', '2026-04-21 07:38:31'),
(12, 'ORD-20260421-578', 2, 156.80, 140.00, 0.00, 0.00, 16.80, 'gcash', 200.00, 'completed', '2026-04-21 08:14:09'),
(13, 'ORD-20260421-946', 2, 181.44, 162.00, 0.00, 0.00, 19.44, 'gcash', 200.00, 'completed', '2026-04-21 08:14:51'),
(14, 'ORD-20260421-863', 2, 156.80, 140.00, 0.00, 0.00, 16.80, 'gcash', 156.80, 'completed', '2026-04-21 08:41:58');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `total_price`) VALUES
(1, 5, 5, 3, 100.00, 300.00),
(2, 6, 5, 3, 100.00, 300.00),
(3, 7, 12, 1, 22.00, 22.00),
(4, 7, 5, 1, 100.00, 100.00),
(5, 8, 12, 1, 22.00, 22.00),
(6, 8, 16, 1, 140.00, 140.00),
(7, 8, 9, 1, 20.00, 20.00),
(8, 8, 6, 1, 32.00, 32.00),
(9, 8, 4, 1, 25.00, 25.00),
(10, 9, 12, 1, 22.00, 22.00),
(11, 9, 5, 1, 100.00, 100.00),
(12, 10, 9, 2, 20.00, 40.00),
(13, 10, 16, 2, 140.00, 280.00),
(14, 11, 7, 1, 34.00, 34.00),
(15, 12, 16, 1, 140.00, 140.00),
(16, 13, 12, 1, 22.00, 22.00),
(17, 13, 16, 1, 140.00, 140.00),
(18, 14, 16, 1, 140.00, 140.00);

-- --------------------------------------------------------

--
-- Table structure for table `payment_qrcodes`
--

CREATE TABLE `payment_qrcodes` (
  `id` int(11) NOT NULL,
  `payment_method` varchar(50) NOT NULL DEFAULT 'gcash',
  `qr_code_path` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_qrcodes`
--

INSERT INTO `payment_qrcodes` (`id`, `payment_method`, `qr_code_path`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'gcash', '', 'GCash Payment QR Code', 0, '2026-04-21 07:19:45', '2026-04-21 08:08:52'),
(2, 'gcash', '', 'GCash Payment QR Code', 0, '2026-04-21 07:20:44', '2026-04-21 08:08:52'),
(3, 'gcash', 'public/uploads/qrcodes/gcash_qr_1776758937.png', 'GCash Payment QR Code', 1, '2026-04-21 08:08:57', '2026-04-21 08:13:37');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `sku` varchar(64) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `low_stock_threshold` int(11) DEFAULT 10,
  `barcode` varchar(100) DEFAULT NULL,
  `expiry` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `sku`, `category_id`, `price`, `stock_quantity`, `low_stock_threshold`, `barcode`, `expiry`, `description`, `status`, `created_at`, `updated_at`, `last_updated_by`) VALUES
(1, 'Piattos Cheese 85g', 'CHP-PTTS-CHS-85', 1, 35.00, 150, 20, '4800016111111', '2026-12-31', 'Hexagon shaped cheese flavored potato crisps', 'active', '2026-04-19 08:24:27', '2026-04-19 08:24:27', 1),
(2, 'V-Cut Spicy BBQ 60g', 'CHP-VCUT-BBQ-60', 1, 28.00, 100, 15, '4800016222222', '2026-10-15', 'Spicy barbecue flavored potato chips', 'active', '2026-04-19 08:24:27', '2026-04-19 08:24:27', 1),
(3, 'Pic-A 3-in-1 90g', 'CHP-PICA-3IN1-90', 1, 38.00, 80, 15, '4800016333333', '2026-11-20', 'Mix of Piattos, Nova, and Tortillos', 'active', '2026-04-19 08:24:27', '2026-04-19 08:24:27', 1),
(4, 'Boy Bawang Garlic 100g', 'CRN-BYBWG-GRL-100', 2, 25.00, 199, 30, '4800016444444', '2027-01-20', 'Fried cornick with garlic flavor', 'active', '2026-04-19 08:24:27', '2026-04-19 09:56:04', 1),
(5, 'Boy Bawang Garlic (Tie/Wholesale - 20x20g)', 'CRN-BYBWG-TIE-20', 2, 100.00, 42, 10, '4800016555555', '2027-01-20', 'Wholesale tie of small Boy Bawang packs', 'active', '2026-04-19 08:24:27', '2026-04-21 07:03:11', 1),
(6, 'Chippy BBQ 110g', 'CRN-CHPY-BBQ-110', 2, 32.00, 119, 20, '4800016666666', '2026-11-30', 'Classic BBQ flavored corn chips', 'active', '2026-04-19 08:24:27', '2026-04-19 09:56:04', 1),
(7, 'Nova Multigrain Country Cheddar 78g', 'CRN-NOVA-CDB-78', 2, 34.00, 79, 15, '4800016777777', '2026-09-10', 'Multigrain snacks', 'active', '2026-04-19 08:24:27', '2026-04-21 07:38:31', 1),
(8, 'Nagaraya Cracker Nuts Original 160g', 'NUT-NGRY-ORG-160', 3, 40.00, 50, 10, '4800016888888', '2027-03-15', 'Original butter flavor cracker nuts', 'active', '2026-04-19 08:24:27', '2026-04-19 08:24:27', 1),
(9, 'Growers Peanuts Garlic 80g', 'NUT-GRWS-GRL-80', 3, 20.00, 97, 20, '4800016999999', '2026-12-01', 'Roasted peanuts with fried garlic chips', 'active', '2026-04-19 08:24:27', '2026-04-21 07:36:00', 1),
(10, 'Ding Dong Mixed Nuts 100g', 'NUT-DDNG-MIX-100', 3, 28.00, 150, 25, '4800016000111', '2026-11-05', 'Snack mix with peanuts, corn, and green peas', 'active', '2026-04-19 08:24:27', '2026-04-19 08:24:27', 1),
(11, 'Oishi Prawn Crackers Spicy 60g', 'CRK-OISH-SPC-60', 4, 18.00, 300, 50, '4800016000222', '2026-08-20', 'Spicy prawn flavored crackers', 'active', '2026-04-19 08:24:27', '2026-04-19 08:24:27', 1),
(12, 'Fishda 80g', 'CRK-FSHD-80', 4, 22.00, 146, 25, '4800016000333', '2026-10-05', 'Fish shaped fish crackers', 'active', '2026-04-19 08:24:27', '2026-04-21 08:14:51', 1),
(13, 'Martys Cracklin Vegetarian Chicharon 90g', 'CRK-MRTY-VGC-90', 4, 26.00, 180, 30, '4800016000444', '2026-09-25', 'Salt and vinegar vegetarian chicharon', 'active', '2026-04-19 08:24:27', '2026-04-19 08:24:27', 1),
(14, 'Stick-O Chocolate Wafer Stick 380g (Jar)', 'SWT-STKO-CHO-380', 5, 120.00, 40, 5, '4800016000555', '2027-05-10', 'Chocolate filled wafer sticks in a jar (wholesale size)', 'active', '2026-04-19 08:24:27', '2026-04-19 08:24:27', 1),
(15, 'Pillows Chocolate 150g', 'SWT-PLLW-CHO-150', 5, 45.00, 80, 15, '4800016000666', '2026-11-15', 'Chocolate filled cracker pillows', 'active', '2026-04-19 08:24:27', '2026-04-19 08:24:27', 1),
(16, 'Flat Tops Milk Chocolate (Pack of 100)', 'SWT-FLTP-100', 5, 140.00, 24, 5, '4800016000777', '2027-08-01', 'Classic Ricoa flat tops wholesale pack', 'active', '2026-04-19 08:24:27', '2026-04-21 08:41:58', 1);

-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `id` int(11) NOT NULL,
  `shift_name` varchar(100) NOT NULL,
  `shift_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `max_employees` int(11) DEFAULT 10,
  `status` enum('scheduled','in-progress','completed','cancelled') DEFAULT 'scheduled',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shift_assignments`
--

CREATE TABLE `shift_assignments` (
  `id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('supervisor','regular') DEFAULT 'regular',
  `status` enum('assigned','confirmed','declined','completed','no-show') DEFAULT 'assigned',
  `notes` text DEFAULT NULL,
  `assigned_by` int(11) NOT NULL,
  `assigned_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_settings`
--

CREATE TABLE `store_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` varchar(50) DEFAULT 'text' COMMENT 'text, number, boolean, image',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `store_settings`
--

INSERT INTO `store_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `created_at`, `updated_at`) VALUES
(1, 'store_name', 'Pinoy Chichirya Wholesale & Retail', 'text', '2026-04-19 07:40:09', '2026-04-19 07:40:09'),
(2, 'currency', 'PHP', 'text', '2026-04-19 07:40:09', '2026-04-19 07:40:09'),
(3, 'tax_rate', '12', 'number', '2026-04-19 07:40:09', '2026-04-19 07:40:09'),
(4, 'receipt_header', 'Pinoy Chichirya Wholesale & Retail\nManila, Philippines', 'text', '2026-04-19 07:40:09', '2026-04-19 07:40:09'),
(5, 'receipt_footer', 'Maraming Salamat Po! Balik kayo.', 'text', '2026-04-19 07:40:09', '2026-04-19 07:40:09');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff','cashier') NOT NULL DEFAULT 'staff',
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `first_name`, `last_name`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin_john', 'admin@pointshift.com', '$2y$10$rsModrThMFb0L0qncLZnBOR7DYPySmOvO590EA/AvBPqz4Pl63QG2', 'admin', 'John', 'Doe', 'active', '2026-04-19 08:24:27', '2026-04-21 00:55:53'),
(2, 'cashier_jane', 'jane.s@pointshift.com', '$2y$10$Vly..uwx/6NXJyG08HbbyOl3U6N09ZUs0xmTxtyyqkjNaJwH5N1ve', 'cashier', 'Jane', 'Smith', 'active', '2026-04-19 08:26:49', '2026-04-21 06:36:01'),
(3, 'cashier_mark', 'mark.t@pointshift.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier', 'Mark', 'Taylor', 'active', '2026-04-19 08:26:49', '2026-04-19 08:26:49'),
(4, 'staff_sarah', 'sarah.w@pointshift.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'Sarah', 'Williams', 'active', '2026-04-19 08:26:49', '2026-04-19 08:26:49'),
(5, 'cashier_alex', 'alex.b@pointshift.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier', 'Alex', 'Brown', 'inactive', '2026-04-19 08:26:49', '2026-04-19 08:26:49');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_reports`
--
ALTER TABLE `inventory_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `fk_inventory_reports_user` (`user_id`),
  ADD KEY `idx_inventory_reports_date` (`date`),
  ADD KEY `idx_inventory_reports_created` (`created_at`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `recipient_id` (`recipient_id`),
  ADD KEY `parent_message_id` (`parent_message_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `payment_qrcodes`
--
ALTER TABLE `payment_qrcodes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_method` (`payment_method`),
  ADD KEY `is_active` (`is_active`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `fk_products_last_updated_by` (`last_updated_by`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `shift_date` (`shift_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `shift_assignments`
--
ALTER TABLE `shift_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_shift_user` (`shift_id`,`user_id`),
  ADD KEY `shift_id` (`shift_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `store_settings`
--
ALTER TABLE `store_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory_reports`
--
ALTER TABLE `inventory_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `payment_qrcodes`
--
ALTER TABLE `payment_qrcodes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shift_assignments`
--
ALTER TABLE `shift_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_settings`
--
ALTER TABLE `store_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `inventory_reports`
--
ALTER TABLE `inventory_reports`
  ADD CONSTRAINT `fk_inventory_reports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_reports_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`parent_message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_last_updated_by` FOREIGN KEY (`last_updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `shifts`
--
ALTER TABLE `shifts`
  ADD CONSTRAINT `shifts_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `shift_assignments`
--
ALTER TABLE `shift_assignments`
  ADD CONSTRAINT `shift_assignments_ibfk_1` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shift_assignments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shift_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
