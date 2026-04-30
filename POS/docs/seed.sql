-- Seed data recreated from pointshift_pos dump for the current kakai_pos schema.
-- Import docs/database.sql first, then this file.

SET FOREIGN_KEY_CHECKS=0;
START TRANSACTION;

USE `kakai_pos`;

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `first_name`, `last_name`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin_john', 'admin@pointshift.com', '$2y$10$rsModrThMFb0L0qncLZnBOR7DYPySmOvO590EA/AvBPqz4Pl63QG2', 'admin', 'John', 'Doe', 'active', '2026-04-19 08:24:27', '2026-04-21 00:55:53'),
(2, 'cashier_jane', 'kim@gmail.com', '$2y$10$Vly..uwx/6NXJyG08HbbyOl3U6N09ZUs0xmTxtyyqkjNaJwH5N1ve', 'cashier', 'Kim', 'Dipasupil', 'active', '2026-04-19 08:26:49', '2026-04-23 07:08:08'),
(3, 'cashier_mark', 'mark.t@pointshift.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier', 'Mark', 'Taylor', 'active', '2026-04-19 08:26:49', '2026-04-19 08:26:49'),
(4, 'staff_sarah', 'sarah.w@pointshift.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'Sarah', 'Williams', 'active', '2026-04-19 08:26:49', '2026-04-19 08:26:49'),
(5, 'cashier_alex', 'alex.b@pointshift.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier', 'Alex', 'Brown', 'inactive', '2026-04-19 08:26:49', '2026-04-19 08:26:49')
ON DUPLICATE KEY UPDATE
  `username`=VALUES(`username`),
  `email`=VALUES(`email`),
  `password`=VALUES(`password`),
  `role`=VALUES(`role`),
  `first_name`=VALUES(`first_name`),
  `last_name`=VALUES(`last_name`),
  `status`=VALUES(`status`),
  `updated_at`=VALUES(`updated_at`);

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Potato Chips', 'Classic and flavored potato crisps', '2026-04-19 08:24:27'),
(2, 'Corn Snacks', 'Puffed, roasted, and fried corn snacks (Cornik, etc.)', '2026-04-19 08:24:27'),
(3, 'Nuts & Beans', 'Peanuts, mixed nuts, green peas, and roasted beans', '2026-04-19 08:24:27'),
(4, 'Crackers & Biscuits', 'Prawn crackers, fish crackers, and cropek', '2026-04-19 08:24:27'),
(5, 'Sweet Snacks', 'Chocolates, wafers, candies, and sweet biscuits', '2026-04-19 08:24:27')
ON DUPLICATE KEY UPDATE
  `name`=VALUES(`name`),
  `description`=VALUES(`description`);

INSERT INTO `products` (`id`, `name`, `sku`, `category_id`, `product_type`, `price`, `cost_price`, `stock_quantity`, `low_stock_threshold`, `barcode`, `expiry`, `description`, `status`, `created_at`, `updated_at`, `last_updated_by`) VALUES
(1, 'Piattos Cheese 85g', 'CHP-PTTS-CHS-85', 1, 'retail', 35.00, 24.50, 150, 20, '4800016111111', '2026-12-31', 'Hexagon shaped cheese flavored potato crisps', 'active', '2026-04-19 08:24:27', '2026-04-19 08:24:27', 1),
(2, 'V-Cut Spicy BBQ 60g', 'CHP-VCUT-BBQ-60', 1, 'retail', 28.00, 19.25, 100, 15, '4800016222222', '2026-10-15', 'Spicy barbecue flavored potato chips', 'active', '2026-04-19 08:24:27', '2026-04-19 08:24:27', 1),
(3, 'Pic-A 3-in-1 90g', 'CHP-PICA-3IN1-90', 1, 'retail', 38.00, 27.00, 80, 15, '4800016333333', '2026-11-20', 'Mix of Piattos, Nova, and Tortillos', 'active', '2026-04-19 08:24:27', '2026-04-19 08:24:27', 1),
(4, 'Boy Bawang Garlic 100g', 'CRN-BYBWG-GRL-100', 2, 'retail', 25.00, 17.00, 199, 30, '4800016444444', '2027-01-20', 'Fried cornick with garlic flavor', 'active', '2026-04-19 08:24:27', '2026-04-19 09:56:04', 1),
(5, 'Boy Bawang Garlic (Tie/Wholesale - 20x20g)', 'CRN-BYBWG-TIE-20', 2, 'wholesale', 100.00, 72.00, 16, 10, '4800016555555', '2027-01-20', 'Wholesale tie of small Boy Bawang packs', 'active', '2026-04-19 08:24:27', '2026-04-25 01:41:24', 1),
(6, 'Chippy BBQ 110g', 'CRN-CHPY-BBQ-110', 2, 'retail', 32.00, 22.50, 119, 20, '4800016666666', '2026-11-30', 'Classic BBQ flavored corn chips', 'active', '2026-04-19 08:24:27', '2026-04-19 09:56:04', 1),
(7, 'Nova Multigrain Country Cheddar 78g', 'CRN-NOVA-CDB-78', 2, 'retail', 34.00, 24.00, 79, 15, '4800016777777', '2026-09-10', 'Multigrain snacks', 'active', '2026-04-19 08:24:27', '2026-04-21 07:38:31', 1),
(8, 'Nagaraya Cracker Nuts Original 160g', 'NUT-NGRY-ORG-160', 3, 'retail', 40.00, 29.00, 50, 10, '4800016888888', '2027-03-15', 'Original butter flavor cracker nuts', 'active', '2026-04-19 08:24:27', '2026-04-19 08:24:27', 1),
(9, 'Growers Peanuts Garlic 80g', 'NUT-GRWS-GRL-80', 3, 'retail', 20.00, 13.50, 97, 20, '4800016999999', '2026-12-01', 'Roasted peanuts with fried garlic chips', 'active', '2026-04-19 08:24:27', '2026-04-21 07:36:00', 1),
(10, 'Ding Dong Mixed Nuts 100g', 'NUT-DDNG-MIX-100', 3, 'retail', 28.00, 19.50, 150, 25, '4800016000111', '2026-11-05', 'Snack mix with peanuts, corn, and green peas', 'active', '2026-04-19 08:24:27', '2026-04-19 08:24:27', 1),
(11, 'Oishi Prawn Crackers Spicy 60g', 'CRK-OISH-SPC-60', 4, 'retail', 18.00, 12.00, 300, 50, '4800016000222', '2026-08-20', 'Spicy prawn flavored crackers', 'active', '2026-04-19 08:24:27', '2026-04-19 08:24:27', 1),
(12, 'Fishda 80g', 'CRK-FSHD-80', 4, 'retail', 22.00, 15.00, 145, 25, '4800016000333', '2026-10-05', 'Fish shaped fish crackers', 'active', '2026-04-19 08:24:27', '2026-04-25 01:45:13', 1),
(13, 'Martys Cracklin Vegetarian Chicharon 90g', 'CRK-MRTY-VGC-90', 4, 'retail', 26.00, 18.50, 180, 30, '4800016000444', '2026-09-25', 'Salt and vinegar vegetarian chicharon', 'active', '2026-04-19 08:24:27', '2026-04-19 08:24:27', 1),
(14, 'Stick-O Chocolate Wafer Stick 380g (Jar)', 'SWT-STKO-CHO-380', 5, 'wholesale', 120.00, 88.00, 40, 5, '4800016000555', '2027-05-10', 'Chocolate filled wafer sticks in a jar (wholesale size)', 'active', '2026-04-19 08:24:27', '2026-04-19 08:24:27', 1),
(15, 'Pillows Chocolate 150g', 'SWT-PLLW-CHO-150', 5, 'retail', 45.00, 31.00, 80, 15, '4800016000666', '2026-11-15', 'Chocolate filled cracker pillows', 'active', '2026-04-19 08:24:27', '2026-04-19 08:24:27', 1),
(16, 'Flat Tops Milk Chocolate (Pack of 100)', 'SWT-FLTP-100', 5, 'wholesale', 140.00, 102.00, 24, 5, '4800016000777', '2027-08-01', 'Classic Ricoa flat tops wholesale pack', 'active', '2026-04-19 08:24:27', '2026-04-21 08:41:58', 1)
ON DUPLICATE KEY UPDATE
  `name`=VALUES(`name`),
  `sku`=VALUES(`sku`),
  `category_id`=VALUES(`category_id`),
  `product_type`=VALUES(`product_type`),
  `price`=VALUES(`price`),
  `cost_price`=VALUES(`cost_price`),
  `stock_quantity`=VALUES(`stock_quantity`),
  `low_stock_threshold`=VALUES(`low_stock_threshold`),
  `barcode`=VALUES(`barcode`),
  `expiry`=VALUES(`expiry`),
  `description`=VALUES(`description`),
  `status`=VALUES(`status`),
  `updated_at`=VALUES(`updated_at`),
  `last_updated_by`=VALUES(`last_updated_by`);

INSERT INTO `product_batches` (`id`, `product_id`, `quantity`, `expiry_date`, `created_at`, `updated_at`) VALUES
(1, 1, 150, '2026-12-31', '2026-04-19 08:24:27', '2026-04-19 08:24:27'),
(2, 2, 100, '2026-10-15', '2026-04-19 08:24:27', '2026-04-19 08:24:27'),
(3, 3, 80, '2026-11-20', '2026-04-19 08:24:27', '2026-04-19 08:24:27'),
(4, 4, 199, '2027-01-20', '2026-04-19 08:24:27', '2026-04-19 09:56:04'),
(5, 5, 16, '2027-01-20', '2026-04-19 08:24:27', '2026-04-25 01:41:24'),
(6, 6, 119, '2026-11-30', '2026-04-19 08:24:27', '2026-04-19 09:56:04'),
(7, 7, 79, '2026-09-10', '2026-04-19 08:24:27', '2026-04-21 07:38:31'),
(8, 8, 50, '2027-03-15', '2026-04-19 08:24:27', '2026-04-19 08:24:27'),
(9, 9, 97, '2026-12-01', '2026-04-19 08:24:27', '2026-04-21 07:36:00'),
(10, 10, 150, '2026-11-05', '2026-04-19 08:24:27', '2026-04-19 08:24:27'),
(11, 11, 300, '2026-08-20', '2026-04-19 08:24:27', '2026-04-19 08:24:27'),
(12, 12, 145, '2026-10-05', '2026-04-19 08:24:27', '2026-04-25 01:45:13'),
(13, 13, 180, '2026-09-25', '2026-04-19 08:24:27', '2026-04-19 08:24:27'),
(14, 14, 40, '2027-05-10', '2026-04-19 08:24:27', '2026-04-19 08:24:27'),
(15, 15, 80, '2026-11-15', '2026-04-19 08:24:27', '2026-04-19 08:24:27'),
(16, 16, 24, '2027-08-01', '2026-04-19 08:24:27', '2026-04-21 08:41:58')
ON DUPLICATE KEY UPDATE
  `product_id`=VALUES(`product_id`),
  `quantity`=VALUES(`quantity`),
  `expiry_date`=VALUES(`expiry_date`),
  `updated_at`=VALUES(`updated_at`);

INSERT INTO `product_breakdown_links` (`id`, `wholesale_product_id`, `retail_product_id`, `retail_units_per_wholesale`, `created_at`, `updated_at`) VALUES
(1, 5, 4, 20, '2026-04-29 00:00:00', '2026-04-29 00:00:00')
ON DUPLICATE KEY UPDATE
  `wholesale_product_id`=VALUES(`wholesale_product_id`),
  `retail_product_id`=VALUES(`retail_product_id`),
  `retail_units_per_wholesale`=VALUES(`retail_units_per_wholesale`),
  `updated_at`=VALUES(`updated_at`);

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Metro Snacks Distributor', 'Ana Reyes', '0917-555-0141', 'orders@metrosnacks.example', 'Manila, Philippines', 'active', '2026-04-19 08:24:27', '2026-04-19 08:24:27'),
(2, 'Pinoy Wholesale Mart', 'Ramon Cruz', '0918-555-0222', 'sales@pinoywholesale.example', 'Quezon City, Philippines', 'active', '2026-04-19 08:24:27', '2026-04-19 08:24:27'),
(3, 'Sweet Treats Supply Co.', 'Liza Santos', '0919-555-0333', 'supply@sweettreats.example', 'Pasig City, Philippines', 'active', '2026-04-19 08:24:27', '2026-04-19 08:24:27')
ON DUPLICATE KEY UPDATE
  `name`=VALUES(`name`),
  `contact_person`=VALUES(`contact_person`),
  `phone`=VALUES(`phone`),
  `email`=VALUES(`email`),
  `address`=VALUES(`address`),
  `status`=VALUES(`status`),
  `updated_at`=VALUES(`updated_at`);

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
(14, 'ORD-20260421-863', 2, 156.80, 140.00, 0.00, 0.00, 16.80, 'gcash', 156.80, 'completed', '2026-04-21 08:41:58'),
(15, 'ORD-20260423-432', 2, 100.00, 89.29, 0.00, 0.00, 10.71, 'cash', 100.00, 'completed', '2026-04-23 13:25:18'),
(16, 'ORD-20260425-202', 2, 300.00, 267.86, 0.00, 0.00, 32.14, 'cash', 500.00, 'completed', '2026-04-25 01:03:52'),
(17, 'ORD-20260425-047', 2, 100.00, 89.29, 0.00, 0.00, 10.71, 'cash', 100.00, 'completed', '2026-04-25 01:41:24'),
(18, 'ORD-20260425-907', 2, 22.00, 19.64, 0.00, 0.00, 2.36, 'cash', 100.00, 'completed', '2026-04-25 01:45:13')
ON DUPLICATE KEY UPDATE
  `order_number`=VALUES(`order_number`),
  `user_id`=VALUES(`user_id`),
  `total_amount`=VALUES(`total_amount`),
  `subtotal`=VALUES(`subtotal`),
  `discount_percent`=VALUES(`discount_percent`),
  `discount_amount`=VALUES(`discount_amount`),
  `tax_amount`=VALUES(`tax_amount`),
  `payment_method`=VALUES(`payment_method`),
  `amount_received`=VALUES(`amount_received`),
  `status`=VALUES(`status`);

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `cost_price_at_sale`, `total_price`) VALUES
(1, 5, 5, 3, 100.00, 72.00, 300.00),
(2, 6, 5, 3, 100.00, 72.00, 300.00),
(3, 7, 12, 1, 22.00, 15.00, 22.00),
(4, 7, 5, 1, 100.00, 72.00, 100.00),
(5, 8, 12, 1, 22.00, 15.00, 22.00),
(6, 8, 16, 1, 140.00, 102.00, 140.00),
(7, 8, 9, 1, 20.00, 13.50, 20.00),
(8, 8, 6, 1, 32.00, 22.50, 32.00),
(9, 8, 4, 1, 25.00, 17.00, 25.00),
(10, 9, 12, 1, 22.00, 15.00, 22.00),
(11, 9, 5, 1, 100.00, 72.00, 100.00),
(12, 10, 9, 2, 20.00, 13.50, 40.00),
(13, 10, 16, 2, 140.00, 102.00, 280.00),
(14, 11, 7, 1, 34.00, 24.00, 34.00),
(15, 12, 16, 1, 140.00, 102.00, 140.00),
(16, 13, 12, 1, 22.00, 15.00, 22.00),
(17, 13, 16, 1, 140.00, 102.00, 140.00),
(18, 14, 16, 1, 140.00, 102.00, 140.00),
(19, 15, 5, 1, 100.00, 72.00, 100.00),
(20, 16, 5, 3, 100.00, 72.00, 300.00),
(21, 17, 5, 1, 100.00, 72.00, 100.00),
(22, 18, 12, 1, 22.00, 15.00, 22.00)
ON DUPLICATE KEY UPDATE
  `order_id`=VALUES(`order_id`),
  `product_id`=VALUES(`product_id`),
  `quantity`=VALUES(`quantity`),
  `unit_price`=VALUES(`unit_price`),
  `cost_price_at_sale`=VALUES(`cost_price_at_sale`),
  `total_price`=VALUES(`total_price`);

INSERT INTO `inventory_reports` (`id`, `date`, `product_id`, `user_id`, `change_type`, `quantity`, `quantity_changed`, `previous_quantity`, `new_quantity`, `remarks`, `created_at`) VALUES
(1, '2026-04-24', 5, 1, 'Removed', 40, 40, 41, 1, 'Stock removed by admin. Previous: 41, New: 1', '2026-04-24 11:04:42'),
(2, '2026-04-24', 5, 1, 'Added', 9, 9, 1, 10, 'Stock added by admin. Previous: 1, New: 10', '2026-04-24 11:05:44'),
(3, '2026-04-24', 5, 1, 'Added', 10, 10, 10, 20, 'Stock added by admin. Previous: 10, New: 20', '2026-04-24 11:05:52')
ON DUPLICATE KEY UPDATE
  `date`=VALUES(`date`),
  `product_id`=VALUES(`product_id`),
  `user_id`=VALUES(`user_id`),
  `change_type`=VALUES(`change_type`),
  `quantity`=VALUES(`quantity`),
  `quantity_changed`=VALUES(`quantity_changed`),
  `previous_quantity`=VALUES(`previous_quantity`),
  `new_quantity`=VALUES(`new_quantity`),
  `remarks`=VALUES(`remarks`);

INSERT INTO `payment_qrcodes` (`id`, `payment_method`, `qr_code_path`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'gcash', '', 'GCash Payment QR Code', 0, '2026-04-21 07:19:45', '2026-04-21 08:08:52'),
(2, 'gcash', '', 'GCash Payment QR Code', 0, '2026-04-21 07:20:44', '2026-04-21 08:08:52'),
(3, 'gcash', 'public/uploads/qrcodes/gcash_qr_1776758937.png', 'GCash Payment QR Code', 1, '2026-04-21 08:08:57', '2026-04-21 08:13:37')
ON DUPLICATE KEY UPDATE
  `payment_method`=VALUES(`payment_method`),
  `qr_code_path`=VALUES(`qr_code_path`),
  `description`=VALUES(`description`),
  `is_active`=VALUES(`is_active`),
  `updated_at`=VALUES(`updated_at`);

INSERT INTO `store_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `created_at`, `updated_at`) VALUES
(1, 'store_name', 'Pinoy Chichirya Wholesale & Retail', 'text', '2026-04-19 07:40:09', '2026-04-19 07:40:09'),
(2, 'currency', 'PHP', 'text', '2026-04-19 07:40:09', '2026-04-19 07:40:09'),
(3, 'tax_rate', '12', 'number', '2026-04-19 07:40:09', '2026-04-25 00:28:32'),
(4, 'receipt_header', 'Pinoy Chichirya Wholesale & RetailManila, Philippines', 'text', '2026-04-19 07:40:09', '2026-04-25 00:26:50'),
(5, 'receipt_footer', 'Maraming Salamat Po! Balik kayo.', 'text', '2026-04-19 07:40:09', '2026-04-19 07:40:09'),
(7, 'store_branch', '', 'text', '2026-04-25 00:26:47', '2026-04-25 00:26:47'),
(8, 'store_address', 'asdasdaasd', 'text', '2026-04-25 00:26:48', '2026-04-25 00:26:48'),
(9, 'store_phone', 'asdasdas', 'text', '2026-04-25 00:26:48', '2026-04-25 00:26:48'),
(10, 'store_email', 'asdas@gmail.com', 'text', '2026-04-25 00:26:48', '2026-04-25 00:26:48'),
(11, 'business_hours_open', '08:00', 'text', '2026-04-25 00:26:49', '2026-04-25 00:26:49'),
(12, 'business_hours_close', '20:00', 'text', '2026-04-25 00:26:49', '2026-04-25 00:26:49'),
(13, 'business_days', 'Monday to Sunday', 'text', '2026-04-25 00:26:49', '2026-04-25 00:26:49'),
(17, 'currency_symbol', 'PHP', 'text', '2026-04-25 00:26:50', '2026-04-25 00:26:50'),
(18, 'receipt_show_logo', '1', 'text', '2026-04-25 00:26:50', '2026-04-25 00:26:50'),
(19, 'receipt_show_cashier', '1', 'text', '2026-04-25 00:26:51', '2026-04-25 00:26:51')
ON DUPLICATE KEY UPDATE
  `setting_key`=VALUES(`setting_key`),
  `setting_value`=VALUES(`setting_value`),
  `setting_type`=VALUES(`setting_type`),
  `updated_at`=VALUES(`updated_at`);

ALTER TABLE `users` AUTO_INCREMENT=6;
ALTER TABLE `categories` AUTO_INCREMENT=6;
ALTER TABLE `products` AUTO_INCREMENT=17;
ALTER TABLE `product_batches` AUTO_INCREMENT=17;
ALTER TABLE `product_breakdown_links` AUTO_INCREMENT=2;
ALTER TABLE `suppliers` AUTO_INCREMENT=4;
ALTER TABLE `orders` AUTO_INCREMENT=19;
ALTER TABLE `order_items` AUTO_INCREMENT=23;
ALTER TABLE `inventory_reports` AUTO_INCREMENT=4;
ALTER TABLE `payment_qrcodes` AUTO_INCREMENT=4;
ALTER TABLE `store_settings` AUTO_INCREMENT=34;

COMMIT;
SET FOREIGN_KEY_CHECKS=1;
