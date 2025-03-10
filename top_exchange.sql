-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 06, 2025 at 03:12 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `top_exchange`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`id`, `username`, `password`, `created_at`, `role`) VALUES
(56, 'admin', '123', '2025-03-05 22:58:17', 'Admin'),
(57, 'Secretary', '123', '2025-03-05 23:17:38', 'Secretary'),
(58, 'aedan', '123', '2025-03-05 23:23:25', 'Admin'),
(60, 'Manager', '123', '2025-03-05 23:27:49', 'Manager'),
(61, 'Accountant', '123', '2025-03-05 23:27:55', 'Accountant');

-- --------------------------------------------------------

--
-- Table structure for table `clients_accounts`
--

CREATE TABLE `clients_accounts` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `region` varchar(50) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `business_proof` text DEFAULT NULL,
  `status` enum('Pending','Active','Rejected','Inactive') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients_accounts`
--

INSERT INTO `clients_accounts` (`id`, `username`, `password`, `email`, `phone`, `region`, `city`, `company_address`, `business_proof`, `status`, `created_at`) VALUES
(16, 'asdasdaaa', '$2y$10$QkvJTl2ygPcEAOpauI6wpuGFNt5zA2IIm4aiPsYsyyLq7xZow4toS', 'aasdas@gmail.com', '1231', 'asdasd', 'asdasd', 'asdasdasd', '[\"\\/top_exchange\\/uploads\\/asdasdaaa\\/4.png\"]', 'Active', '2025-03-01 19:12:22'),
(17, 'admin', '$2y$10$Hh9CakUrlrjClJ9HKBb8OuqO3GTuldjIp88X6/L/yte82douvI1qK', 'aasd@gmail.com', '123', 'asd', 'asd', 'asdsad', '[\"\\/top_exchange\\/uploads\\/admin\\/POST 3.png\"]', 'Active', '2025-03-06 06:16:16');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `customer_name`, `created_at`) VALUES
(1, 'Solaire Entertainment City', '2025-02-18 04:23:50'),
(2, 'Tiger', '2025-02-18 04:23:50'),
(3, 'City of Dreams', '2025-02-18 04:23:50');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_date` datetime DEFAULT current_timestamp(),
  `delivery_date` date DEFAULT NULL,
  `order_status` varchar(50) DEFAULT 'Pending',
  `status` varchar(50) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `customer_id`, `order_date`, `delivery_date`, `order_status`, `status`, `total_amount`) VALUES
(2, 1, '2024-08-20 00:00:00', '2024-08-23', 'Pending', 'Pending', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `order_items`
--
DELIMITER $$
CREATE TRIGGER `before_insert_order_items` BEFORE INSERT ON `order_items` FOR EACH ROW BEGIN
    DECLARE available_stock INT;

    -- Get the current stock quantity
    SELECT stock_quantity INTO available_stock 
    FROM products 
    WHERE product_id = NEW.product_id;

    -- Check if there's enough stock
    IF available_stock < NEW.quantity THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Not enough stock available';
    ELSE
        -- Deduct the ordered quantity from stock
        UPDATE products 
        SET stock_quantity = stock_quantity - NEW.quantity
        WHERE product_id = NEW.product_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `page_id` int(11) NOT NULL,
  `page_name` varchar(50) NOT NULL,
  `file_path` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`page_id`, `page_name`, `file_path`) VALUES
(1, 'Accounts - Clients', 'accounts_clients.php'),
(2, 'Accounts - Admin', 'accounts.php'),
(3, 'Customers', 'customers.php'),
(4, 'Dashboard', 'dashboard.php'),
(5, 'Inventory', 'inventory.php'),
(6, 'User Roles', 'user_roles.php');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `item_description` varchar(255) NOT NULL,
  `packaging` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `category`, `item_description`, `packaging`, `price`, `stock_quantity`) VALUES
(1, 'Siopao', 'Asado Siopao (A Large)', '6pcs/pack', 280.00, 100),
(2, 'Siopao', 'Asado Siopao (A Med)', '10pcs/pack', 325.00, 100),
(3, 'Siopao', 'Asado Siopao (A Small)', '15pcs/pack', 270.00, 100),
(4, 'Siopao', 'Asado Siopao (B Large)', '6pcs/pack', 235.00, 100),
(5, 'Siopao', 'Asado Siopao (B Med)', '10pcs/pack', 250.00, 100),
(6, 'Siopao', 'Asado Siopao (B Small)', '15pcs/pack', 205.00, 100),
(7, 'Siopao', 'Bola Bola Siopao (Large)', '6pcs/pack', 310.00, 100),
(8, 'Siopao', 'Bola Bola Siopao (Med)', '10pcs/pack', 350.00, 100),
(9, 'Siopao', 'Bola Bola Siopao (Small)', '15pcs/pack', 290.00, 100),
(10, 'Siopao', 'Jumbo Pao', '4pcs/pack', 325.00, 100),
(11, 'Siopao', 'Cuaopao', '10pcs/pack', 125.00, 0),
(12, 'Siopao', 'Minibun Mantao', '12pcs/pack', 115.00, 0),
(13, 'Siopao', 'Egg Custard Pao (Min 10 packs)', '8pcs/pack', 150.00, 0),
(14, 'Dimsum & Dumplings', 'Regular Pork Siomai', '30pcs/pack', 145.00, 0),
(15, 'Dimsum & Dumplings', 'Special Pork Siomai', '30pcs/pack', 240.00, 0),
(16, 'Dimsum & Dumplings', 'Regular Sharksfin Dumpling', '30pcs/pack', 180.00, 0),
(17, 'Dimsum & Dumplings', 'Special Sharksfin Dumpling', '30pcs/pack', 260.00, 0),
(18, 'Dimsum & Dumplings', 'Kutchay Dumpling', '30pcs/pack', 275.00, 0),
(19, 'Dimsum & Dumplings', 'Chicken Siomai', '30pcs/pack', 300.00, 0),
(20, 'Dimsum & Dumplings', 'Beef Siomai', '20pcs/pack', 250.00, 0),
(21, 'Dimsum & Dumplings', 'Premium Pork Siomai (A)', '20pcs/pack', 280.00, 0),
(22, 'Dimsum & Dumplings', 'Premium Pork Siomai w/ Shrimp (A)', '20pcs/pack', 310.00, 0),
(23, 'Dimsum & Dumplings', 'Premium Sharksfin Dumpling', '20pcs/pack', 300.00, 0),
(24, 'Dimsum & Dumplings', 'Hakaw (Shrimp Dumpling) (A)', '12pcs/pack', 300.00, 0),
(25, 'Dimsum & Dumplings', 'Hakaw (Shrimp Dumpling) (B)', '20pcs/pack', 480.00, 0),
(26, 'Dimsum & Dumplings', 'Japanese Pork Siomai (A)', '20pcs/pack', 325.00, 0),
(27, 'Dimsum & Dumplings', 'Polonchay Dumpling (Min 6 Packs) (A)', '12pcs/pack', 310.00, 0),
(28, 'Dimsum & Dumplings', 'Polonchay Dumpling (Min 6 Packs) (B)', '20pcs/pack', 470.00, 0),
(29, 'Dimsum & Dumplings', 'Polonchay Dumpling w/ Shrimp (Min 6 Packs) (A)', '12pcs/pack', 330.00, 0),
(30, 'Dimsum & Dumplings', 'Polonchay Dumpling w/ Shrimp (Min 6 Packs) (B)', '20pcs/pack', 530.00, 0),
(31, 'Dimsum & Dumplings', 'Beancurd Roll (A)', '12pcs/pack', 310.00, 699),
(32, 'Dimsum & Dumplings', 'Beancurd Roll (B)', '20pcs/pack', 500.00, 0),
(33, 'Dimsum & Dumplings', 'Pork Gyoza Dumpling (A)', '20pcs/pack', 390.00, 0),
(34, 'Dimsum & Dumplings', 'Shanghai Dumpling (A)', '20pcs/pack', 255.00, 0),
(35, 'Dimsum & Dumplings', 'Siao Long Pao', '15pcs/pack', 270.00, 0),
(36, 'Dimsum & Dumplings', 'Wanton Regular', '20pcs/pack', 315.00, 0),
(37, 'Dimsum & Dumplings', 'Sesame Butchi Ball', '12pcs/pack', 185.00, 0),
(38, 'Dimsum & Dumplings', 'Machang (Hong Kong)', '6pcs/pack', 250.00, 0),
(39, 'Dimsum & Dumplings', 'Machang w/ Chestnut (Min 6 Packs)', '1pc', 110.00, 0),
(40, 'Dimsum & Dumplings', 'Pork Rib Taosi', '500g', 200.00, 0),
(41, 'Dimsum & Dumplings', 'Pork Spring Roll', '20pcs/pack', 320.00, 0),
(42, 'Dimsum & Dumplings', 'Chicken Feet', '500g', 200.00, 0),
(43, 'Dimsum & Dumplings', 'Radish Cake 1.5kg', '1.5kg', 370.00, 0),
(44, 'Dimsum & Dumplings', 'Radish Cake 1kg', '1kg', 300.00, 0),
(45, 'Dimsum & Dumplings', 'Pumpkin Cake 1.5kg', '1.5kg', 370.00, 0),
(46, 'Dimsum & Dumplings', 'Pumpkin Cake 1kg', '1kg', 300.00, 0),
(47, 'Healthy Dimsum', 'Vegetable Dumpling (A)', '12pcs/pack', 190.00, 0),
(48, 'Healthy Dimsum', 'Vegetable Dumpling (B)', '20pcs/pack', 300.00, 0),
(49, 'Healthy Dimsum', 'Vegetable Spring Roll (A)', '12pcs/pack', 230.00, 0),
(50, 'Healthy Dimsum', 'Vegetable Spring Roll (B)', '20pcs/pack', 360.00, 0),
(51, 'Sauces', 'Chili Sauce (A)', '1.5kg/cntr', 590.00, 0),
(52, 'Sauces', 'Chili Sauce (B)', '220g/btl', 160.00, 0),
(53, 'Sauces', 'Seafood XO Sauce', '220g/btl', 320.00, 0),
(54, 'Sauces', 'Lemon Sauce (A)', '420g/btl', 135.00, 0),
(55, 'Sauces', 'Sweet & Sour Sauce (A)', '420g/btl', 135.00, 0),
(56, 'Sauces', 'Beef Fillet Sauce', '420g/btl', 150.00, 0),
(57, 'Sauces', 'Lemon Sauce (B)', '3.5kg/Gal', 620.00, 0),
(58, 'Sauces', 'Sweet & Sour Sauce (B)', '3.5kg/Gal', 620.00, 0),
(59, 'Marinated Items', 'Asado Marinated (Char Siu)', '1kg', 400.00, 0),
(60, 'Marinated Items', 'Asado Cooked (Char Siu)', '1kg', 700.00, 0),
(61, 'Noodles & Wrappers', 'Pancit Canton', '2kg/pack', 350.00, 0),
(62, 'Noodles & Wrappers', 'Dried Egg Noodles', '1kg/pack', 185.00, 0),
(63, 'Noodles & Wrappers', 'Hongkong Noodles (Yellow/White)', '1kg/pack', 185.00, 0),
(64, 'Noodles & Wrappers', 'Shanghai Noodles (Yellow/White)', '2kg/pack', 360.00, 0),
(65, 'Noodles & Wrappers', 'Hofan Noodles (Minimum 6 packs)', '1kg/pack', 170.00, 0),
(66, 'Noodles & Wrappers', 'Ramen Noodles', '1kg/pack', 195.00, 0),
(67, 'Noodles & Wrappers', 'Spinach Noodles (Minimum 6 packs)', '1kg/pack', 195.00, 0),
(68, 'Noodles & Wrappers', 'Siomai Wrapper (Yellow/White)', '250g/pack', 70.00, 0),
(69, 'Noodles & Wrappers', 'Wanton Wrapper (Yellow/White)', '250g/pack', 70.00, 0),
(70, 'Noodles & Wrappers', 'Beancurd Wrapper', '1kg/pack', 1600.00, 0),
(71, 'Noodles & Wrappers', 'Spring Roll Wrapper', '25pcs/pack', 90.00, 0),
(72, 'Noodles & Wrappers', 'Gyoza Wrapper (Minimum 10 Packs)', '250g/pack', 70.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `pages` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `status`, `pages`) VALUES
(1, 'Admin', 'active', 'Accounts - Admin, Accounts - Clients, Customers, Dashboard, User Roles, Inventory'),
(2, 'Manager', 'active', ''),
(3, 'Secretary', 'active', ''),
(4, 'Accountant', 'active', '');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_accounts_roles` (`role`);

--
-- Indexes for table `clients_accounts`
--
ALTER TABLE `clients_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `fk_orders_customers` (`customer_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`page_id`),
  ADD UNIQUE KEY `page_name` (`page_name`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`),
  ADD UNIQUE KEY `unique_role` (`role_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `clients_accounts`
--
ALTER TABLE `clients_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `page_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounts`
--
ALTER TABLE `accounts`
  ADD CONSTRAINT `fk_accounts_roles` FOREIGN KEY (`role`) REFERENCES `roles` (`role_name`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
