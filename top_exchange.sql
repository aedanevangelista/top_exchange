-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Apr 08, 2025 at 04:27 PM
-- Server version: 10.11.10-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u701062148_top_exchange`
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
  `role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `admin_session_id` varchar(255) DEFAULT NULL,
  `admin_last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`id`, `username`, `password`, `created_at`, `role`, `status`, `admin_session_id`, `admin_last_login`) VALUES
(56, 'admin', '123', '2025-03-05 22:58:17', 'Admin', 'Active', NULL, NULL),
(57, 'Secretary', '123', '2025-03-05 23:17:38', 'Secretary', 'Active', NULL, NULL),
(58, 'aedan', '123', '2025-03-05 23:23:25', 'aed', 'Active', NULL, NULL),
(60, 'Manager', '123', '2025-03-05 23:27:49', 'Manager', 'Active', NULL, NULL),
(61, 'Accountant', '123', '2025-03-05 23:27:55', 'Accountant', 'Archived', NULL, NULL),
(62, 'Ryan', '123', '2025-03-09 07:14:07', 'Admin', 'Archived', NULL, NULL),
(68, 'Test', '123', '2025-03-30 08:44:21', 'Secretary', 'Archived', NULL, NULL),
(69, 'aedanpogi', '123', '2025-03-30 14:22:26', 'Admin', 'Active', NULL, NULL),
(70, 'asddd', '123', '2025-03-30 15:42:31', 'Admin', 'Active', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `balance_history`
--

CREATE TABLE `balance_history` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clients_accounts`
--

CREATE TABLE `clients_accounts` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `company_address` varchar(255) DEFAULT NULL,
  `business_proof` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Active',
  `balance` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `client_session_id` varchar(255) DEFAULT NULL,
  `client_last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients_accounts`
--

INSERT INTO `clients_accounts` (`id`, `username`, `password`, `email`, `phone`, `region`, `city`, `company`, `company_address`, `business_proof`, `status`, `balance`, `created_at`, `client_session_id`, `client_last_login`) VALUES
(17, 'aedanevangelista', '$2y$10$jBu29UPrI.tiA6RY78Sfmeq3MX07il.3QHNi/5yLJp4pPPRR2u1eW', 'aedanevangelista@gmail.com', '0912345678', 'NCR', 'Quezon City', 'Top Exchange', '', '[\"\\/uploads\\/aedanevangelista\\/67ea26c320cf1_BeefFilletSauce.png\"]', 'Active', 0.00, '2025-03-31 05:23:15', NULL, NULL),
(18, 'aedanpogi', '$2y$10$8gTpS4G2a5./WFrpYVavL.OJYt.rF2d4Cqvi1QzKeTXbSxWWwWe5O', 'aedanpogi@gmail.com', '09185585149', 'NCR', 'Quezon City', '', '', '[\"\\/uploads\\/aedanpogi\\/67ea344a30392_BeancurdRoll.png\",\"\\/uploads\\/aedanpogi\\/67ea344a30655_BeefFilletSauce.png\",\"\\/uploads\\/aedanpogi\\/67ea344a3083b_BeefSiomai.png\"]', 'Pending', 0.00, '2025-03-31 06:20:58', NULL, NULL);

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
-- Table structure for table `monthly_payments`
--

CREATE TABLE `monthly_payments` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('Fully Paid','Partially Paid','Unpaid','For Approval') NOT NULL DEFAULT 'Unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `remaining_balance` decimal(10,2) DEFAULT NULL,
  `proof_image` varchar(255) DEFAULT NULL,
  `payment_method` varchar(20) DEFAULT NULL,
  `payment_type` enum('Internal','External') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `po_number` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `order_date` date NOT NULL,
  `delivery_date` date NOT NULL,
  `delivery_address` varchar(255) DEFAULT NULL,
  `orders` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`orders`)),
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('Pending','Active','Rejected','Completed') NOT NULL DEFAULT 'Pending',
  `contact_number` varchar(20) DEFAULT NULL,
  `special_instructions` text DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `delivery_fee` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `po_number`, `username`, `order_date`, `delivery_date`, `delivery_address`, `orders`, `total_amount`, `status`, `contact_number`, `special_instructions`, `payment_method`, `subtotal`, `delivery_fee`) VALUES
(50, 'aedanevangelista-1', 'aedanevangelista', '2025-03-31', '2025-04-02', 'assdasdsad', '[{\"product_id\":1,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Large)\",\"packaging\":\"6pcs/pack\",\"price\":280,\"quantity\":5},{\"product_id\":2,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Med)\",\"packaging\":\"10pcs/pack\",\"price\":325,\"quantity\":5}]', 3025.00, 'Completed', NULL, NULL, NULL, 0.00, 0.00),
(51, 'aedanevangelista-2', 'aedanevangelista', '2025-03-31', '2025-04-02', 'No company address available', '[{\"product_id\":1,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Large)\",\"packaging\":\"6pcs/pack\",\"price\":280,\"quantity\":5},{\"product_id\":17,\"category\":\"Dimsum & Dumplings\",\"item_description\":\"Special Sharksfin Dumpling\",\"packaging\":\"30pcs/pack\",\"price\":260,\"quantity\":5}]', 2700.00, 'Active', NULL, NULL, NULL, 0.00, 0.00);

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
(6, 'User Roles', 'user_roles.php'),
(7, 'Orders', 'orders.php'),
(8, 'Order History', 'order_history.php'),
(9, 'Payment History', 'payment_history.php');

-- --------------------------------------------------------

--
-- Table structure for table `payment_history`
--

CREATE TABLE `payment_history` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `proof_image` varchar(255) DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_type` enum('Internal','External') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_status_history`
--

CREATE TABLE `payment_status_history` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `changed_by` varchar(100) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `item_description` varchar(255) NOT NULL,
  `packaging` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `additional_description` text DEFAULT NULL,
  `product_image` varchar(255) DEFAULT NULL,
  `ingredients` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `category`, `product_name`, `item_description`, `packaging`, `price`, `stock_quantity`, `additional_description`, `product_image`, `ingredients`) VALUES
(1, 'Siopao', 'Asado Siopao', 'Asado Siopao (A Large)', '6pcs/pack', 280.00, 100, '', '/uploads/products/Asado_Siopao__A_Large_/product_image.png', '[[\"Minced Pork\", 480], [\"Soy Sauce\", 30], [\"Sugar\", 30], [\"Hoisin Sauce\", 18], [\"Star Anise\", 0.6], [\"Flour\", 600], [\"Yeast\", 6], [\"Milk\", 30], [\"Butter (Anchor)\", 18], [\"Baking Powder\", 6]]'),
(2, 'Siopao', 'Asado Siopao', 'Asado Siopao (A Med)', '10pcs/pack', 325.00, 100, '', '/uploads/products/Asado_Siopao__A_Med_/product_image.png', '[[\"Minced Pork\", 800], [\"Soy Sauce\", 50], [\"Sugar\", 50], [\"Hoisin Sauce\", 30], [\"Star Anise\", 1], [\"Flour\", 1000], [\"Yeast\", 10], [\"Milk\", 50], [\"Butter (Anchor)\", 30], [\"Baking Powder\", 10]]'),
(3, 'Siopao', 'Asado Siopao', 'Asado Siopao (A Small)', '15pcs/pack', 270.00, 100, '', '/uploads/products/Asado_Siopao__A_Small_/product_image.png', '[[\"Minced Pork\", 1200], [\"Soy Sauce\", 75], [\"Sugar\", 75], [\"Hoisin Sauce\", 45], [\"Star Anise\", 1.5], [\"Flour\", 1500], [\"Yeast\", 15], [\"Milk\", 75], [\"Butter (Anchor)\", 45], [\"Baking Powder\", 15]]'),
(4, 'Siopao', 'Asado Siopao', 'Asado Siopao (B Large)', '6pcs/pack', 235.00, 100, '', '/uploads/products/Asado_Siopao__B_Large_/product_image.png', '[[\"Minced Pork\", 480], [\"Soy Sauce\", 30], [\"Sugar\", 30], [\"Hoisin Sauce\", 18], [\"Star Anise\", 0.6], [\"Flour\", 600], [\"Yeast\", 6], [\"Milk\", 30], [\"Butter (Anchor)\", 18], [\"Baking Powder\", 6]]'),
(5, 'Siopao', 'Asado Siopao', 'Asado Siopao (B Med)', '10pcs/pack', 250.00, 100, '', '/uploads/products/Asado_Siopao__B_Med_/product_image.png', '[[\"Minced Pork\", 800], [\"Soy Sauce\", 50], [\"Sugar\", 50], [\"Hoisin Sauce\", 30], [\"Star Anise\", 1], [\"Flour\", 1000], [\"Yeast\", 10], [\"Milk\", 50], [\"Butter (Anchor)\", 30], [\"Baking Powder\", 10]]'),
(6, 'Siopao', 'Asado Siopao', 'Asado Siopao (B Small)', '15pcs/pack', 205.00, 100, '', '/uploads/products/Asado_Siopao__B_Small_/product_image.png', '[[\"Minced Pork\", 1200], [\"Soy Sauce\", 75], [\"Sugar\", 75], [\"Hoisin Sauce\", 45], [\"Star Anise\", 1.5], [\"Flour\", 1500], [\"Yeast\", 15], [\"Milk\", 75], [\"Butter (Anchor)\", 45], [\"Baking Powder\", 15]]'),
(7, 'Siopao', 'Bola Bola Siopao', 'Bola Bola Siopao (Large)', '6pcs/pack', 310.00, 100, '', '/uploads/products/Bola_Bola_Siopao__Large_/product_image.png', '[[\"Minced Pork\", 420], [\"Shrimp\", 60], [\"Soy Sauce\", 30], [\"Sugar\", 18], [\"Hoisin Sauce\", 18], [\"Flour\", 600], [\"Yeast\", 6], [\"Milk\", 30], [\"Butter (Anchor)\", 18], [\"Baking Powder\", 6]]'),
(8, 'Siopao', 'Bola Bola Siopao', 'Bola Bola Siopao (Med)', '10pcs/pack', 350.00, 100, '', '/uploads/products/Bola_Bola_Siopao__Med_/product_image.png', '[[\"Minced Pork\", 700], [\"Shrimp\", 100], [\"Soy Sauce\", 50], [\"Sugar\", 30], [\"Hoisin Sauce\", 30], [\"Flour\", 1000], [\"Yeast\", 10], [\"Milk\", 50], [\"Butter (Anchor)\", 30], [\"Baking Powder\", 10]]'),
(9, 'Siopao', 'Bola Bola Siopao', 'Bola Bola Siopao (Small)', '15pcs/pack', 290.00, 100, '', '/uploads/products/Bola_Bola_Siopao__Small_/product_image.png', '[[\"Minced Pork\", 1050], [\"Shrimp\", 150], [\"Soy Sauce\", 75], [\"Sugar\", 45], [\"Hoisin Sauce\", 45], [\"Flour\", 1500], [\"Yeast\", 15], [\"Milk\", 75], [\"Butter (Anchor)\", 45], [\"Baking Powder\", 15]]'),
(10, 'Siopao', 'Jumbo Pao', 'Jumbo Pao', '4pcs/pack', 325.00, 100, '', '/uploads/products/Jumbo_Pao/product_image.png', '[[\"Minced Pork\", 360], [\"Shrimp\", 40], [\"Sugar\", 20], [\"Soy Sauce\", 20], [\"Hoisin Sauce\", 20], [\"Flour\", 480], [\"Yeast\", 4.8], [\"Milk\", 24], [\"Butter (Anchor)\", 16], [\"Baking Powder\", 4]]'),
(11, 'Siopao', 'Cuaopao', 'Cuaopao', '10pcs/pack', 125.00, 0, '', '/uploads/products/Cuaopao/product_image.png', '[[\"Minced Pork\", 600], [\"Soy Sauce\", 40], [\"Sugar\", 30], [\"Hoisin Sauce\", 30], [\"Flour\", 900], [\"Yeast\", 10], [\"Milk\", 50], [\"Butter (Anchor)\", 30], [\"Baking Powder\", 10]]'),
(12, 'Siopao', 'Minibun Mantao', 'Minibun Mantao', '12pcs/pack', 115.00, 0, '', '/uploads/products/Minibun_Mantao/product_image.png', '[[\"Flour\", 840], [\"Yeast\", 12], [\"Milk\", 48], [\"Sugar\", 48], [\"Butter (Anchor)\", 24], [\"Baking Powder\", 12]]'),
(13, 'Siopao', 'Egg Custard Pao', 'Egg Custard Pao (Min 10 packs)', '8pcs/pack', 150.00, 0, '', '/uploads/products/Egg_Custard_Pao__Min_10_packs_/product_image.png', '[[\"Flour\", 640], [\"Milk\", 80], [\"Sugar\", 64], [\"Butter (Anchor)\", 32], [\"Yeast\", 8], [\"Baking Powder\", 8]]'),
(14, 'Dimsum & Dumplings', 'Regular Pork Siomai', 'Regular Pork Siomai', '30pcs/pack', 145.00, 0, '', '/uploads/products/Regular_Pork_Siomai/product_image.png', '[[\"Minced Pork\", 750], [\"Soy Sauce\", 30], [\"Sugar\", 15], [\"Flour\", 90], [\"Milk\", 6], [\"Butter (Anchor)\", 6]]'),
(15, 'Dimsum & Dumplings', 'Special Pork Siomai', 'Special Pork Siomai', '30pcs/pack', 240.00, 0, '', '/uploads/products/Special_Pork_Siomai/product_image.png', '[[\"Minced Pork\", 660], [\"Shrimp\", 90], [\"Soy Sauce\", 30], [\"Sugar\", 15], [\"Hoisin Sauce\", 15], [\"Flour\", 90], [\"Butter (Anchor)\", 9]]'),
(16, 'Dimsum & Dumplings', 'Regular Sharksfin Dumpling', 'Regular Sharksfin Dumpling', '30pcs/pack', 180.00, 0, '', '/uploads/products/Regular_Sharksfin_Dumpling/product_image.png', '[[\"Minced Pork\", 690], [\"Soy Sauce\", 30], [\"Sugar\", 15], [\"Flour\", 90], [\"Milk\", 9]]'),
(17, 'Dimsum & Dumplings', 'Special Sharksfin Dumpling', 'Special Sharksfin Dumpling', '30pcs/pack', 260.00, 0, '', '/uploads/products/Special_Sharksfin_Dumpling/product_image.png', '[[\"Minced Pork\", 630], [\"Shrimp\", 120], [\"Soy Sauce\", 30], [\"Hoisin Sauce\", 15], [\"Flour\", 90]]'),
(18, 'Dimsum & Dumplings', 'Kutchay Dumpling', 'Kutchay Dumpling', '30pcs/pack', 275.00, 0, '', '/uploads/products/Kutchay_Dumpling/product_image.png', '[[\"Minced Pork\", 600], [\"Soy Sauce\", 30], [\"Sugar\", 15], [\"Flour\", 90], [\"Butter (Anchor)\", 9]]'),
(19, 'Dimsum & Dumplings', 'Chicken Siomai', 'Chicken Siomai', '30pcs/pack', 300.00, 0, '0', '/uploads/products/Chicken_Siomai/product_image.png', '[[\"Chicken Diced Seasoned\", 690], [\"Soy Sauce\", 30], [\"Sugar\", 15], [\"Flour\", 90]]'),
(20, 'Dimsum & Dumplings', 'Beef Siomai', 'Beef Siomai', '20pcs/pack', 250.00, 0, '0', '/uploads/products/Beef_Siomai/product_image.png', '[[\"Beef Sliced Seasoned\", 500], [\"Soy Sauce\", 20], [\"Hoisin Sauce\", 10], [\"Flour\", 60], [\"Butter (Anchor)\", 4]]'),
(21, 'Dimsum & Dumplings', 'Premium Pork Siomai', 'Premium Pork Siomai (A)', '20pcs/pack', 280.00, 0, '', '/uploads/products/Premium_Pork_Siomai__A_/product_image.png', '[[\"Minced Pork\", 440], [\"Shrimp\", 60], [\"Soy Sauce\", 20], [\"Hoisin Sauce\", 10], [\"Flour\", 60]]'),
(22, 'Dimsum & Dumplings', 'Premium Pork Siomai w/ Shrimp', 'Premium Pork Siomai w/ Shrimp (A)', '20pcs/pack', 310.00, 0, '', '/uploads/products/Premium_Pork_Siomai_w__Shrimp__A_/product_image.png', '[[\"Minced Pork\", 400], [\"Shrimp\", 100], [\"Soy Sauce\", 20], [\"Hoisin Sauce\", 10], [\"Flour\", 60]]'),
(23, 'Dimsum & Dumplings', 'Premium Sharksfin Dumpling', 'Premium Sharksfin Dumpling', '20pcs/pack', 300.00, 0, '', '/uploads/products/Premium_Sharksfin_Dumpling/product_image.png', '[[\"Minced Pork\", 400], [\"Shrimp\", 100], [\"Soy Sauce\", 20], [\"Flour\", 60], [\"Butter (Anchor)\", 6]]'),
(24, 'Dimsum & Dumplings', 'Hakaw', 'Hakaw (Shrimp Dumpling) (A)', '12pcs/pack', 300.00, 0, '0', '/uploads/products/Hakaw__Shrimp_Dumpling___A_/product_image.png', '[[\"Shrimp\", 300], [\"Butter (Anchor)\", 3.6], [\"Flour\", 36], [\"Sugar\", 6]]'),
(25, 'Dimsum & Dumplings', 'Hakaw', 'Hakaw (Shrimp Dumpling) (B)', '20pcs/pack', 480.00, 0, '0', '/uploads/products/Hakaw__Shrimp_Dumpling___B_/product_image.png', '[[\"Shrimp\", 500], [\"Butter (Anchor)\", 6], [\"Flour\", 60], [\"Sugar\", 10]]'),
(26, 'Dimsum & Dumplings', 'Japanese Pork Siomai', 'Japanese Pork Siomai (A)', '20pcs/pack', 325.00, 0, '', '/uploads/products/Japanese_Pork_Siomai__A_/product_image.png', '[[\"Minced Pork\", 400], [\"Soy Sauce\", 20], [\"Japanese Soy Sauce\", 20], [\"Flour\", 60], [\"Sugar\", 10]]'),
(27, 'Dimsum & Dumplings', 'Polonchay Dumpling', 'Polonchay Dumpling (Min 6 Packs) (A)', '12pcs/pack', 310.00, 0, '', '/uploads/products/Polonchay_Dumpling__Min_6_Packs___A_/product_image.png', '[[\"Minced Pork\", 240], [\"Soy Sauce\", 12], [\"Flour\", 36]]'),
(28, 'Dimsum & Dumplings', 'Polonchay Dumpling', 'Polonchay Dumpling (Min 6 Packs) (B)', '20pcs/pack', 470.00, 0, '', '/uploads/products/Polonchay_Dumpling__Min_6_Packs___B_/product_image.png', '[[\"Minced Pork\", 400], [\"Soy Sauce\", 20], [\"Flour\", 60]]'),
(29, 'Dimsum & Dumplings', 'Polonchay Dumpling w/ Shrimp', 'Polonchay Dumpling w/ Shrimp (Min 6 Packs) (A)', '12pcs/pack', 330.00, 0, '', '/uploads/products/Polonchay_Dumpling_w__Shrimp__Min_6_Packs___A_/product_image.png', '[[\"Minced Pork\", 216], [\"Shrimp\", 48], [\"Soy Sauce\", 12], [\"Flour\", 36]]'),
(30, 'Dimsum & Dumplings', 'Polonchay Dumpling w/ Shrimp', 'Polonchay Dumpling w/ Shrimp (Min 6 Packs) (B)', '20pcs/pack', 530.00, 0, '', '/uploads/products/Polonchay_Dumpling_w__Shrimp__Min_6_Packs___B_/product_image.png', '[[\"Minced Pork\", 360], [\"Shrimp\", 80], [\"Soy Sauce\", 20], [\"Flour\", 60]]'),
(31, 'Dimsum & Dumplings', 'Beancurd Roll', 'Beancurd Roll (A)', '12pcs/pack', 310.00, 685, '0', '/uploads/products/Beancurd_Roll__A_/product_image.png', '[[\"Minced Pork\", 264], [\"Soy Sauce\", 12], [\"Sugar\", 6], [\"Veg. Spring Roll (Ham)\", 36]]'),
(32, 'Dimsum & Dumplings', 'Beancurd Roll', 'Beancurd Roll (B)', '20pcs/pack', 500.00, 0, '0', '/uploads/products/Beancurd_Roll__B_/product_image.png', '[[\"Minced Pork\", 440], [\"Soy Sauce\", 20], [\"Sugar\", 10], [\"Veg. Spring Roll (Ham)\", 60]]'),
(33, 'Dimsum & Dumplings', 'Pork Gyoza Dumpling', 'Pork Gyoza Dumpling (A)', '20pcs/pack', 390.00, 0, '', '/uploads/products/Pork_Gyoza_Dumpling__A_/product_image.png', '[[\"Minced Pork\", 440], [\"Soy Sauce\", 20], [\"Crispy Powder\", 40], [\"Flour\", 60]]'),
(34, 'Dimsum & Dumplings', 'Shanghai Dumpling', 'Shanghai Dumpling (A)', '20pcs/pack', 255.00, 0, '', '/uploads/products/Shanghai_Dumpling__A_/product_image.png', '[[\"Minced Pork\", 440], [\"Soy Sauce\", 20], [\"Sugar\", 10], [\"Flour\", 60]]'),
(35, 'Dimsum & Dumplings', 'Siao Long Pao', 'Siao Long Pao', '15pcs/pack', 270.00, 0, '', '/uploads/products/Siao_Long_Pao/product_image.png', '[[\"Minced Pork\", 375], [\"Soy Sauce\", 15], [\"Hoisin Sauce\", 7.5], [\"Flour\", 45]]'),
(36, 'Dimsum & Dumplings', 'Wanton Regular', 'Wanton Regular', '20pcs/pack', 315.00, 0, '', '/uploads/products/Wanton_Regular/product_image.png', '[[\"Minced Pork\", 400], [\"Soy Sauce\", 20], [\"Flour\", 60]]'),
(37, 'Dimsum & Dumplings', 'Sesame Butchi Ball', 'Sesame Butchi Ball', '12pcs/pack', 185.00, 0, '', '/uploads/products/Sesame_Butchi_Ball/product_image.png', '[[\"Glutinous Rice\", 300], [\"Sugar\", 60], [\"Dark Chocolate Bar\", 48], [\"Butter (Anchor)\", 12]]'),
(38, 'Dimsum & Dumplings', 'Machang', 'Machang (Hong Kong)', '6pcs/pack', 250.00, 0, '', '/uploads/products/Machang__Hong_Kong_/product_image.png', '[[\"Glutinous Rice\", 720], [\"Pork Belly (Skin On)\", 240], [\"Soy Sauce\", 18], [\"Chestnut\", 60]]'),
(39, 'Dimsum & Dumplings', 'Machang w/ Chestnut', 'Machang w/ Chestnut (Min 6 Packs)', '1pc', 110.00, 0, '', '/uploads/products/Machang_w__Chestnut__Min_6_Packs_/product_image.png', '[[\"Glutinous Rice\", 130], [\"Pork Belly (Skin On)\", 40], [\"Soy Sauce\", 3], [\"Chestnut\", 20]]'),
(40, 'Dimsum & Dumplings', 'Pork Rib Taosi', 'Pork Rib Taosi', '500g', 200.00, 0, '', '/uploads/products/Pork_Rib_Taosi/product_image.png', '[[\"Pork Ribs\", 500], [\"Soy Sauce\", 15], [\"Star Anise\", 5]]'),
(41, 'Dimsum & Dumplings', 'Pork Spring Roll', 'Pork Spring Roll', '20pcs/pack', 320.00, 0, '', '/uploads/products/Pork_Spring_Roll/product_image.png', '[[\"Minced Pork\", 360], [\"Veg. Spring Roll (Ham)\", 60], [\"Soy Sauce\", 20]]'),
(42, 'Dimsum & Dumplings', 'Chicken Feet', 'Chicken Feet', '500g', 200.00, 0, '0', '/uploads/products/Chicken_Feet/product_image.png', '[[\"Chicken Feet\", 500], [\"Soy Sauce\", 10], [\"Star Anise\", 5]]'),
(43, 'Dimsum & Dumplings', 'Radish Cake', 'Radish Cake 1.5kg', '1.5kg', 370.00, 0, '', '/uploads/products/Radish_Cake_1_5kg/product_image.png', '[[\"Minced Pork\", 300], [\"Flour\", 200], [\"Soy Sauce\", 30], [\"Sugar\", 20]]'),
(44, 'Dimsum & Dumplings', 'Radish Cake', 'Radish Cake 1kg', '1kg', 300.00, 0, '', '/uploads/products/Radish_Cake_1kg/product_image.png', '[[\"Minced Pork\", 200], [\"Flour\", 135], [\"Soy Sauce\", 20], [\"Sugar\", 13]]'),
(45, 'Dimsum & Dumplings', 'Pumpkin Cake', 'Pumpkin Cake 1.5kg', '1.5kg', 370.00, 0, '', '/uploads/products/Pumpkin_Cake_1_5kg/product_image.png', '[[\"Minced Pork\", 300], [\"Flour\", 200], [\"Sugar\", 30], [\"Butter (Anchor)\", 20]]'),
(46, 'Dimsum & Dumplings', 'Pumpkin Cake', 'Pumpkin Cake 1kg', '1kg', 300.00, 0, '', '/uploads/products/Pumpkin_Cake_1kg/product_image.png', '[[\"Minced Pork\", 200], [\"Flour\", 135], [\"Sugar\", 20], [\"Butter (Anchor)\", 13]]'),
(47, 'Healthy Dimsum', 'Vegetable Dumpling', 'Vegetable Dumpling (A)', '12pcs/pack', 190.00, 0, '', '/uploads/products/Vegetable_Dumpling__A_/product_image.png', '[[\"Veg. Spring Roll (Ham)\", 120], [\"Tofu\", 60], [\"Soy Sauce\", 12], [\"Flour\", 36]]'),
(48, 'Healthy Dimsum', 'Vegetable Dumpling', 'Vegetable Dumpling (B)', '20pcs/pack', 300.00, 0, '', '/uploads/products/Vegetable_Dumpling__B_/product_image.png', '[[\"Veg. Spring Roll (Ham)\", 200], [\"Tofu\", 100], [\"Soy Sauce\", 20], [\"Flour\", 60]]'),
(49, 'Healthy Dimsum', 'Vegetable Spring Roll', 'Vegetable Spring Roll (A)', '12pcs/pack', 230.00, 0, '', '/uploads/products/Vegetable_Spring_Roll__A_/product_image.png', '[[\"Veg. Spring Roll (Ham)\", 144], [\"Tofu\", 48], [\"Spring Roll Wrapper\", 12]]'),
(50, 'Healthy Dimsum', 'Vegetable Spring Roll', 'Vegetable Spring Roll (B)', '20pcs/pack', 360.00, 0, '', '/uploads/products/Vegetable_Spring_Roll__B_/product_image.png', '[[\"Veg. Spring Roll (Ham)\", 240], [\"Tofu\", 80], [\"Spring Roll Wrapper\", 20]]'),
(51, 'Sauces', 'Chili Sauce', 'Chili Sauce (A)', '1.5kg/cntr', 590.00, 0, '0', '/uploads/products/Chili_Sauce__A_/product_image.png', '[[\"Chili\", 750], [\"Garlic\", 300], [\"Vinegar\", 200], [\"Oil\", 150], [\"Sugar\", 50], [\"Salt\", 50]]'),
(52, 'Sauces', 'Chili Sauce', 'Chili Sauce (B)', '220g/btl', 160.00, 0, '', '/uploads/products/Chili_Sauce__B_/product_image.png', '[[\"Chili\", 110], [\"Garlic\", 44], [\"Vinegar\", 30], [\"Oil\", 22], [\"Sugar\", 7], [\"Salt\", 7]]'),
(53, 'Sauces', 'Seafood XO Sauce', 'Seafood XO Sauce', '220g/btl', 320.00, 0, '', '/uploads/products/Seafood_XO_Sauce/product_image.png', '[[\"Dried Japanese Scallop\", 40], [\"Dried Shrimp\", 40], [\"Garlic\", 30], [\"Chili\", 20], [\"Oil\", 60], [\"Soy Sauce\", 30]]'),
(54, 'Sauces', 'Lemon Sauce', 'Lemon Sauce (A)', '420g/btl', 135.00, 0, '', '/uploads/products/Lemon_Sauce__A_/product_image.png', '[[\"Lemon\", 200], [\"Sugar\", 100], [\"Cornstarch\", 60], [\"Vinegar\", 60]]'),
(55, 'Sauces', 'Sweet & Sour Sauce', 'Sweet & Sour Sauce (A)', '420g/btl', 135.00, 0, '', '/uploads/products/Sweet___Sour_Sauce__A_/product_image.png', '[[\"Ketchup\", 150], [\"Pineapple\", 100], [\"Vinegar\", 80], [\"Sugar\", 50], [\"Cornstarch\", 40]]'),
(56, 'Sauces', 'Beef Fillet Sauce', 'Beef Fillet Sauce', '420g/btl', 150.00, 0, '', '/uploads/products/Beef_Fillet_Sauce/product_image.png', '[[\"Soy Sauce\", 120], [\"Sugar\", 80], [\"Garlic\", 60], [\"Star Anise\", 20], [\"Cornstarch\", 140]]'),
(57, 'Sauces', 'Lemon Sauce', 'Lemon Sauce (B)', '3.5kg/Gal', 620.00, 0, '', '/uploads/products/Lemon_Sauce__B_/product_image.png', '[[\"Lemon\", 1660], [\"Sugar\", 830], [\"Cornstarch\", 500], [\"Vinegar\", 510]]'),
(58, 'Sauces', 'Sweet & Sour Sauce', 'Sweet & Sour Sauce (B)', '3.5kg/Gal', 620.00, 0, '', '/uploads/products/Sweet___Sour_Sauce__B_/product_image.png', '[[\"Ketchup\", 1250], [\"Pineapple\", 830], [\"Vinegar\", 700], [\"Sugar\", 420], [\"Cornstarch\", 300]]'),
(59, 'Marinated Items', 'Asado Marinated', 'Asado Marinated (Char Siu)', '1kg', 400.00, 0, '', '/uploads/products/Asado_Marinated__Char_Siu_/product_image.png', '[[\"Pork Belly (Skin On)\", 1000], [\"Soy Sauce\", 50], [\"Sugar\", 40], [\"Star Anise\", 10], [\"Sausana/Chinese\", 30]]'),
(60, 'Marinated Items', 'Asado Cooked', 'Asado Cooked (Char Siu)', '1kg', 700.00, 0, '', '/uploads/products/Asado_Cooked__Char_Siu_/product_image.png', '[[\"Pork Belly (Skin On)\", 1000], [\"Soy Sauce\", 50], [\"Sugar\", 40], [\"Star Anise\", 10], [\"Sausana/Chinese\", 30]]'),
(61, 'Noodles & Wrappers', 'Pancit Canton', 'Pancit Canton', '2kg/pack', 350.00, 0, '', '/uploads/products/Pancit_Canton/product_image.png', NULL),
(62, 'Noodles & Wrappers', 'Dried Egg Noodles', 'Dried Egg Noodles', '1kg/pack', 185.00, 0, '', '/uploads/products/Dried_Egg_Noodles/product_image.png', NULL),
(63, 'Noodles & Wrappers', 'Hongkong Noodles', 'Hongkong Noodles (Yellow/White)', '1kg/pack', 185.00, 0, '', '/uploads/products/Hongkong_Noodles__Yellow_White_/product_image.png', NULL),
(64, 'Noodles & Wrappers', 'Shanghai Noodles', 'Shanghai Noodles (Yellow/White)', '2kg/pack', 360.00, 0, '', '/uploads/products/Shanghai_Noodles__Yellow_White_/product_image.png', NULL),
(65, 'Noodles & Wrappers', 'Hofan Noodles', 'Hofan Noodles (Minimum 6 packs)', '1kg/pack', 170.00, 0, '', '/uploads/products/Hofan_Noodles__Minimum_6_packs_/product_image.png', NULL),
(66, 'Noodles & Wrappers', 'Ramen Noodles', 'Ramen Noodles', '1kg/pack', 195.00, 0, '', '/uploads/products/Ramen_Noodles/product_image.png', NULL),
(67, 'Noodles & Wrappers', 'Spinach Noodles', 'Spinach Noodles (Minimum 6 packs)', '1kg/pack', 195.00, 0, '', '/uploads/products/Spinach_Noodles__Minimum_6_packs_/product_image.png', NULL),
(68, 'Noodles & Wrappers', 'Siomai Wrapper', 'Siomai Wrapper (Yellow/White)', '250g/pack', 70.00, 0, '', '/uploads/products/Siomai_Wrapper__Yellow_White_/product_image.png', NULL),
(69, 'Noodles & Wrappers', 'Wanton Wrapper', 'Wanton Wrapper (Yellow/White)', '250g/pack', 70.00, 0, '', '/uploads/products/Wanton_Wrapper__Yellow_White_/product_image.png', NULL),
(70, 'Noodles & Wrappers', 'Beancurd Wrapper', 'Beancurd Wrapper', '1kg/pack', 1600.00, 0, '', '/uploads/products/Beancurd_Wrapper/product_image.png', NULL),
(71, 'Noodles & Wrappers', 'Spring Roll Wrapper', 'Spring Roll Wrapper', '25pcs/pack', 90.00, 0, '', '/uploads/products/Spring_Roll_Wrapper/product_image.png', NULL),
(72, 'Noodles & Wrappers', 'Gyoza Wrapper', 'Gyoza Wrapper (Minimum 10 Packs)', '250g/pack', 70.00, 0, '', '/uploads/products/Gyoza_Wrapper__Minimum_10_Packs_/product_image.png', NULL),
(82, 'Pork', 'Sisig', 'Sisig (Small)', '100g', 500.00, 0, '0', '/uploads/products/Sisig__Small_/product_image.png', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `raw_materials`
--

CREATE TABLE `raw_materials` (
  `material_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `stock_quantity` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Measured in grams',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `raw_materials`
--

INSERT INTO `raw_materials` (`material_id`, `name`, `stock_quantity`, `created_at`, `updated_at`) VALUES
(1, 'Chicken Meat', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(2, 'Chicken Feet', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(3, 'Chicken Breading', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(4, 'Chicken Diced Seasoned', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(5, 'Chicken Lemon Seasoned', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(6, 'Chicken Leg Boneless', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(7, 'Chicken Leg Quarter', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(8, 'Chicken Marinated', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(9, 'Chicken Sliced Seasoned', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(10, 'Whole Chicken', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(11, 'Peking Duck', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(12, 'Pork Meat', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(13, 'Pork Belly (Skin On)', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(14, 'Pork Belly (Skinless)', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(15, 'Pork Chop Marinated', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(16, 'Pork Fat', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(17, 'Pork Pigue', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(18, 'Pork Rib Spicy Seasoned', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(19, 'Pork Ribs', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(20, 'Pork Sliced Seasoned', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(21, 'Pork Sweet & Sour Seasoned', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(22, 'Porkloin', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(23, 'Pork Spareribs', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(24, 'Minced Pork', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(25, 'Siao Long Pao (Ham)', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(26, 'Wanton (Ham)', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(27, 'Veg. Spring Roll (Ham)', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(28, 'Smoked Ham', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(29, 'Hakaav (Ham)', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(30, 'Beef Meat', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(31, 'Beef Cube Roll', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(32, 'Beef Forequarter / Brisket', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(33, 'Beef Knuckle', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(34, 'Beef Rum', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(35, 'Beef Short Plates', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(36, 'Beef Sliced Seasoned', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(37, 'Beef Tenderloin Seasoned', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(38, 'Shrimp', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(39, 'Crabstick', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(40, 'Scallop', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(41, 'Dried Japanese Scallop', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(42, 'Salted Fish', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(43, 'Hibi', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(44, 'Ebito', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(45, 'Squid Cube', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(46, 'Giant Squid / Cuttlefish', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(47, 'Cuttlefish Seasoned', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(48, 'Fish Fillet Seasoned', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(49, 'Cream Dory Fillet', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(50, 'Cream Dory Fish Skin', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(51, 'Tai Tai Fish', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(52, 'Soy Sauce', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(53, 'Japanese Soy Sauce', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(54, 'Hoisin Sauce', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(55, 'Star Anise', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(56, 'Butter (Anchor)', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(57, 'Margarine Buttercup (Buttercup)', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(58, 'Cheese Quickmelt (Magnolia)', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(59, 'Cheese Unsalted (Magnolia)', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(60, 'Cheese (Eden)', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(61, 'Dark Chocolate Bar', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(62, 'Flour', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(63, 'Sugar', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(64, 'Yeast', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(65, 'Baking Powder', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(66, 'Milk', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(67, 'Glutinous Rice', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(68, 'Chestnut', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(69, 'Tofu', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(70, 'Chili', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(71, 'Garlic', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(72, 'Lemon', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(73, 'Pineapple', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(74, 'Cornstarch', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(75, 'Vinegar', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(76, 'Dried Shrimp', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(77, 'Ketchup', 0.00, '2025-04-07 16:57:58', '2025-04-07 16:57:58'),
(156, 'Oil', 0.00, '2025-04-07 17:57:32', '2025-04-07 17:57:32'),
(157, 'Salt', 0.00, '2025-04-07 17:57:32', '2025-04-07 17:57:32'),
(158, 'Sausana/Chinese', 0.00, '2025-04-07 17:57:32', '2025-04-07 17:57:32'),
(159, 'Crispy Powder', 0.00, '2025-04-07 17:57:32', '2025-04-07 17:57:32');

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
(1, 'Admin', 'active', 'Accounts - Admin, Accounts - Clients, Customers, Dashboard, User Roles, Inventory, Orders, Order History, Payment History, Forecast'),
(2, 'Manager', 'active', 'Accounts - Clients, Customers, Dashboard, Inventory, Order History, Orders, Payment History, Forecast'),
(3, 'Secretary', 'active', 'Customers, Dashboard, Inventory, Order History, Orders, Payment History'),
(4, 'Accountant', 'active', 'Dashboard, Order History, Orders, Payment History'),
(36, 'aed', 'active', 'Accounts - Admin, Accounts - Clients, Customers, Dashboard, Inventory, Order History, Orders, Payment History, User Roles');

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
-- Indexes for table `balance_history`
--
ALTER TABLE `balance_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `username` (`username`);

--
-- Indexes for table `clients_accounts`
--
ALTER TABLE `clients_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_username` (`username`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`);

--
-- Indexes for table `monthly_payments`
--
ALTER TABLE `monthly_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_month_year` (`username`,`month`,`year`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`);

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`page_id`),
  ADD UNIQUE KEY `page_name` (`page_name`);

--
-- Indexes for table `payment_history`
--
ALTER TABLE `payment_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `username` (`username`);

--
-- Indexes for table `payment_status_history`
--
ALTER TABLE `payment_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `username` (`username`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `raw_materials`
--
ALTER TABLE `raw_materials`
  ADD PRIMARY KEY (`material_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `balance_history`
--
ALTER TABLE `balance_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `clients_accounts`
--
ALTER TABLE `clients_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `monthly_payments`
--
ALTER TABLE `monthly_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=830;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `page_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `payment_history`
--
ALTER TABLE `payment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `payment_status_history`
--
ALTER TABLE `payment_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `raw_materials`
--
ALTER TABLE `raw_materials`
  MODIFY `material_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=160;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounts`
--
ALTER TABLE `accounts`
  ADD CONSTRAINT `fk_accounts_roles` FOREIGN KEY (`role`) REFERENCES `roles` (`role_name`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `balance_history`
--
ALTER TABLE `balance_history`
  ADD CONSTRAINT `balance_history_ibfk_1` FOREIGN KEY (`username`) REFERENCES `clients_accounts` (`username`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payment_history`
--
ALTER TABLE `payment_history`
  ADD CONSTRAINT `payment_history_ibfk_1` FOREIGN KEY (`username`) REFERENCES `clients_accounts` (`username`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payment_status_history`
--
ALTER TABLE `payment_status_history`
  ADD CONSTRAINT `payment_status_history_ibfk_1` FOREIGN KEY (`username`) REFERENCES `clients_accounts` (`username`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
