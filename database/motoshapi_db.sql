-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 21, 2025 at 02:57 AM
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
-- Database: `motoshapi_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `about_us`
--

CREATE TABLE `about_us` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `photo_url` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `about_us`
--

INSERT INTO `about_us` (`id`, `name`, `photo_url`, `description`) VALUES
(1, 'John Carlo Marasigan', 'uploads/about_us/68554c6f25148.jpg', 'John Carlo is the technical backbone of Motoshapi, serving as our Database Administrator and Supporting Programmer. With expertise in database management, backend development, and server optimization, he ensure that our platform runs smoothly, securely, and efficiently. His work in maintaining data integrity, improving site performance, and implementing new features helps deliver a seamless experience for our users.'),
(2, 'Marc Angelo Canillas', 'uploads/about_us/6852bc5f445e0.jpg', 'Meet Our Lead Programmer, Marc is the visionary developer behind the Motoshapi website, with a passion for crafting clean, efficient, and innovative code. With expertise in Front-end Development, Back-end Development, he brings technical excellence and creative problem-solving to every project. Dedicated to continuous learning and cutting-edge solutions, Marc ensures our software is both powerful and user-friendly.'),
(3, 'Ralph Mathew Cawilan', 'uploads/about_us/68554b71c8449.jpg', 'Meet Our Editor, Ralph is the dedicated editor behind Motoshapi, bringing his passion for editing every piece of content. With a keen eye for detail and a commitment to accuracy, Ralph ensures that our readers receive informative, engaging, and well-crafted articles. He has a background in Web Designing, and his expertise helps shape Motoshapi into a trusted resource for our beloved customers.');

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`, `email`, `full_name`, `last_login`, `status`, `created_at`, `updated_at`) VALUES
(1, 'jc', 'jc123', 'jc@example.com', 'System Administrator', '2025-06-13 12:31:15', 'active', '2025-06-11 04:44:55', '2025-06-13 12:31:15'),
(2, 'marc', 'marc123', 'marc@example.com', 'Marc', '2025-06-21 00:57:19', 'active', '2025-06-11 05:03:41', '2025-06-21 00:57:19'),
(3, 'ralph', 'ralph123', 'ralph@example.com', 'Ralph', '2025-06-11 05:19:41', 'active', '2025-06-11 05:19:25', '2025-06-11 05:19:41');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`) VALUES
(2, 'Tires', 'Upgrade your bike with high-quality tires for unbeatable grip, durability, and performance. Perfect for street, sport, or off-road riding.\r\n\r\n✔ Superior traction & stability\r\n✔ Long-lasting tread life\r\n✔ Best brands at great prices\r\n\r\nGet the right tires for your ride—shop now! 🏍️🔥', '2025-06-09 11:54:15'),
(3, 'Mags', 'Upgrade your bike\'s look and performance with our premium motorcycle mags. Lightweight, stylish, and built for strength—perfect for any rider.\r\n\r\n✔ Strong alloy construction\r\n✔ Sleek, eye-catching designs\r\n✔ Better handling & durability\r\n\r\nBoost your bike\'s style & performance—shop now! 🏍️✨\r\n\r\n', '2025-06-09 11:56:09'),
(4, 'Motor Oil', '\r\nKeep your engine running stronger for longer with our high-performance motorcycle oils. Formulated for maximum protection, smoother shifts, and extended engine life.\r\n\r\n✔ Advanced synthetic & mineral blends\r\n✔ Enhanced heat & friction resistance\r\n✔ Optimal clutch & gearbox performance\r\n\r\nProtect your ride – choose the right oil today! 🏍️⚡', '2025-06-09 11:57:44');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `transaction_type` enum('online','walkin') DEFAULT 'online',
  `payment_mode_id` int(11) DEFAULT NULL,
  `payment_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payment_details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `first_name`, `last_name`, `total_amount`, `status`, `transaction_type`, `payment_mode_id`, `payment_details`, `created_at`, `updated_at`) VALUES
(14, 4, 'joki', 'banks', 13000.00, 'delivered', 'online', 1, NULL, '2025-06-13 11:32:21', '2025-06-13 11:32:21'),
(15, 4, 'Marc Angelo', 'Canillas', 25500.00, 'delivered', 'online', 1, NULL, '2025-06-18 14:01:49', '2025-06-18 14:01:49'),
(35, 3, 'Marc Angelo', 'Canillas', 600.00, 'pending', 'online', 1, '[]', '2025-06-20 12:34:26', '2025-06-20 12:34:26');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`, `created_at`) VALUES
(10, 11, 9, 10, 750.00, '2025-06-13 11:17:28'),
(11, 12, 8, 10, 600.00, '2025-06-13 11:22:00'),
(12, 13, 9, 1, 750.00, '2025-06-13 11:22:43'),
(13, 14, 6, 1, 13000.00, '2025-06-13 11:32:21'),
(14, 15, 6, 1, 13000.00, '2025-06-18 14:01:49'),
(15, 15, 5, 1, 12500.00, '2025-06-18 14:01:49'),
(23, 29, 7, 1, 950.00, '2025-06-19 03:22:29'),
(24, 30, 9, 11, 750.00, '2025-06-19 04:25:45'),
(25, 31, 3, 1, 22000.00, '2025-06-20 04:26:55'),
(26, 31, 8, 1, 600.00, '2025-06-20 04:26:55'),
(27, 32, 7, 1, 950.00, '2025-06-20 05:11:50'),
(28, 33, 3, 1, 22000.00, '2025-06-20 06:27:07'),
(29, 34, 7, 1, 950.00, '2025-06-20 12:33:54'),
(30, 34, 8, 1, 600.00, '2025-06-20 12:33:54'),
(31, 34, 2, 1, 18000.00, '2025-06-20 12:33:54'),
(32, 35, 8, 1, 600.00, '2025-06-20 12:34:26');

-- --------------------------------------------------------

--
-- Table structure for table `payment_modes`
--

CREATE TABLE `payment_modes` (
  `id` int(11) NOT NULL,
  `mode_name` varchar(50) NOT NULL,
  `mode_code` varchar(20) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_modes`
--

INSERT INTO `payment_modes` (`id`, `mode_name`, `mode_code`, `is_active`, `created_at`) VALUES
(1, 'Cash on Delivery (COD)', 'cod', 1, '2025-06-18 14:51:27');

-- --------------------------------------------------------


CREATE TABLE `payment_settings` (
  `id` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `account_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`account_details`)),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_settings`
--

-- No payment settings needed for COD-only system

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `description`, `price`, `stock`, `image_url`, `created_at`, `updated_at`, `featured`, `is_active`) VALUES
(2, 2, 'Pirelli Diablo Rosso Slim-Type Tire', '✔ Type: High-performance street/sport tire (ideal for PH roads & weather)\r\n✔ Popular PH Bike Fitments:\r\n\r\n150cc-400cc bikes: R15, Ninja 250/400, Dominar 250/400, CBR250/300\r\n\r\nScooters: NMAX 155 (rear conversions), PCX160 (check size variants)\r\n✔ Compound: Dual-compound (longer wear in PH heat + grip for twisties like Marilaque)\r\n✔ Tread: Rain channels for wet-season safety (monsoon-ready)\r\n✔ PH-Specific Advantage: Slim profile fits common PH sport/modified setups without swingarm mods', 18000.00, 10, 'uploads/products/6846ce2076e89.png', '2025-06-09 12:05:52', '2025-06-09 12:05:52', 0, 1),
(3, 2, 'Pirelli Diablo Rosso Track-Spec Tire', '✔ Type: Track-focused sport tire (street-legal but race-bred)\r\n✔ PH Bike Fitment (Common Models):\r\n\r\n300cc-600cc: Ninja 400, Yamaha R3/R7, CBR500R, Duke 390\r\n\r\n600cc+: ZX-6R, CBR650R (check exact size requirements)\r\n\r\n✔ Track-Optimized Features:\r\n\r\nFull-slick center with minimal grooves (max dry grip)\r\n\r\nSoft race compound for aggressive lean angles\r\n\r\nStiffer sidewalls for track stability at high speeds', 22000.00, 8, 'uploads/products/6846cfe0da582.png', '2025-06-09 12:13:20', '2025-06-09 12:13:20', 1, 1),
(4, 2, 'Bridgestone Dual Sport Tire', '✔ Type: 80% Road / 20% Off-Road (Perfect for PH Adventure & Rough Roads)\r\n✔ PH Bike Compatibility:\r\n\r\nAdventure Bikes: Honda CB500X, Versys 650, BMW G310GS, KTM 390 Adventure\r\n\r\nBig Scooters: Yamaha TMAX, XMAX 300 (check size availability)\r\n\r\nEnduro/Trail: CRF250L, KLX250 (for riders who prioritize road comfort)\r\n\r\n✔ Key Features for PH Riders:\r\n\r\nDurable center tread for highway longevity (Manila to Baguio rides)\r\n\r\nAggressive shoulder blocks for light off-road (Dirt to Sierra Madre trails)\r\n\r\nWet-optimized grooves – Safe for monsoon season riding', 14500.00, 8, 'uploads/products/6846d12e95708.png', '2025-06-09 12:18:54', '2025-06-09 12:18:54', 0, 1),
(5, 3, 'RacingBoy 6-Spoke Alloy Mags', '✔ Type: Lightweight Aftermarket Alloy Wheels (Sport/Street Design)\r\n✔ PH Bike Compatibility:\r\n\r\nUnderbones: Yamaha Sniper 155, Mio i125/i125S, Honda RS150, Suzuki Raider 150\r\n\r\nSport Bikes: R15 V3/V4, Fazzio 125 (check hub compatibility)\r\n\r\nMaxi-Scooters: Aerox 155, NMAX 155 (rear wheel conversions only)\r\n\r\n✔ Key Features:\r\n\r\n6-spoke forged alloy – Strong yet lightweight for better acceleration\r\n\r\nJ-Size Options: 1.85x17 (front) / 2.15x17 (rear) – Fits 70/90 to 100/80 tires\r\n\r\nColors: Gold, Black, Red, Silver (matte/gloss)', 12500.00, 5, 'uploads/products/6846d5ff648a2.png', '2025-06-09 12:39:27', '2025-06-09 12:39:27', 1, 1),
(6, 3, 'RacingBoy 5-Spoke Alloy Mags ', '✔ Type: Lightweight Performance Wheels (Street & Racing Use)\r\n✔ PH Bike Compatibility:\r\n\r\nUnderbones: Yamaha Sniper 155, Mio i125/i125S, Honda RS150, Suzuki Raider 150\r\n\r\nSport Bikes: Yamaha R15 (V3/V4), Kawasaki Rouser NS200\r\n\r\nScooters: Aerox 155 (rear conversion only), Fazzio 125 (check fitment)\r\n\r\n✔ Key Features:\r\n\r\n5-spoke forged alloy – Aggressive design with high strength-to-weight ratio\r\n\r\nTubeless-ready – Improves safety & reduces puncture risks\r\n\r\nJ-Size Options: 1.60x17 / 1.85x17 / 2.15x17 (fits 70/90 to 110/90 tires)\r\n\r\nColors: Matte Black, Gold, Red, Silver', 13000.00, 6, 'uploads/products/6846d64918082.png', '2025-06-09 12:40:41', '2025-06-09 12:40:41', 0, 1),
(7, 4, 'Motul Fully Syntethic Oil', '✔ Type: High-performance 100% synthetic engine oil (ester-based technology)\r\n✔ Best for PH Motorcycles:\r\n\r\nSmall Bikes (Underbones/Scoots): Mio i125, Click 125, PCX160, Aerox 155 (Motul 7100 10W-40)\r\n\r\nBig Bikes (400cc+): Ninja 400, Dominar 400, Z650 (Motul 300V 15W-50)\r\n\r\nHigh-Revving Sportbikes: Yamaha R15, R3, CBR150R (Motul 5100 15W-50)\r\n\r\n✔ Key Benefits for PH Riders:\r\n\r\nHeat resistance – Stays stable in PH traffic & long rides (Baguio to Batangas)\r\n\r\nEngine protection – Reduces wear on high-mileage bikes (ideal for Grab riders)\r\n\r\nSmoother shifts – Optimized for PH common wet-clutch systems', 950.00, 12, 'uploads/products/6846d702dd1aa.png', '2025-06-09 12:43:46', '2025-06-09 12:43:46', 1, 1),
(8, 4, 'Petron Fully Syntethic Oil', '✔ Type: 100% Synthetic Engine Oil (API SN/JASO MA2 Certified)\r\n✔ PH Motorcycle Compatibility:\r\n\r\nUnderbones/Scooters: Mio i125, Click 125/160, PCX160, Aerox 155 (10W-40)\r\n\r\nSport Bikes: Yamaha R15, Suzuki Raider 150, Kawasaki Rouser NS200 (15W-50)\r\n\r\nBig Bikes: Dominar 400, Ninja 650 (20W-50 for high-temp protection)\r\n\r\n✔ Key Features for PH Riders:\r\n\r\nTraffic-ready – Superior heat resistance for stop-and-go Metro Manila rides\r\n\r\nWet-clutch safe – Prevents slippage in common PH bikes\r\n\r\nSludge prevention – Cleans old engine deposits (ideal for 2nd-hand bikes)', 600.00, 14, 'uploads/products/6846d7426af2d.png', '2025-06-09 12:44:50', '2025-06-09 12:44:50', 0, 1),
(9, 4, 'Shell Fully Syntethic Oil', '✔ Type: 100% Synthetic Engine Oil (API SN+/JASO MA2 Certified)\r\n✔ PH Motorcycle Compatibility:\r\n\r\nUnderbones/Scooters: Mio i125, Click 125/160, PCX160, Aerox 155 (Shell Advance Ultra 10W-40)\r\n\r\nSport Bikes: Yamaha R15, Suzuki GSX-R150, Kawasaki Ninja 250 (Shell Advance Ultra 15W-50)\r\n\r\nBig Bikes: Dominar 400, CB650R (Shell Advance Ultra 20W-50)\r\n\r\n✔ Key Features for PH Riders:\r\n\r\nActive Cleansing Tech – Reduces engine deposits (ideal for old/taxi bikes)\r\n\r\nTriple Protection+ – Anti-wear, heat resistance & fuel efficiency\r\n\r\nWet-Clutch Optimized – Safe for PH\'s common manual-transmission bikes', 750.00, 13, 'uploads/products/6846d78bb144b.png', '2025-06-09 12:46:03', '2025-06-09 12:46:03', 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `shipping_information`
--

CREATE TABLE `shipping_information` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `house_number` varchar(20) NOT NULL,
  `street` varchar(255) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `payment_mode_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shipping_information`
--

INSERT INTO `shipping_information` (`id`, `order_id`, `first_name`, `last_name`, `email`, `phone`, `house_number`, `street`, `barangay`, `city`, `province`, `postal_code`, `payment_mode_id`, `created_at`) VALUES
(9, 14, 'joki', 'banks', 'muryel@gmail.com', '09123456788', '111', 'zone 1', 'bunggo', 'calamba', 'laguna', '4027', 1, '2025-06-13 11:32:21'),
(10, 15, 'Marc Angelo', 'Canillas', 'canillasmarc04@gmail.com', '09163910082', '123', 'qwase', 'bungooopA', 'Calamba, City of', 'Laguna', '4027', 1, '2025-06-18 14:01:49'),
(17, 29, 'Marc Angelo', 'Canillas', 'canillasmarc04@gmail.com', '09163910082', '3123', 'asf', 'bungooopA', 'Calamba, City of', 'Laguna', '4027', 2, '2025-06-19 03:22:29'),
(18, 31, 'Marc Angelo', 'Canillas', 'canillasmarc04@gmail.com', '09163910082', '123', 'gsfgshfhj', 'bungoo', 'Calamba, City of', 'Laguna', '4027', 3, '2025-06-20 04:26:55'),
(19, 32, 'Marc Angelo', 'Canillas', 'canillasmarc04@gmail.com', '09163910082', '123', 'gsfgshfhj', 'bungoo', 'Calamba, City of', 'Laguna', '4027', 2, '2025-06-20 05:11:50'),
(20, 33, 'Marc Angelo', 'Canillas', 'canillasmarc04@gmail.com', '09163910082', '3123', 'asf', 'asd', 'Calamba, City of', 'Laguna', '4027', 2, '2025-06-20 06:27:07'),
(21, 34, 'Marc Angelo', 'Canillas', 'canillasmarc04@gmail.com', '09163910082', '3123', 'asf', 'asd', 'Calamba, City of', 'Laguna', '4027', 2, '2025-06-20 12:33:54'),
(22, 35, 'Marc Angelo', 'Canillas', 'canillasmarc04@gmail.com', '09163910082', '123', 'asf', 'bungoo', 'Calamba, City of', 'Laguna', '4027', 1, '2025-06-20 12:34:26');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `status`, `created_at`, `updated_at`) VALUES
(3, 'gilbert', '$2y$10$g8v1CgY5ktTzz3DrVWNDCeTrONsamJ.fpI8.QV2.iYl7UcdPWOTzO', 'gilberto@gmail.com', 'user', 'active', '2025-06-11 09:17:01', '2025-06-11 09:17:01'),
(4, 'bobmarley', '$2y$10$Q6sPPSr/J96VYZS2q4GJo.dYvhVD7ta.760qjC4bCbOIruGKXapX.', 'bob@gmail.com', 'user', 'active', '2025-06-12 12:09:19', '2025-06-12 12:09:19');

-- --------------------------------------------------------

--
-- Table structure for table `variations`
--

CREATE TABLE `variations` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variation` varchar(100) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `price` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `variations`
--

INSERT INTO `variations` (`id`, `product_id`, `variation`, `stock`, `price`, `is_active`, `created_at`) VALUES
(1, 5, 'Gold', 2, NULL, 1, '2025-06-09 12:39:27'),
(2, 5, 'Black', 3, NULL, 1, '2025-06-09 12:39:27'),
(3, 5, 'Red', 4, NULL, 1, '2025-06-09 12:39:27'),
(4, 5, 'Matte Silver', 3, NULL, 1, '2025-06-09 12:39:27'),
(5, 5, 'Gloss Silver', 5, NULL, 1, '2025-06-09 12:39:27');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `about_us`
--
ALTER TABLE `about_us`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_payment_mode_order` (`payment_mode_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `payment_modes`
--
ALTER TABLE `payment_modes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mode_name` (`mode_name`),
  ADD UNIQUE KEY `mode_code` (`mode_code`);

--
-- Indexes for table `payment_settings`
--
ALTER TABLE `payment_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_type` (`payment_type`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `shipping_information`
--
ALTER TABLE `shipping_information`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `fk_payment_mode` (`payment_mode_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `variations`
--
ALTER TABLE `variations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `about_us`
--
ALTER TABLE `about_us`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `payment_modes`
--
ALTER TABLE `payment_modes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payment_settings`
--
ALTER TABLE `payment_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `shipping_information`
--
ALTER TABLE `shipping_information`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `variations`
--
ALTER TABLE `variations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Table structure for table `sms_logs`
--

CREATE TABLE `sms_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone_number` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status` enum('sent','failed','received','disabled') NOT NULL DEFAULT 'sent',
  `response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_phone` (`phone_number`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_payment_mode_order` FOREIGN KEY (`payment_mode_id`) REFERENCES `payment_modes` (`id`),
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `shipping_information`
--
ALTER TABLE `shipping_information`
  ADD CONSTRAINT `fk_payment_mode` FOREIGN KEY (`payment_mode_id`) REFERENCES `payment_modes` (`id`),
  ADD CONSTRAINT `shipping_information_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `variations`
--
ALTER TABLE `variations`
  ADD CONSTRAINT `variations_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
