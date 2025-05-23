-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 30, 2025 at 12:31 PM
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
(56, 'admin', '123', '2025-03-05 22:58:17', 'Super Admin', 'Active', NULL, NULL),
(57, 'Secretary', '123', '2025-03-05 23:17:38', 'Secretary', 'Active', NULL, NULL),
(60, 'Manager', '123', '2025-03-05 23:27:49', 'Manager', 'Active', NULL, NULL),
(61, 'Accountant', '123', '2025-03-05 23:27:55', 'Accountant', 'Active', NULL, NULL),
(73, 'Owner', '123', '2025-04-29 17:09:36', 'Owner', 'Active', NULL, NULL);

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

--
-- Dumping data for table `balance_history`
--

INSERT INTO `balance_history` (`id`, `username`, `amount`, `notes`, `created_by`, `created_at`) VALUES
(6, 'Boters', 100000.00, '', 'Boters', '2025-04-25 03:03:36'),
(7, 'maamcristylen', 50000.00, '', 'system', '2025-04-26 13:36:17');

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
  `bill_to_address` varchar(255) DEFAULT NULL,
  `business_proof` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Active',
  `balance` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `client_session_id` varchar(255) DEFAULT NULL,
  `client_last_login` timestamp NULL DEFAULT NULL,
  `verification_code` varchar(6) DEFAULT NULL,
  `code_expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients_accounts`
--

INSERT INTO `clients_accounts` (`id`, `username`, `password`, `email`, `phone`, `region`, `city`, `company`, `company_address`, `bill_to_address`, `business_proof`, `status`, `balance`, `created_at`, `client_session_id`, `client_last_login`, `verification_code`, `code_expires_at`) VALUES
(1, 'sdfs45343', '$2y$10$yMz1VW2iD.xN9TU9jMJTmOVcdqNlDda3IbZERMv.yrRmquWqVUmoS', 'asdasdasdasaasdd@gmail.com', '123456789900', 'asdsdfsd', 'asdasdsa', 'sdfsdfsdf', 'asdasdas', 'dasdasdas', '[\"\\/uploads\\/sdfs45343\\/1.jpeg\"]', 'Pending', 0.00, '2025-04-29 17:02:00', NULL, NULL, NULL, NULL),
(17, 'aedantiu', '$2y$10$3sUyM1MUQZX6NdhA2MeFpOI1ZJNCoMlyew60g/SDJHxzjffsCjl7K', 'aedantiu@gmail.com', '0912345678', 'NCR', 'Quezon City', 'Top Exchange', 'asdasda', NULL, '[\"\\/uploads\\/aedantiu\\/67ea26c320cf1_BeefFilletSauce.png\"]', 'Active', 0.00, '2025-03-31 05:23:15', NULL, NULL, NULL, NULL),
(18, 'aedanpogi', '$2y$10$d4RGV6odNkDvDMCwI9NcjeLIhiLsdy8R8mffGUPMnWulbxrQhXYWy', 'aedanpogi@gmail.com', '09185585149', 'NCR', 'Quezon City', 'ABC Corp', '123', NULL, '[\"\\/uploads\\/aedanpogi\\/67ea344a30392_BeancurdRoll.png\",\"\\/uploads\\/aedanpogi\\/67ea344a30655_BeefFilletSauce.png\",\"\\/uploads\\/aedanpogi\\/67ea344a3083b_BeefSiomai.png\"]', 'Active', 0.00, '2025-03-31 06:20:58', NULL, NULL, NULL, NULL),
(19, 'Boters', '$2y$10$ZCuwFddafUAQjSSkGiW1u.fn3L5oomcUGr/scGq/7KP7gwHsJHJHa', 'jefferson45santonia@gmail.com', '09185585149', 'Metro Manila', 'Quezon City', '', '10 Aguinaldo Street', NULL, '[\"\\/uploads\\/Boters\\/RadishCake.png\"]', 'Active', 100000.00, '2025-04-17 05:58:45', NULL, NULL, NULL, NULL),
(20, 'Ryan', '$2y$10$j7do4aHoJGhm7q98GMYV8uLjw7bxz6kBYvsvVjoYR.giafGEXuLGi', 'ryanfrancisrodriguez02@gmail.com', '09154864843', 'NCR', 'Quezon City', 'STI College Munoz-EDSA', '1-B Palomaria Street Veterans Village Project 7 ggagagag', NULL, '[\"\\/uploads\\/Ryan\\/FlowChart.drawio.png\",\"\\/uploads\\/Ryan\\/6810f9847a324_PO_Boters-2.pdf\"]', 'Active', 0.00, '2025-04-17 12:14:15', NULL, NULL, NULL, NULL),
(21, 'maamcristylen', '$2y$10$TErfCLLXvaAqnh3FJKsz1uVQ/Z8/wphFZ1ySwr1ILXZIodEeQDyXC', 'esporsadocristylen09@gmail.com', '123456789101112', 'NCR', 'Quezon  City', 'STI College Munoz-EDSA', '123 STI College Munoz-EDSA', '', '[\"\\/uploads\\/maamcristylen\\/3bc67be0-fc06-4a8b-a735-667c6c34e800.jpeg\"]', 'Active', 29375.00, '2025-04-23 08:07:25', NULL, NULL, '184108', '2025-04-29 18:46:15'),
(22, 'Solaire', '$2y$10$fItuJjqrzbuunCvHMzOzHuIMNyQIvYxkD60ZJMVvnROrOOPdCaK9O', 'solairesolaire@gmail.com', '091720049875', 'NCR', 'Quezon City', 'Solaire', 'Solaire Solaire', NULL, '[\"\\/uploads\\/Solaire\\/youtube gray.png\"]', 'Pending', 0.00, '2025-04-25 03:34:42', NULL, NULL, NULL, NULL),
(23, 'panot', '$2y$10$lCwW1YIUROvCAhKQBqVlnOG4DFbwQKVaqPCPMYHgagGSaZllrhUSi', 'aslkdjaskldj@gmail.com', '', 'Metro Manila', 'Quezon City', 'Mama mo Panot International', '20 Panot St. Brgy. Mamamopanot', NULL, '[\"\\/uploads\\/panot\\/audience2.png\"]', 'Pending', 0.00, '2025-04-25 03:49:48', NULL, NULL, NULL, NULL),
(24, 'sdfsdfsd', '$2y$10$rKj8yZYFwOVKcFRTzz/0uOELVoRdSM8YC/hnT6TBDuZ2Zxawj/gIa', 'sdfsdfsdf@gmail.com', '908234892374', 'jskdhfjksdhf', 'kjhasdkjfhsdkjf', '', 'dfsdfsdfsdfsdfsd', NULL, '[\"\\/uploads\\/sdfsdfsd\\/e0671231-f16e-4dcb-b45c-f36ef525d9ef.jpeg\"]', 'Pending', 0.00, '2025-04-25 03:50:23', NULL, NULL, NULL, NULL),
(25, 'asjdhasasd', '$2y$10$dkRrX.PnwK1Ocuvd6ShOR.m0X90grzFibdUZZr9OsjfEM.cjdsGqW', 'aksjdhassdkjd@gmail.com', '123123123123', 'asdasd', 'asdasdas', 'dasdasd', 'asdasdasd', 'asdasdasd', '[\"\\/uploads\\/asjdhasasd\\/43d90c09-2421-4990-8042-ead7794f7f81.jpeg\"]', 'Pending', 0.00, '2025-04-29 17:04:03', NULL, NULL, NULL, NULL),
(26, 'asdasdasdasd', '$2y$10$8t9hRoilqYcxW/3/QQQpI.mEe5jINz0Y504AVz8ew1clAE5XJi6EK', 'asdasdasd@gasm.com', '123123123123', '123asdasd', 'asdasdasd', 'asdfsdf', 'azxdcasczxcz', 'asd vc bcvbvcb', '[\"\\/uploads\\/asdasdasdasd\\/1af6f75e-f679-4619-8184-bbd6edcbd60e.jpeg\"]', 'Pending', 0.00, '2025-04-29 17:04:26', NULL, NULL, NULL, NULL),
(27, 'test123', '$2y$10$Fbwb0AoIVR5bCQjd0xvVsueo/TaeF.1Mq2UykKjhY3583rKkGESpi', 'janariesimpuerto13@gmail.com', '', 'Metro Manila', 'Quezon City', '', 'asdasdkak', '', '[\"\\/uploads\\/test123\\/mama coco.png\"]', 'Active', 0.00, '2025-04-29 18:26:13', NULL, NULL, '045942', '2025-04-29 18:43:38'),
(28, 'monalizareyes', '$2y$10$vjzpkOZwCw9MRHPW6iKuEerhR21Kc7M.Qi0M0C00MREUA5AaJDURW', 'tiuaed1@gmail.com', '', 'NCR', 'Quezon City', 'Monaliza', 'STI Munoz 2873', '9273 Bill to Address', '[\"\\/uploads\\/monalizareyes\\/Untitled design (2).png\"]', 'Active', 0.00, '2025-04-30 03:00:13', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `contact_no` varchar(50) NOT NULL,
  `availability` enum('Available','Not Available') NOT NULL DEFAULT 'Available',
  `area` enum('North','South') NOT NULL DEFAULT 'North',
  `current_deliveries` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`id`, `name`, `address`, `contact_no`, `availability`, `area`, `current_deliveries`, `created_at`, `updated_at`) VALUES
(1, 'Manong Ryan', 'Mexico St.', '092893749823', 'Available', 'North', 5, '2025-04-25 04:19:29', '2025-04-30 08:26:50');

-- --------------------------------------------------------

--
-- Table structure for table `driver_assignments`
--

CREATE TABLE `driver_assignments` (
  `id` int(11) NOT NULL,
  `po_number` varchar(255) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Assigned','In Progress','Delivered') NOT NULL DEFAULT 'Assigned'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `driver_assignments`
--

INSERT INTO `driver_assignments` (`id`, `po_number`, `driver_id`, `assigned_at`, `status`) VALUES
(1, 'maamcristylen-3', 1, '2025-04-29 14:48:53', ''),
(2, 'maamcristylen-4', 1, '2025-04-29 15:26:56', ''),
(3, 'Ryan-1', 1, '2025-04-29 16:53:12', ''),
(4, 'Boters-1', 1, '2025-04-30 02:54:24', ''),
(5, 'monalizareyes-1', 1, '2025-04-30 03:08:12', 'Assigned'),
(7, 'monalizareyes-2', 1, '2025-04-30 08:26:50', 'Assigned');

-- --------------------------------------------------------

--
-- Table structure for table `driver_orders`
--

CREATE TABLE `driver_orders` (
  `id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `po_number` varchar(255) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_movements`
--

CREATE TABLE `inventory_movements` (
  `id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_type` enum('raw_material','finished_product') NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `po_number` varchar(50) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_movements`
--

INSERT INTO `inventory_movements` (`id`, `item_name`, `item_type`, `quantity`, `po_number`, `reason`, `user_id`, `timestamp`) VALUES
(1, 'Minced Pork', 'raw_material', 62100.00, 'monalizareyes-2', 'Manufacturing for Regular Sharksfin Dumpling', 'system', '2025-04-30 08:06:02'),
(2, 'Soy Sauce', 'raw_material', 2700.00, 'monalizareyes-2', 'Manufacturing for Regular Sharksfin Dumpling', 'system', '2025-04-30 08:06:02'),
(3, 'Sugar', 'raw_material', 1350.00, 'monalizareyes-2', 'Manufacturing for Regular Sharksfin Dumpling', 'system', '2025-04-30 08:06:02'),
(4, 'Flour', 'raw_material', 8100.00, 'monalizareyes-2', 'Manufacturing for Regular Sharksfin Dumpling', 'system', '2025-04-30 08:06:02'),
(5, 'Milk', 'raw_material', 810.00, 'monalizareyes-2', 'Manufacturing for Regular Sharksfin Dumpling', 'system', '2025-04-30 08:06:02'),
(6, 'Asado Siopao (B Large)', 'finished_product', 90.00, 'monalizareyes-2', 'Order Activated', 'system', '2025-04-30 08:06:02'),
(7, 'Minced Pork', 'raw_material', 62100.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:06:18'),
(8, 'Soy Sauce', 'raw_material', 2700.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:06:18'),
(9, 'Sugar', 'raw_material', 1350.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:06:18'),
(10, 'Flour', 'raw_material', 8100.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:06:18'),
(11, 'Milk', 'raw_material', 810.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:06:18'),
(12, 'Asado Siopao (B Large)', 'finished_product', 90.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:06:18'),
(13, 'Minced Pork', 'raw_material', 62100.00, 'monalizareyes-2', 'Manufacturing for Regular Sharksfin Dumpling', 'system', '2025-04-30 08:06:28'),
(14, 'Soy Sauce', 'raw_material', 2700.00, 'monalizareyes-2', 'Manufacturing for Regular Sharksfin Dumpling', 'system', '2025-04-30 08:06:28'),
(15, 'Sugar', 'raw_material', 1350.00, 'monalizareyes-2', 'Manufacturing for Regular Sharksfin Dumpling', 'system', '2025-04-30 08:06:28'),
(16, 'Flour', 'raw_material', 8100.00, 'monalizareyes-2', 'Manufacturing for Regular Sharksfin Dumpling', 'system', '2025-04-30 08:06:28'),
(17, 'Milk', 'raw_material', 810.00, 'monalizareyes-2', 'Manufacturing for Regular Sharksfin Dumpling', 'system', '2025-04-30 08:06:28'),
(18, 'Asado Siopao (B Large)', 'finished_product', 90.00, 'monalizareyes-2', 'Order Activated', 'system', '2025-04-30 08:06:28'),
(19, 'Minced Pork', 'raw_material', 62100.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:26:24'),
(20, 'Soy Sauce', 'raw_material', 2700.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:26:24'),
(21, 'Sugar', 'raw_material', 1350.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:26:24'),
(22, 'Flour', 'raw_material', 8100.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:26:24'),
(23, 'Milk', 'raw_material', 810.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:26:24'),
(24, 'Asado Siopao (B Large)', 'finished_product', 90.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:26:24'),
(25, 'Minced Pork', 'raw_material', 62100.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:26:24'),
(26, 'Soy Sauce', 'raw_material', 2700.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:26:24'),
(27, 'Sugar', 'raw_material', 1350.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:26:24'),
(28, 'Flour', 'raw_material', 8100.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:26:24'),
(29, 'Milk', 'raw_material', 810.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:26:24'),
(30, 'Asado Siopao (B Large)', 'finished_product', 90.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:26:24'),
(31, 'Minced Pork', 'raw_material', 62100.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:26:24'),
(32, 'Soy Sauce', 'raw_material', 2700.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:26:24'),
(33, 'Sugar', 'raw_material', 1350.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:26:24'),
(34, 'Flour', 'raw_material', 8100.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:26:24'),
(35, 'Milk', 'raw_material', 810.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:26:24'),
(36, 'Asado Siopao (B Large)', 'finished_product', 90.00, 'monalizareyes-2', 'Order Status Change Return', 'system', '2025-04-30 08:26:24'),
(37, 'Minced Pork', 'raw_material', 62100.00, 'monalizareyes-2', 'Manufacturing for Regular Sharksfin Dumpling', 'system', '2025-04-30 08:26:44'),
(38, 'Soy Sauce', 'raw_material', 2700.00, 'monalizareyes-2', 'Manufacturing for Regular Sharksfin Dumpling', 'system', '2025-04-30 08:26:44'),
(39, 'Sugar', 'raw_material', 1350.00, 'monalizareyes-2', 'Manufacturing for Regular Sharksfin Dumpling', 'system', '2025-04-30 08:26:44'),
(40, 'Flour', 'raw_material', 8100.00, 'monalizareyes-2', 'Manufacturing for Regular Sharksfin Dumpling', 'system', '2025-04-30 08:26:44'),
(41, 'Milk', 'raw_material', 810.00, 'monalizareyes-2', 'Manufacturing for Regular Sharksfin Dumpling', 'system', '2025-04-30 08:26:44'),
(42, 'Asado Siopao (B Large)', 'finished_product', 90.00, 'monalizareyes-2', 'Order Activated', 'system', '2025-04-30 08:26:44');

-- --------------------------------------------------------

--
-- Table structure for table `manufacturing_logs`
--

CREATE TABLE `manufacturing_logs` (
  `log_id` int(11) NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `manufacturing_logs`
--

INSERT INTO `manufacturing_logs` (`log_id`, `po_number`, `product_id`, `product_name`, `quantity`, `created_at`) VALUES
(1, 'Boters-2', 11, 'Cuaopao', 1, '2025-04-24 11:11:48'),
(2, 'Boters-2', 45, 'Pumpkin Cake 1.5kg', 4, '2025-04-24 11:11:48'),
(3, 'Boters-2', 43, 'Radish Cake 1.5kg', 1, '2025-04-24 11:11:48'),
(4, 'Boters-2', 12, 'Minibun Mantao', 1, '2025-04-25 04:32:41'),
(5, 'Boters-2', 18, 'Kutchay Dumpling', 1, '2025-04-25 04:32:41'),
(6, 'maamcristylen-1', 11, 'Cuaopao', 5, '2025-04-26 06:50:41'),
(7, 'maamcristylen-1', 12, 'Minibun Mantao', 5, '2025-04-26 06:50:41'),
(8, 'maamcristylen-1', 13, 'Egg Custard Pao (Min 10 packs)', 5, '2025-04-26 06:50:41'),
(9, 'maamcristylen-1', 14, 'Regular Pork Siomai', 5, '2025-04-26 06:50:41'),
(10, 'maamcristylen-1', 15, 'Special Pork Siomai', 5, '2025-04-26 06:50:41'),
(11, 'maamcristylen-1', 44, 'Radish Cake 1kg', 5, '2025-04-26 06:50:41'),
(12, 'maamcristylen-1', 45, 'Pumpkin Cake 1.5kg', 5, '2025-04-26 06:50:41'),
(13, 'maamcristylen-1', 46, 'Pumpkin Cake 1kg', 5, '2025-04-26 06:50:41'),
(14, 'maamcristylen-1', 47, 'Vegetable Dumpling (A)', 5, '2025-04-26 06:50:41'),
(15, 'maamcristylen-1', 48, 'Vegetable Dumpling (B)', 5, '2025-04-26 06:50:41'),
(16, 'monalizareyes-2', 16, 'Regular Sharksfin Dumpling', 90, '2025-04-30 08:06:02'),
(17, 'monalizareyes-2', 16, 'Regular Sharksfin Dumpling', 90, '2025-04-30 08:06:28'),
(18, 'monalizareyes-2', 16, 'Regular Sharksfin Dumpling', 90, '2025-04-30 08:26:44');

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

--
-- Dumping data for table `monthly_payments`
--

INSERT INTO `monthly_payments` (`id`, `username`, `month`, `year`, `total_amount`, `payment_status`, `created_at`, `updated_at`, `remaining_balance`, `proof_image`, `payment_method`, `payment_type`) VALUES
(1, 'maamcristylen', 4, 2025, 4180.00, 'Unpaid', '2025-04-29 16:56:14', '2025-04-29 16:56:26', 4180.00, NULL, NULL, NULL),
(830, 'aedanpogi', 1, 2025, 0.00, 'Fully Paid', '2025-04-24 02:11:24', '2025-04-24 02:11:24', 0.00, NULL, NULL, NULL),
(831, 'maamcristylen', 3, 2025, 0.00, 'Unpaid', '2025-04-26 13:35:12', '2025-04-29 13:21:00', 0.00, 'payment_1745674511.jpg', NULL, 'Internal');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `po_number` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `order_date` date NOT NULL,
  `delivery_date` date NOT NULL,
  `delivery_address` varchar(255) DEFAULT NULL,
  `orders` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`orders`)),
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('Pending','Active','For Delivery','In Transit','Rejected','Completed') NOT NULL DEFAULT 'Pending',
  `driver_assigned` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Flag to track if a driver has been assigned to this order',
  `contact_number` varchar(20) DEFAULT NULL,
  `special_instructions` text DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `progress` int(11) DEFAULT 0,
  `completed_items` longtext DEFAULT NULL,
  `item_progress_data` longtext DEFAULT NULL,
  `quantity_progress_data` longtext DEFAULT NULL,
  `item_progress_percentages` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `po_number`, `username`, `company`, `order_date`, `delivery_date`, `delivery_address`, `orders`, `total_amount`, `status`, `driver_assigned`, `contact_number`, `special_instructions`, `subtotal`, `progress`, `completed_items`, `item_progress_data`, `quantity_progress_data`, `item_progress_percentages`) VALUES
(100, 'maamcristylen-2', 'maamcristylen', NULL, '2025-04-28', '2025-04-30', '123 STI College Munoz-EDSA', '[{\"product_id\":1,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Large)\",\"packaging\":\"6pcs/pack\",\"price\":280,\"quantity\":5},{\"product_id\":2,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Med)\",\"packaging\":\"10pcs/pack\",\"price\":325,\"quantity\":5}]', 3025.00, 'Completed', 1, NULL, '55', 0.00, 100, '[0,1]', NULL, '[[true,true,true,true,true],[true,true,true,true,true]]', '[100,100]'),
(101, 'maamcristylen-3', 'maamcristylen', NULL, '2025-04-28', '2025-04-30', '123 STI College Munoz-EDSA', '[{\"product_id\":1,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Large)\",\"packaging\":\"6pcs/pack\",\"price\":280,\"quantity\":1}]', 280.00, 'Completed', 1, NULL, '', 0.00, 100, '[0]', NULL, '[[true]]', '[100]'),
(102, 'maamcristylen-4', 'maamcristylen', NULL, '2025-04-29', '2025-04-30', '123 STI College Munoz-EDSA', '[{\"product_id\":1,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Large)\",\"packaging\":\"6pcs/pack\",\"price\":280,\"quantity\":1},{\"product_id\":2,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Med)\",\"packaging\":\"10pcs/pack\",\"price\":325,\"quantity\":1},{\"product_id\":3,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Small)\",\"packaging\":\"15pcs/pack\",\"price\":270,\"quantity\":1}]', 875.00, 'Completed', 1, NULL, 'asdas', 0.00, 100, '[0,1,2]', NULL, '[[true],[true],[true]]', '[100,100,100]'),
(103, 'Boters-1', 'Boters', NULL, '2025-04-29', '2025-04-30', '10 Aguinaldo Street', '[{\"product_id\":1,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Large)\",\"packaging\":\"6pcs/pack\",\"price\":280,\"quantity\":5}]', 1400.00, 'Completed', 1, NULL, 'asdasdasdsa', 0.00, 100, '[0]', NULL, '[[true,true,true,true,true]]', '[100]'),
(104, 'aedanpogi-1', 'aedanpogi', NULL, '2025-04-29', '2025-04-30', '123', '[{\"product_id\":4,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (B Large)\",\"packaging\":\"6pcs/pack\",\"price\":235,\"quantity\":1},{\"product_id\":3,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Small)\",\"packaging\":\"15pcs/pack\",\"price\":270,\"quantity\":1},{\"product_id\":2,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Med)\",\"packaging\":\"10pcs/pack\",\"price\":325,\"quantity\":1},{\"product_id\":1,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Large)\",\"packaging\":\"6pcs/pack\",\"price\":280,\"quantity\":1}]', 1110.00, 'Pending', 0, NULL, 'dasfsdfdsf', 0.00, 0, NULL, NULL, NULL, NULL),
(105, 'Ryan-1', 'Ryan', NULL, '2025-04-29', '2025-04-30', '1-B Palomaria Street Veterans Village Project 7 ggagagag', '[{\"product_id\":4,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (B Large)\",\"packaging\":\"6pcs/pack\",\"price\":235,\"quantity\":2},{\"product_id\":3,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Small)\",\"packaging\":\"15pcs/pack\",\"price\":270,\"quantity\":2},{\"product_id\":2,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Med)\",\"packaging\":\"10pcs/pack\",\"price\":325,\"quantity\":2},{\"product_id\":1,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Large)\",\"packaging\":\"6pcs/pack\",\"price\":280,\"quantity\":2}]', 2220.00, 'Completed', 1, NULL, '', 0.00, 100, '[0,1,2,3]', NULL, '[[true,true],[true,true],[true,true],[true,true]]', '[100,100,100,100]'),
(106, 'monalizareyes-1', 'monalizareyes', NULL, '2025-04-30', '2025-05-02', 'STI Munoz 2873', '[{\"product_id\":2,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Med)\",\"packaging\":\"10pcs/pack\",\"price\":325,\"quantity\":200},{\"product_id\":1,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Large)\",\"packaging\":\"6pcs/pack\",\"price\":280,\"quantity\":5}]', 66400.00, 'Active', 1, NULL, '', 0.00, 0, NULL, NULL, NULL, NULL),
(107, 'monalizareyes-2', 'monalizareyes', NULL, '2025-04-30', '2025-05-07', 'STI Munoz 2873', '[{\"product_id\":16,\"category\":\"Dimsum & Dumplings\",\"item_description\":\"Regular Sharksfin Dumpling\",\"packaging\":\"30pcs/pack\",\"price\":180,\"quantity\":90},{\"product_id\":4,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (B Large)\",\"packaging\":\"6pcs/pack\",\"price\":235,\"quantity\":90}]', 37350.00, 'For Delivery', 1, NULL, '', 0.00, 100, '[0,1]', NULL, '[[true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true],[true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true,true]]', '[100,100]');

-- --------------------------------------------------------

--
-- Table structure for table `order_status_logs`
--

CREATE TABLE `order_status_logs` (
  `log_id` int(11) NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `old_status` varchar(20) NOT NULL,
  `new_status` varchar(20) NOT NULL,
  `changed_by` varchar(50) NOT NULL,
  `changed_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_status_logs`
--

INSERT INTO `order_status_logs` (`log_id`, `po_number`, `old_status`, `new_status`, `changed_by`, `changed_at`) VALUES
(1, 'maamcristylen-1', 'Pending', 'Active', 'system', '2025-04-28 14:27:22'),
(2, 'maamcristylen-2', 'Pending', 'Active', 'system', '2025-04-28 14:27:40'),
(3, 'maamcristylen-1', 'Active', 'For Delivery', 'system', '2025-04-28 15:11:08'),
(4, 'maamcristylen-3', 'Pending', 'Active', 'system', '2025-04-28 17:57:06'),
(5, 'maamcristylen-2', 'Active', 'For Delivery', 'system', '2025-04-29 13:08:07'),
(6, 'maamcristylen-1', 'For Delivery', 'Completed', 'system', '2025-04-29 13:08:21'),
(7, 'maamcristylen-2', 'For Delivery', 'Completed', 'system', '2025-04-29 13:37:37'),
(8, 'maamcristylen-3', 'Active', 'For Delivery', 'system', '2025-04-29 14:48:57'),
(9, 'maamcristylen-3', 'For Delivery', 'Completed', 'system', '2025-04-29 14:49:11'),
(10, 'maamcristylen-4', 'Pending', 'Active', 'system', '2025-04-29 15:26:35'),
(11, 'maamcristylen-4', 'Active', 'For Delivery', 'system', '2025-04-29 15:26:58'),
(12, 'maamcristylen-4', 'For Delivery', 'Completed', 'system', '2025-04-29 15:27:33'),
(13, 'Ryan-1', 'Pending', 'Active', 'system', '2025-04-29 16:52:59'),
(14, 'Ryan-1', 'Active', 'For Delivery', 'system', '2025-04-29 16:53:19'),
(15, 'Ryan-1', 'For Delivery', 'In Transit', 'system', '2025-04-29 16:53:43'),
(16, 'Ryan-1', 'In Transit', 'Completed', 'system', '2025-04-29 16:53:51'),
(17, 'aedanpogi-1', 'Pending', 'Rejected', 'system', '2025-04-29 17:37:19'),
(18, 'aedanpogi-1', 'Rejected', 'Pending', 'system', '2025-04-29 17:37:29'),
(19, 'Boters-1', 'Pending', 'Active', 'Ryan', '2025-04-30 02:54:10'),
(20, 'Boters-1', 'Active', 'For Delivery', 'Ryan', '2025-04-30 02:54:32'),
(21, 'Boters-1', 'In Transit', 'For Delivery', 'Ryan', '2025-04-30 02:55:15'),
(22, 'Boters-1', 'In Transit', 'For Delivery', 'Ryan', '2025-04-30 02:56:07'),
(23, 'Boters-1', 'In Transit', 'Completed', 'Ryan', '2025-04-30 02:56:17'),
(24, 'aedanpogi-1', 'Pending', 'Rejected', 'monalizareyes', '2025-04-30 03:05:47'),
(25, 'monalizareyes-1', 'Pending', 'Active', 'monalizareyes', '2025-04-30 03:08:00'),
(26, 'monalizareyes-2', 'Pending', 'Active', 'system', '2025-04-30 06:38:35'),
(27, 'monalizareyes-2', 'Active', 'Pending', 'system', '2025-04-30 06:38:42'),
(28, 'monalizareyes-2', 'Pending', 'Active', 'system', '2025-04-30 06:38:54'),
(29, 'monalizareyes-2', 'Active', 'Pending', 'system', '2025-04-30 06:38:58'),
(30, 'monalizareyes-2', 'Pending', 'Active', 'system', '2025-04-30 06:39:04'),
(31, 'monalizareyes-2', 'Active', 'Pending', 'system', '2025-04-30 06:39:11'),
(32, 'monalizareyes-2', 'Pending', 'Active', 'system', '2025-04-30 06:39:17'),
(33, 'monalizareyes-2', 'Active', 'Pending', 'system', '2025-04-30 06:40:02'),
(34, 'monalizareyes-2', 'Pending', 'Active', 'system', '2025-04-30 08:06:02'),
(35, 'monalizareyes-2', 'Active', 'Pending', 'system', '2025-04-30 08:06:18'),
(36, 'monalizareyes-2', 'Pending', 'Active', 'system', '2025-04-30 08:06:28'),
(37, 'monalizareyes-2', 'Active', 'Pending', 'system', '2025-04-30 08:26:24'),
(38, 'monalizareyes-2', 'Pending', 'Rejected', 'system', '2025-04-30 08:26:27'),
(39, 'monalizareyes-2', 'Rejected', 'Pending', 'system', '2025-04-30 08:26:31'),
(40, 'aedanpogi-1', 'Rejected', 'Pending', 'system', '2025-04-30 08:26:34'),
(41, 'monalizareyes-2', 'Pending', 'Active', 'system', '2025-04-30 08:26:44'),
(42, 'monalizareyes-2', 'Active', 'For Delivery', 'system', '2025-04-30 08:26:53');

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `page_id` int(11) NOT NULL,
  `page_name` varchar(50) NOT NULL,
  `file_path` varchar(100) NOT NULL,
  `module` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`page_id`, `page_name`, `file_path`, `module`) VALUES
(1, 'Accounts - Clients', 'accounts_clients.php', 'Accounts'),
(2, 'Accounts - Admin', 'accounts.php', 'Accounts'),
(4, 'Dashboard', 'dashboard.php', 'Dashboard'),
(5, 'Inventory', 'inventory.php', 'Inventory'),
(6, 'User Roles', 'user_roles.php', 'Accounts'),
(7, 'Orders', 'orders.php', 'Ordering'),
(8, 'Order History', 'order_history.php', 'Ordering'),
(9, 'Payment History', 'payment_history.php', 'Payments'),
(12, 'Raw Materials', 'raw_materials.php', 'Inventory'),
(14, 'Department Forecast', 'department_forecast.php', 'Production'),
(17, 'Drivers', 'drivers.php', 'Staff'),
(19, 'Deliverable Orders', 'deliverable_orders.php', 'Ordering'),
(23, 'Forecast', 'forecast.php', 'Production');

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

--
-- Dumping data for table `payment_history`
--

INSERT INTO `payment_history` (`id`, `username`, `month`, `year`, `amount`, `notes`, `proof_image`, `created_by`, `created_at`, `payment_type`) VALUES
(23, 'maamcristylen', 3, 2025, 20625.00, NULL, 'payment_1745674511.jpg', 'system', '2025-04-26 13:35:12', 'External'),
(24, 'maamcristylen', 3, 2025, 10000.00, NULL, NULL, 'system', '2025-04-26 13:36:34', 'Internal'),
(25, 'maamcristylen', 3, 2025, 10625.00, NULL, NULL, 'system', '2025-04-26 13:36:46', 'Internal');

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

--
-- Dumping data for table `payment_status_history`
--

INSERT INTO `payment_status_history` (`id`, `username`, `month`, `year`, `old_status`, `new_status`, `changed_by`, `changed_at`) VALUES
(17, 'aedanpogi', 1, 2025, 'Unpaid', 'Fully Paid', 'system', '2025-04-24 02:11:24'),
(18, 'maamcristylen', 3, 2025, 'For Approval', 'Fully Paid', 'system', '2025-04-26 13:35:54'),
(19, 'maamcristylen', 3, 2025, 'Fully Paid', 'Unpaid', 'system', '2025-04-26 13:36:20'),
(20, 'maamcristylen', 3, 2025, 'Fully Paid', 'Unpaid', 'system', '2025-04-29 12:48:30'),
(21, 'maamcristylen', 4, 2025, 'Unpaid', 'Fully Paid', 'system', '2025-04-29 16:56:14'),
(22, 'maamcristylen', 4, 2025, 'Fully Paid', 'Unpaid', 'system', '2025-04-29 16:56:26');

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
(11, 'Siopao', 'Cuaopao', 'Cuaopao', '10pcs/pack', 125.00, 100, '', '/uploads/products/Cuaopao/product_image.png', '[[\"Minced Pork\", 600], [\"Soy Sauce\", 40], [\"Sugar\", 30], [\"Hoisin Sauce\", 30], [\"Flour\", 900], [\"Yeast\", 10], [\"Milk\", 50], [\"Butter (Anchor)\", 30], [\"Baking Powder\", 10]]'),
(12, 'Siopao', 'Minibun Mantao', 'Minibun Mantao', '12pcs/pack', 115.00, 100, '', '/uploads/products/Minibun_Mantao/product_image.png', '[[\"Flour\", 840], [\"Yeast\", 12], [\"Milk\", 48], [\"Sugar\", 48], [\"Butter (Anchor)\", 24], [\"Baking Powder\", 12]]'),
(13, 'Siopao', 'Egg Custard Pao', 'Egg Custard Pao (Min 10 packs)', '8pcs/pack', 150.00, 100, '', '/uploads/products/Egg_Custard_Pao__Min_10_packs_/product_image.png', '[[\"Flour\", 640], [\"Milk\", 80], [\"Sugar\", 64], [\"Butter (Anchor)\", 32], [\"Yeast\", 8], [\"Baking Powder\", 8]]'),
(14, 'Dimsum & Dumplings', 'Regular Pork Siomai', 'Regular Pork Siomai', '30pcs/pack', 145.00, 100, '', '/uploads/products/Regular_Pork_Siomai/product_image.png', '[[\"Minced Pork\", 750], [\"Soy Sauce\", 30], [\"Sugar\", 15], [\"Flour\", 90], [\"Milk\", 6], [\"Butter (Anchor)\", 6]]'),
(15, 'Dimsum & Dumplings', 'Special Pork Siomai', 'Special Pork Siomai', '30pcs/pack', 240.00, 100, '', '/uploads/products/Special_Pork_Siomai/product_image.png', '[[\"Minced Pork\", 660], [\"Shrimp\", 90], [\"Soy Sauce\", 30], [\"Sugar\", 15], [\"Hoisin Sauce\", 15], [\"Flour\", 90], [\"Butter (Anchor)\", 9]]'),
(16, 'Dimsum & Dumplings', 'Regular Sharksfin Dumpling', 'Regular Sharksfin Dumpling', '30pcs/pack', 180.00, 100, '', '/uploads/products/Regular_Sharksfin_Dumpling/product_image.png', '[[\"Minced Pork\", 690], [\"Soy Sauce\", 30], [\"Sugar\", 15], [\"Flour\", 90], [\"Milk\", 9]]'),
(17, 'Dimsum & Dumplings', 'Special Sharksfin Dumpling', 'Special Sharksfin Dumpling', '30pcs/pack', 260.00, 100, '', '/uploads/products/Special_Sharksfin_Dumpling/product_image.png', '[[\"Minced Pork\", 630], [\"Shrimp\", 120], [\"Soy Sauce\", 30], [\"Hoisin Sauce\", 15], [\"Flour\", 90]]'),
(18, 'Dimsum & Dumplings', 'Kutchay Dumpling', 'Kutchay Dumpling', '30pcs/pack', 275.00, 100, '', '/uploads/products/Kutchay_Dumpling/product_image.png', '[[\"Minced Pork\", 600], [\"Soy Sauce\", 30], [\"Sugar\", 15], [\"Flour\", 90], [\"Butter (Anchor)\", 9]]'),
(19, 'Dimsum & Dumplings', 'Chicken Siomai', 'Chicken Siomai', '30pcs/pack', 300.00, 100, '', '/uploads/products/Chicken_Siomai/product_image.png', '[[\"Chicken Diced Seasoned\", 690], [\"Soy Sauce\", 30], [\"Sugar\", 15], [\"Flour\", 90]]'),
(20, 'Dimsum & Dumplings', 'Beef Siomai', 'Beef Siomai', '20pcs/pack', 250.00, 100, '', '/uploads/products/Beef_Siomai/product_image.png', '[[\"Beef Sliced Seasoned\", 500], [\"Soy Sauce\", 20], [\"Hoisin Sauce\", 10], [\"Flour\", 60], [\"Butter (Anchor)\", 4]]'),
(21, 'Dimsum & Dumplings', 'Premium Pork Siomai', 'Premium Pork Siomai (A)', '20pcs/pack', 280.00, 100, '', '/uploads/products/Premium_Pork_Siomai__A_/product_image.png', '[[\"Minced Pork\", 440], [\"Shrimp\", 60], [\"Soy Sauce\", 20], [\"Hoisin Sauce\", 10], [\"Flour\", 60]]'),
(22, 'Dimsum & Dumplings', 'Premium Pork Siomai w/ Shrimp', 'Premium Pork Siomai w/ Shrimp (A)', '20pcs/pack', 310.00, 100, '', '/uploads/products/Premium_Pork_Siomai_w__Shrimp__A_/product_image.png', '[[\"Minced Pork\", 400], [\"Shrimp\", 100], [\"Soy Sauce\", 20], [\"Hoisin Sauce\", 10], [\"Flour\", 60]]'),
(23, 'Dimsum & Dumplings', 'Premium Sharksfin Dumpling', 'Premium Sharksfin Dumpling', '20pcs/pack', 300.00, 100, '', '/uploads/products/Premium_Sharksfin_Dumpling/product_image.png', '[[\"Minced Pork\", 400], [\"Shrimp\", 100], [\"Soy Sauce\", 20], [\"Flour\", 60], [\"Butter (Anchor)\", 6]]'),
(24, 'Dimsum & Dumplings', 'Hakaw', 'Hakaw (Shrimp Dumpling) (A)', '12pcs/pack', 300.00, 100, '', '/uploads/products/Hakaw__Shrimp_Dumpling___A_/product_image.png', '[[\"Shrimp\", 300], [\"Butter (Anchor)\", 3.6], [\"Flour\", 36], [\"Sugar\", 6]]'),
(25, 'Dimsum & Dumplings', 'Hakaw', 'Hakaw (Shrimp Dumpling) (B)', '20pcs/pack', 480.00, 100, '', '/uploads/products/Hakaw__Shrimp_Dumpling___B_/product_image.png', '[[\"Shrimp\", 500], [\"Butter (Anchor)\", 6], [\"Flour\", 60], [\"Sugar\", 10]]'),
(26, 'Dimsum & Dumplings', 'Japanese Pork Siomai', 'Japanese Pork Siomai (A)', '20pcs/pack', 325.00, 100, '', '/uploads/products/Japanese_Pork_Siomai__A_/product_image.png', '[[\"Minced Pork\", 400], [\"Soy Sauce\", 20], [\"Japanese Soy Sauce\", 20], [\"Flour\", 60], [\"Sugar\", 10]]'),
(27, 'Dimsum & Dumplings', 'Polonchay Dumpling', 'Polonchay Dumpling (Min 6 Packs) (A)', '12pcs/pack', 310.00, 100, '', '/uploads/products/Polonchay_Dumpling__Min_6_Packs___A_/product_image.png', '[[\"Minced Pork\", 240], [\"Soy Sauce\", 12], [\"Flour\", 36]]'),
(28, 'Dimsum & Dumplings', 'Polonchay Dumpling', 'Polonchay Dumpling (Min 6 Packs) (B)', '20pcs/pack', 470.00, 100, '', '/uploads/products/Polonchay_Dumpling__Min_6_Packs___B_/product_image.png', '[[\"Minced Pork\", 400], [\"Soy Sauce\", 20], [\"Flour\", 60]]'),
(29, 'Dimsum & Dumplings', 'Polonchay Dumpling w/ Shrimp', 'Polonchay Dumpling w/ Shrimp (Min 6 Packs) (A)', '12pcs/pack', 330.00, 100, '', '/uploads/products/Polonchay_Dumpling_w__Shrimp__Min_6_Packs___A_/product_image.png', '[[\"Minced Pork\", 216], [\"Shrimp\", 48], [\"Soy Sauce\", 12], [\"Flour\", 36]]'),
(30, 'Dimsum & Dumplings', 'Polonchay Dumpling w/ Shrimp', 'Polonchay Dumpling w/ Shrimp (Min 6 Packs) (B)', '20pcs/pack', 530.00, 100, '', '/uploads/products/Polonchay_Dumpling_w__Shrimp__Min_6_Packs___B_/product_image.png', '[[\"Minced Pork\", 360], [\"Shrimp\", 80], [\"Soy Sauce\", 20], [\"Flour\", 60]]'),
(31, 'Dimsum & Dumplings', 'Beancurd Roll', 'Beancurd Roll (A)', '12pcs/pack', 310.00, 100, '', '/uploads/products/Beancurd_Roll__A_/product_image.png', '[[\"Minced Pork\",264],[\"Soy Sauce\",12],[\"Sugar\",6],[\"Veg. Spring Roll (Ham)\",36]]'),
(32, 'Dimsum & Dumplings', 'Beancurd Roll', 'Beancurd Roll (B)', '20pcs/pack', 500.00, 100, '', '/uploads/products/Beancurd_Roll__B_/product_image.png', '[[\"Minced Pork\", 440], [\"Soy Sauce\", 20], [\"Sugar\", 10], [\"Veg. Spring Roll (Ham)\", 60]]'),
(33, 'Dimsum & Dumplings', 'Pork Gyoza Dumpling', 'Pork Gyoza Dumpling (A)', '20pcs/pack', 390.00, 100, '', '/uploads/products/Pork_Gyoza_Dumpling__A_/product_image.png', '[[\"Minced Pork\", 440], [\"Soy Sauce\", 20], [\"Crispy Powder\", 40], [\"Flour\", 60]]'),
(34, 'Dimsum & Dumplings', 'Shanghai Dumpling', 'Shanghai Dumpling (A)', '20pcs/pack', 255.00, 100, '', '/uploads/products/Shanghai_Dumpling__A_/product_image.png', '[[\"Minced Pork\", 440], [\"Soy Sauce\", 20], [\"Sugar\", 10], [\"Flour\", 60]]'),
(35, 'Dimsum & Dumplings', 'Siao Long Pao', 'Siao Long Pao', '15pcs/pack', 270.00, 100, '', '/uploads/products/Siao_Long_Pao/product_image.png', '[[\"Minced Pork\", 375], [\"Soy Sauce\", 15], [\"Hoisin Sauce\", 7.5], [\"Flour\", 45]]'),
(36, 'Dimsum & Dumplings', 'Wanton Regular', 'Wanton Regular', '20pcs/pack', 315.00, 100, '', '/uploads/products/Wanton_Regular/product_image.png', '[[\"Minced Pork\", 400], [\"Soy Sauce\", 20], [\"Flour\", 60]]'),
(37, 'Dimsum & Dumplings', 'Sesame Butchi Ball', 'Sesame Butchi Ball', '12pcs/pack', 185.00, 100, '', '/uploads/products/Sesame_Butchi_Ball/product_image.png', '[[\"Glutinous Rice\", 300], [\"Sugar\", 60], [\"Dark Chocolate Bar\", 48], [\"Butter (Anchor)\", 12]]'),
(38, 'Dimsum & Dumplings', 'Machang', 'Machang (Hong Kong)', '6pcs/pack', 250.00, 100, '', '/uploads/products/Machang__Hong_Kong_/product_image.png', '[[\"Glutinous Rice\", 720], [\"Pork Belly (Skin On)\", 240], [\"Soy Sauce\", 18], [\"Chestnut\", 60]]'),
(39, 'Dimsum & Dumplings', 'Machang w/ Chestnut', 'Machang w/ Chestnut (Min 6 Packs)', '1pc', 110.00, 100, '', '/uploads/products/Machang_w__Chestnut__Min_6_Packs_/product_image.png', '[[\"Glutinous Rice\", 130], [\"Pork Belly (Skin On)\", 40], [\"Soy Sauce\", 3], [\"Chestnut\", 20]]'),
(40, 'Dimsum & Dumplings', 'Pork Rib Taosi', 'Pork Rib Taosi', '500g', 200.00, 100, '', '/uploads/products/Pork_Rib_Taosi/product_image.png', '[[\"Pork Ribs\", 500], [\"Soy Sauce\", 15], [\"Star Anise\", 5]]'),
(41, 'Dimsum & Dumplings', 'Pork Spring Roll', 'Pork Spring Roll', '20pcs/pack', 320.00, 100, '', '/uploads/products/Pork_Spring_Roll/product_image.png', '[[\"Minced Pork\", 360], [\"Veg. Spring Roll (Ham)\", 60], [\"Soy Sauce\", 20]]'),
(42, 'Dimsum & Dumplings', 'Chicken Feet', 'Chicken Feet', '500g', 200.00, 100, '', '/uploads/products/Chicken_Feet/product_image.png', '[[\"Chicken Feet\", 500], [\"Soy Sauce\", 10], [\"Star Anise\", 5]]'),
(43, 'Dimsum & Dumplings', 'Radish Cake', 'Radish Cake 1.5kg', '1.5kg', 370.00, 100, '', '/uploads/products/Radish_Cake_1_5kg/product_image.png', '[[\"Minced Pork\", 300], [\"Flour\", 200], [\"Soy Sauce\", 30], [\"Sugar\", 20]]'),
(44, 'Dimsum & Dumplings', 'Radish Cake', 'Radish Cake 1kg', '1kg', 300.00, 100, '', '/uploads/products/Radish_Cake_1kg/product_image.png', '[[\"Minced Pork\", 200], [\"Flour\", 135], [\"Soy Sauce\", 20], [\"Sugar\", 13]]'),
(45, 'Dimsum & Dumplings', 'Pumpkin Cake', 'Pumpkin Cake 1.5kg', '1.5kg', 370.00, 100, '', '/uploads/products/Pumpkin_Cake_1_5kg/product_image.png', '[[\"Minced Pork\", 300], [\"Flour\", 200], [\"Sugar\", 30], [\"Butter (Anchor)\", 20]]'),
(46, 'Dimsum & Dumplings', 'Pumpkin Cake', 'Pumpkin Cake 1kg', '1kg', 300.00, 100, '', '/uploads/products/Pumpkin_Cake_1kg/product_image.png', '[[\"Minced Pork\", 200], [\"Flour\", 135], [\"Sugar\", 20], [\"Butter (Anchor)\", 13]]'),
(47, 'Healthy Dimsum', 'Vegetable Dumpling', 'Vegetable Dumpling (A)', '12pcs/pack', 190.00, 100, '', '/uploads/products/Vegetable_Dumpling__A_/product_image.png', '[[\"Veg. Spring Roll (Ham)\", 120], [\"Tofu\", 60], [\"Soy Sauce\", 12], [\"Flour\", 36]]'),
(48, 'Healthy Dimsum', 'Vegetable Dumpling', 'Vegetable Dumpling (B)', '20pcs/pack', 300.00, 100, '', '/uploads/products/Vegetable_Dumpling__B_/product_image.png', '[[\"Veg. Spring Roll (Ham)\", 200], [\"Tofu\", 100], [\"Soy Sauce\", 20], [\"Flour\", 60]]'),
(49, 'Healthy Dimsum', 'Vegetable Spring Roll', 'Vegetable Spring Roll (A)', '12pcs/pack', 230.00, 100, '', '/uploads/products/Vegetable_Spring_Roll__A_/product_image.png', '[[\"Veg. Spring Roll (Ham)\", 144], [\"Tofu\", 48], [\"Spring Roll Wrapper\", 12]]'),
(50, 'Healthy Dimsum', 'Vegetable Spring Roll', 'Vegetable Spring Roll (B)', '20pcs/pack', 360.00, 100, '', '/uploads/products/Vegetable_Spring_Roll__B_/product_image.png', '[[\"Veg. Spring Roll (Ham)\", 240], [\"Tofu\", 80], [\"Spring Roll Wrapper\", 20]]'),
(51, 'Sauces', 'Chili Sauce', 'Chili Sauce (A)', '1.5kg/cntr', 590.00, 100, '', '/uploads/products/Chili_Sauce__A_/product_image.png', '[[\"Chili\", 750], [\"Garlic\", 300], [\"Vinegar\", 200], [\"Oil\", 150], [\"Sugar\", 50], [\"Salt\", 50]]'),
(52, 'Sauces', 'Chili Sauce', 'Chili Sauce (B)', '220g/btl', 160.00, 100, '', '/uploads/products/Chili_Sauce__B_/product_image.png', '[[\"Chili\", 110], [\"Garlic\", 44], [\"Vinegar\", 30], [\"Oil\", 22], [\"Sugar\", 7], [\"Salt\", 7]]'),
(53, 'Sauces', 'Seafood XO Sauce', 'Seafood XO Sauce', '220g/btl', 320.00, 100, '', '/uploads/products/Seafood_XO_Sauce/product_image.png', '[[\"Dried Japanese Scallop\", 40], [\"Dried Shrimp\", 40], [\"Garlic\", 30], [\"Chili\", 20], [\"Oil\", 60], [\"Soy Sauce\", 30]]'),
(54, 'Sauces', 'Lemon Sauce', 'Lemon Sauce (A)', '420g/btl', 135.00, 100, '', '/uploads/products/Lemon_Sauce__A_/product_image.png', '[[\"Lemon\", 200], [\"Sugar\", 100], [\"Cornstarch\", 60], [\"Vinegar\", 60]]'),
(55, 'Sauces', 'Sweet & Sour Sauce', 'Sweet & Sour Sauce (A)', '420g/btl', 135.00, 100, '', '/uploads/products/Sweet___Sour_Sauce__A_/product_image.png', '[[\"Ketchup\", 150], [\"Pineapple\", 100], [\"Vinegar\", 80], [\"Sugar\", 50], [\"Cornstarch\", 40]]'),
(56, 'Sauces', 'Beef Fillet Sauce', 'Beef Fillet Sauce', '420g/btl', 150.00, 100, '', '/uploads/products/Beef_Fillet_Sauce/product_image.png', '[[\"Soy Sauce\", 120], [\"Sugar\", 80], [\"Garlic\", 60], [\"Star Anise\", 20], [\"Cornstarch\", 140]]'),
(57, 'Sauces', 'Lemon Sauce', 'Lemon Sauce (B)', '3.5kg/Gal', 620.00, 100, '', '/uploads/products/Lemon_Sauce__B_/product_image.png', '[[\"Lemon\", 1660], [\"Sugar\", 830], [\"Cornstarch\", 500], [\"Vinegar\", 510]]'),
(58, 'Sauces', 'Sweet & Sour Sauce', 'Sweet & Sour Sauce (B)', '3.5kg/Gal', 620.00, 100, '', '/uploads/products/Sweet___Sour_Sauce__B_/product_image.png', '[[\"Ketchup\", 1250], [\"Pineapple\", 830], [\"Vinegar\", 700], [\"Sugar\", 420], [\"Cornstarch\", 300]]'),
(59, 'Marinated Items', 'Asado Marinated', 'Asado Marinated (Char Siu)', '1kg', 400.00, 100, '', '/uploads/products/Asado_Marinated__Char_Siu_/product_image.png', '[[\"Pork Belly (Skin On)\", 1000], [\"Soy Sauce\", 50], [\"Sugar\", 40], [\"Star Anise\", 10], [\"Sausana/Chinese\", 30]]'),
(60, 'Marinated Items', 'Asado Cooked', 'Asado Cooked (Char Siu)', '1kg', 700.00, 100, '', '/uploads/products/Asado_Cooked__Char_Siu_/product_image.png', '[[\"Pork Belly (Skin On)\", 1000], [\"Soy Sauce\", 50], [\"Sugar\", 40], [\"Star Anise\", 10], [\"Sausana/Chinese\", 30]]'),
(61, 'Noodles & Wrappers', 'Pancit Canton', 'Pancit Canton', '2kg/pack', 350.00, 100, '', '/uploads/products/Pancit_Canton/product_image.png', NULL),
(62, 'Noodles & Wrappers', 'Dried Egg Noodles', 'Dried Egg Noodles', '1kg/pack', 185.00, 100, '', '/uploads/products/Dried_Egg_Noodles/product_image.png', NULL),
(63, 'Noodles & Wrappers', 'Hongkong Noodles', 'Hongkong Noodles (Yellow/White)', '1kg/pack', 185.00, 100, '', '/uploads/products/Hongkong_Noodles__Yellow_White_/product_image.png', NULL),
(64, 'Noodles & Wrappers', 'Shanghai Noodles', 'Shanghai Noodles (Yellow/White)', '2kg/pack', 360.00, 100, '', '/uploads/products/Shanghai_Noodles__Yellow_White_/product_image.png', NULL),
(65, 'Noodles & Wrappers', 'Hofan Noodles', 'Hofan Noodles (Minimum 6 packs)', '1kg/pack', 170.00, 100, '', '/uploads/products/Hofan_Noodles__Minimum_6_packs_/product_image.png', NULL),
(66, 'Noodles & Wrappers', 'Ramen Noodles', 'Ramen Noodles', '1kg/pack', 195.00, 100, '', '/uploads/products/Ramen_Noodles/product_image.png', NULL),
(67, 'Noodles & Wrappers', 'Spinach Noodles', 'Spinach Noodles (Minimum 6 packs)', '1kg/pack', 195.00, 100, '', '/uploads/products/Spinach_Noodles__Minimum_6_packs_/product_image.png', NULL),
(68, 'Noodles & Wrappers', 'Siomai Wrapper', 'Siomai Wrapper (Yellow/White)', '250g/pack', 70.00, 100, '', '/uploads/products/Siomai_Wrapper__Yellow_White_/product_image.png', NULL),
(69, 'Noodles & Wrappers', 'Wanton Wrapper', 'Wanton Wrapper (Yellow/White)', '250g/pack', 70.00, 100, '', '/uploads/products/Wanton_Wrapper__Yellow_White_/product_image.png', NULL),
(70, 'Noodles & Wrappers', 'Beancurd Wrapper', 'Beancurd Wrapper', '1kg/pack', 1600.00, 100, '', '/uploads/products/Beancurd_Wrapper/product_image.png', NULL),
(71, 'Noodles & Wrappers', 'Spring Roll Wrapper', 'Spring Roll Wrapper', '25pcs/pack', 90.00, 100, '', '/uploads/products/Spring_Roll_Wrapper/product_image.png', NULL),
(72, 'Noodles & Wrappers', 'Gyoza Wrapper', 'Gyoza Wrapper (Minimum 10 Packs)', '250g/pack', 70.00, 100, '', '/uploads/products/Gyoza_Wrapper__Minimum_10_Packs_/product_image.png', NULL),
(82, 'Pork', 'Sisig', 'Sisig (Small)', '100g', 500.00, 100, '', '/uploads/products/Sisig__Small_/product_image.png', NULL),
(83, 'Dimsum & Dumplings', 'Xiao Long Bao', 'Xiao Long Bao (A)', '6pcs/pack', 300.00, 100, '', '/uploads/products/Xiao_Long_Bao__A_/product_image.png', NULL);

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
(1, 'Chicken Meat', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:35:59'),
(2, 'Chicken Feet', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:35:53'),
(3, 'Chicken Breading', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:35:51'),
(4, 'Chicken Diced Seasoned', 9610.00, '2025-04-07 16:57:58', '2025-04-22 04:10:21'),
(5, 'Chicken Lemon Seasoned', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:35:57'),
(6, 'Chicken Leg Boneless', 10400.00, '2025-04-07 16:57:58', '2025-04-15 12:35:54'),
(7, 'Chicken Leg Quarter', 10400.00, '2025-04-07 16:57:58', '2025-04-15 12:35:56'),
(8, 'Chicken Marinated', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:35:58'),
(9, 'Chicken Sliced Seasoned', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:01'),
(10, 'Whole Chicken', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:44:14'),
(11, 'Peking Duck', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:33'),
(12, 'Pork Meat', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:43:49'),
(13, 'Pork Belly (Skin On)', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:43:43'),
(14, 'Pork Belly (Skinless)', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:43:44'),
(15, 'Pork Chop Marinated', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:43:45'),
(16, 'Pork Fat', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:43:45'),
(17, 'Pork Pigue', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:43:48'),
(18, 'Pork Rib Spicy Seasoned', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:43:49'),
(19, 'Pork Ribs', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:43:50'),
(20, 'Pork Sliced Seasoned', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:43:51'),
(21, 'Pork Sweet & Sour Seasoned', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:43:53'),
(22, 'Porkloin', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:43:55'),
(23, 'Pork Spareribs', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:43:52'),
(24, 'Minced Pork', 193630.00, '2025-04-07 16:57:58', '2025-04-30 08:26:44'),
(25, 'Siao Long Pao (Ham)', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:44:01'),
(26, 'Wanton (Ham)', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:44:13'),
(27, 'Veg. Spring Roll (Ham)', 98700.00, '2025-04-07 16:57:58', '2025-04-26 06:50:41'),
(28, 'Smoked Ham', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:44:03'),
(29, 'Hakaav (Ham)', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:24'),
(30, 'Beef Meat', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:35:40'),
(31, 'Beef Cube Roll', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:35:37'),
(32, 'Beef Forequarter / Brisket', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:35:38'),
(33, 'Beef Knuckle', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:35:39'),
(34, 'Beef Rum', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:35:41'),
(35, 'Beef Short Plates', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:35:42'),
(36, 'Beef Sliced Seasoned', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:35:44'),
(37, 'Beef Tenderloin Seasoned', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:35:45'),
(38, 'Shrimp', 98990.00, '2025-04-07 16:57:58', '2025-04-26 06:50:41'),
(39, 'Crabstick', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:05'),
(40, 'Scallop', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:43:59'),
(41, 'Dried Japanese Scallop', 10400.00, '2025-04-07 16:57:58', '2025-04-15 12:36:11'),
(42, 'Salted Fish', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:43:56'),
(43, 'Hibi', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:23'),
(44, 'Ebito', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:16'),
(45, 'Squid Cube', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:44:04'),
(46, 'Giant Squid / Cuttlefish', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:20'),
(47, 'Cuttlefish Seasoned', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:09'),
(48, 'Fish Fillet Seasoned', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:17'),
(49, 'Cream Dory Fillet', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:06'),
(50, 'Cream Dory Fish Skin', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:07'),
(51, 'Tai Tai Fish', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:44:08'),
(52, 'Soy Sauce', 98330.00, '2025-04-07 16:57:58', '2025-04-30 08:26:44'),
(53, 'Japanese Soy Sauce', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:26'),
(54, 'Hoisin Sauce', 7972.00, '2025-04-07 16:57:58', '2025-04-26 06:50:41'),
(55, 'Star Anise', 237.00, '2025-04-07 16:57:58', '2025-04-22 04:10:21'),
(56, 'Butter (Anchor)', 7420.00, '2025-04-07 16:57:58', '2025-04-26 06:50:41'),
(57, 'Margarine Buttercup (Buttercup)', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:29'),
(58, 'Cheese Quickmelt (Magnolia)', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:35:48'),
(59, 'Cheese Unsalted (Magnolia)', 10400.00, '2025-04-07 16:57:58', '2025-04-15 12:35:50'),
(60, 'Cheese (Eden)', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:35:47'),
(61, 'Dark Chocolate Bar', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:10'),
(62, 'Flour', 131060.00, '2025-04-07 16:57:58', '2025-04-30 08:26:44'),
(63, 'Sugar', 96771.00, '2025-04-07 16:57:58', '2025-04-30 08:26:44'),
(64, 'Yeast', 99450.00, '2025-04-07 16:57:58', '2025-04-26 06:50:41'),
(65, 'Baking Powder', 9649.50, '2025-04-07 16:57:58', '2025-04-26 06:50:41'),
(66, 'Milk', 6669.00, '2025-04-07 16:57:58', '2025-04-30 08:26:44'),
(67, 'Glutinous Rice', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:21'),
(68, 'Chestnut', 10400.00, '2025-04-07 16:57:58', '2025-04-15 12:35:51'),
(69, 'Tofu', 99500.00, '2025-04-07 16:57:58', '2025-04-26 06:50:41'),
(70, 'Chili', 10400.00, '2025-04-07 16:57:58', '2025-04-15 12:36:02'),
(71, 'Garlic', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:19'),
(72, 'Lemon', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:28'),
(73, 'Pineapple', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:43:41'),
(74, 'Cornstarch', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:04'),
(75, 'Vinegar', 300.00, '2025-04-07 16:57:58', '2025-04-08 16:44:12'),
(76, 'Dried Shrimp', 110400.00, '2025-04-07 16:57:58', '2025-04-19 07:56:20'),
(77, 'Ketchup', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:27'),
(156, 'Oil', 10300.00, '2025-04-07 17:57:32', '2025-04-15 12:36:32'),
(157, 'Salt', 300.00, '2025-04-07 17:57:32', '2025-04-08 16:43:56'),
(158, 'Sausana/Chinese', 300.00, '2025-04-07 17:57:32', '2025-04-08 16:43:58'),
(159, 'Crispy Powder', 10300.00, '2025-04-07 17:57:32', '2025-04-15 12:36:08');

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
(1, 'Super Admin', 'active', 'Accounts - Admin, Accounts - Clients, User Roles, Dashboard, Inventory, Raw Materials, Deliverable Orders, Order History, Orders, Payment History, Department Forecast, Forecast, Drivers'),
(2, 'Manager', 'active', 'Accounts - Admin, Accounts - Clients, Dashboard, Order History, Payment History, Department Forecast, Forecast, Drivers'),
(3, 'Secretary', 'active', 'Customers, Dashboard, Inventory, Order History, Orders, Payment History, Raw Materials, Deliverable Orders'),
(4, 'Accountant', 'active', 'Dashboard, Payment History'),
(36, 'aed', 'inactive', 'Accounts - Admin, Accounts - Clients, User Roles, Dashboard, Inventory, Raw Materials, Order History, Orders, Payment History, Department Forecast, Forecast, Drivers'),
(38, 'Staff', 'active', 'Dashboard, Inventory, Order History, Orders, Raw Materials, Deliverable Orders'),
(39, 'Owner', 'active', 'Dashboard, Inventory, Raw Materials, Deliverable Orders, Order History, Orders, Payment History, Department Forecast, Forecast'),
(46, 'Kupal', 'active', 'User Roles');

-- --------------------------------------------------------

--
-- Table structure for table `walkin_products`
--

CREATE TABLE `walkin_products` (
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
-- Dumping data for table `walkin_products`
--

INSERT INTO `walkin_products` (`product_id`, `category`, `product_name`, `item_description`, `packaging`, `price`, `stock_quantity`, `additional_description`, `product_image`, `ingredients`) VALUES
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
(11, 'Siopao', 'Cuaopao', 'Cuaopao', '10pcs/pack', 125.00, 100, '', '/uploads/products/Cuaopao/product_image.png', '[[\"Minced Pork\", 600], [\"Soy Sauce\", 40], [\"Sugar\", 30], [\"Hoisin Sauce\", 30], [\"Flour\", 900], [\"Yeast\", 10], [\"Milk\", 50], [\"Butter (Anchor)\", 30], [\"Baking Powder\", 10]]'),
(12, 'Siopao', 'Minibun Mantao', 'Minibun Mantao', '12pcs/pack', 115.00, 100, '', '/uploads/products/Minibun_Mantao/product_image.png', '[[\"Flour\", 840], [\"Yeast\", 12], [\"Milk\", 48], [\"Sugar\", 48], [\"Butter (Anchor)\", 24], [\"Baking Powder\", 12]]'),
(13, 'Siopao', 'Egg Custard Pao', 'Egg Custard Pao (Min 10 packs)', '8pcs/pack', 150.00, 100, '', '/uploads/products/Egg_Custard_Pao__Min_10_packs_/product_image.png', '[[\"Flour\", 640], [\"Milk\", 80], [\"Sugar\", 64], [\"Butter (Anchor)\", 32], [\"Yeast\", 8], [\"Baking Powder\", 8]]'),
(14, 'Dimsum & Dumplings', 'Regular Pork Siomai', 'Regular Pork Siomai', '30pcs/pack', 145.00, 100, '', '/uploads/products/Regular_Pork_Siomai/product_image.png', '[[\"Minced Pork\", 750], [\"Soy Sauce\", 30], [\"Sugar\", 15], [\"Flour\", 90], [\"Milk\", 6], [\"Butter (Anchor)\", 6]]'),
(15, 'Dimsum & Dumplings', 'Special Pork Siomai', 'Special Pork Siomai', '30pcs/pack', 240.00, 100, '', '/uploads/products/Special_Pork_Siomai/product_image.png', '[[\"Minced Pork\", 660], [\"Shrimp\", 90], [\"Soy Sauce\", 30], [\"Sugar\", 15], [\"Hoisin Sauce\", 15], [\"Flour\", 90], [\"Butter (Anchor)\", 9]]'),
(16, 'Dimsum & Dumplings', 'Regular Sharksfin Dumpling', 'Regular Sharksfin Dumpling', '30pcs/pack', 180.00, 100, '', '/uploads/products/Regular_Sharksfin_Dumpling/product_image.png', '[[\"Minced Pork\", 690], [\"Soy Sauce\", 30], [\"Sugar\", 15], [\"Flour\", 90], [\"Milk\", 9]]'),
(17, 'Dimsum & Dumplings', 'Special Sharksfin Dumpling', 'Special Sharksfin Dumpling', '30pcs/pack', 260.00, 100, '', '/uploads/products/Special_Sharksfin_Dumpling/product_image.png', '[[\"Minced Pork\", 630], [\"Shrimp\", 120], [\"Soy Sauce\", 30], [\"Hoisin Sauce\", 15], [\"Flour\", 90]]'),
(18, 'Dimsum & Dumplings', 'Kutchay Dumpling', 'Kutchay Dumpling', '30pcs/pack', 275.00, 100, '', '/uploads/products/Kutchay_Dumpling/product_image.png', '[[\"Minced Pork\", 600], [\"Soy Sauce\", 30], [\"Sugar\", 15], [\"Flour\", 90], [\"Butter (Anchor)\", 9]]'),
(19, 'Dimsum & Dumplings', 'Chicken Siomai', 'Chicken Siomai', '30pcs/pack', 300.00, 100, '', '/uploads/products/Chicken_Siomai/product_image.png', '[[\"Chicken Diced Seasoned\", 690], [\"Soy Sauce\", 30], [\"Sugar\", 15], [\"Flour\", 90]]'),
(20, 'Dimsum & Dumplings', 'Beef Siomai', 'Beef Siomai', '20pcs/pack', 250.00, 100, '', '/uploads/products/Beef_Siomai/product_image.png', '[[\"Beef Sliced Seasoned\", 500], [\"Soy Sauce\", 20], [\"Hoisin Sauce\", 10], [\"Flour\", 60], [\"Butter (Anchor)\", 4]]'),
(21, 'Dimsum & Dumplings', 'Premium Pork Siomai', 'Premium Pork Siomai (A)', '20pcs/pack', 280.00, 100, '', '/uploads/products/Premium_Pork_Siomai__A_/product_image.png', '[[\"Minced Pork\", 440], [\"Shrimp\", 60], [\"Soy Sauce\", 20], [\"Hoisin Sauce\", 10], [\"Flour\", 60]]'),
(22, 'Dimsum & Dumplings', 'Premium Pork Siomai w/ Shrimp', 'Premium Pork Siomai w/ Shrimp (A)', '20pcs/pack', 310.00, 100, '', '/uploads/products/Premium_Pork_Siomai_w__Shrimp__A_/product_image.png', '[[\"Minced Pork\", 400], [\"Shrimp\", 100], [\"Soy Sauce\", 20], [\"Hoisin Sauce\", 10], [\"Flour\", 60]]'),
(23, 'Dimsum & Dumplings', 'Premium Sharksfin Dumpling', 'Premium Sharksfin Dumpling', '20pcs/pack', 300.00, 100, '', '/uploads/products/Premium_Sharksfin_Dumpling/product_image.png', '[[\"Minced Pork\", 400], [\"Shrimp\", 100], [\"Soy Sauce\", 20], [\"Flour\", 60], [\"Butter (Anchor)\", 6]]'),
(24, 'Dimsum & Dumplings', 'Hakaw', 'Hakaw (Shrimp Dumpling) (A)', '12pcs/pack', 300.00, 100, '', '/uploads/products/Hakaw__Shrimp_Dumpling___A_/product_image.png', '[[\"Shrimp\", 300], [\"Butter (Anchor)\", 3.6], [\"Flour\", 36], [\"Sugar\", 6]]'),
(25, 'Dimsum & Dumplings', 'Hakaw', 'Hakaw (Shrimp Dumpling) (B)', '20pcs/pack', 480.00, 100, '', '/uploads/products/Hakaw__Shrimp_Dumpling___B_/product_image.png', '[[\"Shrimp\", 500], [\"Butter (Anchor)\", 6], [\"Flour\", 60], [\"Sugar\", 10]]'),
(26, 'Dimsum & Dumplings', 'Japanese Pork Siomai', 'Japanese Pork Siomai (A)', '20pcs/pack', 325.00, 100, '', '/uploads/products/Japanese_Pork_Siomai__A_/product_image.png', '[[\"Minced Pork\", 400], [\"Soy Sauce\", 20], [\"Japanese Soy Sauce\", 20], [\"Flour\", 60], [\"Sugar\", 10]]'),
(27, 'Dimsum & Dumplings', 'Polonchay Dumpling', 'Polonchay Dumpling (Min 6 Packs) (A)', '12pcs/pack', 310.00, 100, '', '/uploads/products/Polonchay_Dumpling__Min_6_Packs___A_/product_image.png', '[[\"Minced Pork\", 240], [\"Soy Sauce\", 12], [\"Flour\", 36]]'),
(28, 'Dimsum & Dumplings', 'Polonchay Dumpling', 'Polonchay Dumpling (Min 6 Packs) (B)', '20pcs/pack', 470.00, 100, '', '/uploads/products/Polonchay_Dumpling__Min_6_Packs___B_/product_image.png', '[[\"Minced Pork\", 400], [\"Soy Sauce\", 20], [\"Flour\", 60]]'),
(29, 'Dimsum & Dumplings', 'Polonchay Dumpling w/ Shrimp', 'Polonchay Dumpling w/ Shrimp (Min 6 Packs) (A)', '12pcs/pack', 330.00, 100, '', '/uploads/products/Polonchay_Dumpling_w__Shrimp__Min_6_Packs___A_/product_image.png', '[[\"Minced Pork\", 216], [\"Shrimp\", 48], [\"Soy Sauce\", 12], [\"Flour\", 36]]'),
(30, 'Dimsum & Dumplings', 'Polonchay Dumpling w/ Shrimp', 'Polonchay Dumpling w/ Shrimp (Min 6 Packs) (B)', '20pcs/pack', 530.00, 100, '', '/uploads/products/Polonchay_Dumpling_w__Shrimp__Min_6_Packs___B_/product_image.png', '[[\"Minced Pork\", 360], [\"Shrimp\", 80], [\"Soy Sauce\", 20], [\"Flour\", 60]]'),
(31, 'Dimsum & Dumplings', 'Beancurd Roll', 'Beancurd Roll (A)', '12pcs/pack', 310.00, 100, '', '/uploads/products/Beancurd_Roll__A_/product_image.png', '[[\"Minced Pork\",264],[\"Soy Sauce\",12],[\"Sugar\",6],[\"Veg. Spring Roll (Ham)\",36]]'),
(32, 'Dimsum & Dumplings', 'Beancurd Roll', 'Beancurd Roll (B)', '20pcs/pack', 500.00, 100, '', '/uploads/products/Beancurd_Roll__B_/product_image.png', '[[\"Minced Pork\", 440], [\"Soy Sauce\", 20], [\"Sugar\", 10], [\"Veg. Spring Roll (Ham)\", 60]]'),
(33, 'Dimsum & Dumplings', 'Pork Gyoza Dumpling', 'Pork Gyoza Dumpling (A)', '20pcs/pack', 390.00, 100, '', '/uploads/products/Pork_Gyoza_Dumpling__A_/product_image.png', '[[\"Minced Pork\", 440], [\"Soy Sauce\", 20], [\"Crispy Powder\", 40], [\"Flour\", 60]]'),
(34, 'Dimsum & Dumplings', 'Shanghai Dumpling', 'Shanghai Dumpling (A)', '20pcs/pack', 255.00, 100, '', '/uploads/products/Shanghai_Dumpling__A_/product_image.png', '[[\"Minced Pork\", 440], [\"Soy Sauce\", 20], [\"Sugar\", 10], [\"Flour\", 60]]'),
(35, 'Dimsum & Dumplings', 'Siao Long Pao', 'Siao Long Pao', '15pcs/pack', 270.00, 100, '', '/uploads/products/Siao_Long_Pao/product_image.png', '[[\"Minced Pork\", 375], [\"Soy Sauce\", 15], [\"Hoisin Sauce\", 7.5], [\"Flour\", 45]]'),
(36, 'Dimsum & Dumplings', 'Wanton Regular', 'Wanton Regular', '20pcs/pack', 315.00, 100, '', '/uploads/products/Wanton_Regular/product_image.png', '[[\"Minced Pork\", 400], [\"Soy Sauce\", 20], [\"Flour\", 60]]'),
(37, 'Dimsum & Dumplings', 'Sesame Butchi Ball', 'Sesame Butchi Ball', '12pcs/pack', 185.00, 100, '', '/uploads/products/Sesame_Butchi_Ball/product_image.png', '[[\"Glutinous Rice\", 300], [\"Sugar\", 60], [\"Dark Chocolate Bar\", 48], [\"Butter (Anchor)\", 12]]'),
(38, 'Dimsum & Dumplings', 'Machang', 'Machang (Hong Kong)', '6pcs/pack', 250.00, 100, '', '/uploads/products/Machang__Hong_Kong_/product_image.png', '[[\"Glutinous Rice\", 720], [\"Pork Belly (Skin On)\", 240], [\"Soy Sauce\", 18], [\"Chestnut\", 60]]'),
(39, 'Dimsum & Dumplings', 'Machang w/ Chestnut', 'Machang w/ Chestnut (Min 6 Packs)', '1pc', 110.00, 100, '', '/uploads/products/Machang_w__Chestnut__Min_6_Packs_/product_image.png', '[[\"Glutinous Rice\", 130], [\"Pork Belly (Skin On)\", 40], [\"Soy Sauce\", 3], [\"Chestnut\", 20]]'),
(40, 'Dimsum & Dumplings', 'Pork Rib Taosi', 'Pork Rib Taosi', '500g', 200.00, 100, '', '/uploads/products/Pork_Rib_Taosi/product_image.png', '[[\"Pork Ribs\", 500], [\"Soy Sauce\", 15], [\"Star Anise\", 5]]'),
(41, 'Dimsum & Dumplings', 'Pork Spring Roll', 'Pork Spring Roll', '20pcs/pack', 320.00, 100, '', '/uploads/products/Pork_Spring_Roll/product_image.png', '[[\"Minced Pork\", 360], [\"Veg. Spring Roll (Ham)\", 60], [\"Soy Sauce\", 20]]'),
(42, 'Dimsum & Dumplings', 'Chicken Feet', 'Chicken Feet', '500g', 200.00, 100, '', '/uploads/products/Chicken_Feet/product_image.png', '[[\"Chicken Feet\", 500], [\"Soy Sauce\", 10], [\"Star Anise\", 5]]'),
(43, 'Dimsum & Dumplings', 'Radish Cake', 'Radish Cake 1.5kg', '1.5kg', 370.00, 100, '', '/uploads/products/Radish_Cake_1_5kg/product_image.png', '[[\"Minced Pork\", 300], [\"Flour\", 200], [\"Soy Sauce\", 30], [\"Sugar\", 20]]'),
(44, 'Dimsum & Dumplings', 'Radish Cake', 'Radish Cake 1kg', '1kg', 300.00, 100, '', '/uploads/products/Radish_Cake_1kg/product_image.png', '[[\"Minced Pork\", 200], [\"Flour\", 135], [\"Soy Sauce\", 20], [\"Sugar\", 13]]'),
(45, 'Dimsum & Dumplings', 'Pumpkin Cake', 'Pumpkin Cake 1.5kg', '1.5kg', 370.00, 100, '', '/uploads/products/Pumpkin_Cake_1_5kg/product_image.png', '[[\"Minced Pork\", 300], [\"Flour\", 200], [\"Sugar\", 30], [\"Butter (Anchor)\", 20]]'),
(46, 'Dimsum & Dumplings', 'Pumpkin Cake', 'Pumpkin Cake 1kg', '1kg', 300.00, 100, '', '/uploads/products/Pumpkin_Cake_1kg/product_image.png', '[[\"Minced Pork\", 200], [\"Flour\", 135], [\"Sugar\", 20], [\"Butter (Anchor)\", 13]]'),
(47, 'Healthy Dimsum', 'Vegetable Dumpling', 'Vegetable Dumpling (A)', '12pcs/pack', 190.00, 100, '', '/uploads/products/Vegetable_Dumpling__A_/product_image.png', '[[\"Veg. Spring Roll (Ham)\", 120], [\"Tofu\", 60], [\"Soy Sauce\", 12], [\"Flour\", 36]]'),
(48, 'Healthy Dimsum', 'Vegetable Dumpling', 'Vegetable Dumpling (B)', '20pcs/pack', 300.00, 100, '', '/uploads/products/Vegetable_Dumpling__B_/product_image.png', '[[\"Veg. Spring Roll (Ham)\", 200], [\"Tofu\", 100], [\"Soy Sauce\", 20], [\"Flour\", 60]]'),
(49, 'Healthy Dimsum', 'Vegetable Spring Roll', 'Vegetable Spring Roll (A)', '12pcs/pack', 230.00, 100, '', '/uploads/products/Vegetable_Spring_Roll__A_/product_image.png', '[[\"Veg. Spring Roll (Ham)\", 144], [\"Tofu\", 48], [\"Spring Roll Wrapper\", 12]]'),
(50, 'Healthy Dimsum', 'Vegetable Spring Roll', 'Vegetable Spring Roll (B)', '20pcs/pack', 360.00, 100, '', '/uploads/products/Vegetable_Spring_Roll__B_/product_image.png', '[[\"Veg. Spring Roll (Ham)\", 240], [\"Tofu\", 80], [\"Spring Roll Wrapper\", 20]]'),
(51, 'Sauces', 'Chili Sauce', 'Chili Sauce (A)', '1.5kg/cntr', 590.00, 100, '', '/uploads/products/Chili_Sauce__A_/product_image.png', '[[\"Chili\", 750], [\"Garlic\", 300], [\"Vinegar\", 200], [\"Oil\", 150], [\"Sugar\", 50], [\"Salt\", 50]]'),
(52, 'Sauces', 'Chili Sauce', 'Chili Sauce (B)', '220g/btl', 160.00, 100, '', '/uploads/products/Chili_Sauce__B_/product_image.png', '[[\"Chili\", 110], [\"Garlic\", 44], [\"Vinegar\", 30], [\"Oil\", 22], [\"Sugar\", 7], [\"Salt\", 7]]'),
(53, 'Sauces', 'Seafood XO Sauce', 'Seafood XO Sauce', '220g/btl', 320.00, 100, '', '/uploads/products/Seafood_XO_Sauce/product_image.png', '[[\"Dried Japanese Scallop\", 40], [\"Dried Shrimp\", 40], [\"Garlic\", 30], [\"Chili\", 20], [\"Oil\", 60], [\"Soy Sauce\", 30]]'),
(54, 'Sauces', 'Lemon Sauce', 'Lemon Sauce (A)', '420g/btl', 135.00, 100, '', '/uploads/products/Lemon_Sauce__A_/product_image.png', '[[\"Lemon\", 200], [\"Sugar\", 100], [\"Cornstarch\", 60], [\"Vinegar\", 60]]'),
(55, 'Sauces', 'Sweet & Sour Sauce', 'Sweet & Sour Sauce (A)', '420g/btl', 135.00, 100, '', '/uploads/products/Sweet___Sour_Sauce__A_/product_image.png', '[[\"Ketchup\", 150], [\"Pineapple\", 100], [\"Vinegar\", 80], [\"Sugar\", 50], [\"Cornstarch\", 40]]'),
(56, 'Sauces', 'Beef Fillet Sauce', 'Beef Fillet Sauce', '420g/btl', 150.00, 100, '', '/uploads/products/Beef_Fillet_Sauce/product_image.png', '[[\"Soy Sauce\", 120], [\"Sugar\", 80], [\"Garlic\", 60], [\"Star Anise\", 20], [\"Cornstarch\", 140]]'),
(57, 'Sauces', 'Lemon Sauce', 'Lemon Sauce (B)', '3.5kg/Gal', 620.00, 100, '', '/uploads/products/Lemon_Sauce__B_/product_image.png', '[[\"Lemon\", 1660], [\"Sugar\", 830], [\"Cornstarch\", 500], [\"Vinegar\", 510]]'),
(58, 'Sauces', 'Sweet & Sour Sauce', 'Sweet & Sour Sauce (B)', '3.5kg/Gal', 620.00, 100, '', '/uploads/products/Sweet___Sour_Sauce__B_/product_image.png', '[[\"Ketchup\", 1250], [\"Pineapple\", 830], [\"Vinegar\", 700], [\"Sugar\", 420], [\"Cornstarch\", 300]]'),
(59, 'Marinated Items', 'Asado Marinated', 'Asado Marinated (Char Siu)', '1kg', 400.00, 100, '', '/uploads/products/Asado_Marinated__Char_Siu_/product_image.png', '[[\"Pork Belly (Skin On)\", 1000], [\"Soy Sauce\", 50], [\"Sugar\", 40], [\"Star Anise\", 10], [\"Sausana/Chinese\", 30]]'),
(60, 'Marinated Items', 'Asado Cooked', 'Asado Cooked (Char Siu)', '1kg', 700.00, 100, '', '/uploads/products/Asado_Cooked__Char_Siu_/product_image.png', '[[\"Pork Belly (Skin On)\", 1000], [\"Soy Sauce\", 50], [\"Sugar\", 40], [\"Star Anise\", 10], [\"Sausana/Chinese\", 30]]'),
(61, 'Noodles & Wrappers', 'Pancit Canton', 'Pancit Canton', '2kg/pack', 350.00, 100, '', '/uploads/products/Pancit_Canton/product_image.png', NULL),
(62, 'Noodles & Wrappers', 'Dried Egg Noodles', 'Dried Egg Noodles', '1kg/pack', 185.00, 100, '', '/uploads/products/Dried_Egg_Noodles/product_image.png', NULL),
(63, 'Noodles & Wrappers', 'Hongkong Noodles', 'Hongkong Noodles (Yellow/White)', '1kg/pack', 185.00, 100, '', '/uploads/products/Hongkong_Noodles__Yellow_White_/product_image.png', NULL),
(64, 'Noodles & Wrappers', 'Shanghai Noodles', 'Shanghai Noodles (Yellow/White)', '2kg/pack', 360.00, 100, '', '/uploads/products/Shanghai_Noodles__Yellow_White_/product_image.png', NULL),
(65, 'Noodles & Wrappers', 'Hofan Noodles', 'Hofan Noodles (Minimum 6 packs)', '1kg/pack', 170.00, 100, '', '/uploads/products/Hofan_Noodles__Minimum_6_packs_/product_image.png', NULL),
(66, 'Noodles & Wrappers', 'Ramen Noodles', 'Ramen Noodles', '1kg/pack', 195.00, 100, '', '/uploads/products/Ramen_Noodles/product_image.png', NULL),
(67, 'Noodles & Wrappers', 'Spinach Noodles', 'Spinach Noodles (Minimum 6 packs)', '1kg/pack', 195.00, 100, '', '/uploads/products/Spinach_Noodles__Minimum_6_packs_/product_image.png', NULL),
(68, 'Noodles & Wrappers', 'Siomai Wrapper', 'Siomai Wrapper (Yellow/White)', '250g/pack', 70.00, 100, '', '/uploads/products/Siomai_Wrapper__Yellow_White_/product_image.png', NULL),
(69, 'Noodles & Wrappers', 'Wanton Wrapper', 'Wanton Wrapper (Yellow/White)', '250g/pack', 70.00, 100, '', '/uploads/products/Wanton_Wrapper__Yellow_White_/product_image.png', NULL),
(70, 'Noodles & Wrappers', 'Beancurd Wrapper', 'Beancurd Wrapper', '1kg/pack', 1600.00, 100, '', '/uploads/products/Beancurd_Wrapper/product_image.png', NULL),
(71, 'Noodles & Wrappers', 'Spring Roll Wrapper', 'Spring Roll Wrapper', '25pcs/pack', 90.00, 100, '', '/uploads/products/Spring_Roll_Wrapper/product_image.png', NULL),
(72, 'Noodles & Wrappers', 'Gyoza Wrapper', 'Gyoza Wrapper (Minimum 10 Packs)', '250g/pack', 70.00, 100, '', '/uploads/products/Gyoza_Wrapper__Minimum_10_Packs_/product_image.png', NULL),
(82, 'Pork', 'Sisig', 'Sisig (Small)', '100g', 500.00, 100, '', '/uploads/products/Sisig__Small_/product_image.png', NULL),
(83, 'Dimsum & Dumplings', 'Xiao Long Bao', 'Xiao Long Bao (A)', '6pcs/pack', 300.00, 100, '', '/uploads/products/Xiao_Long_Bao__A_/product_image.png', NULL);

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
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `driver_assignments`
--
ALTER TABLE `driver_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `driver_id` (`driver_id`);

--
-- Indexes for table `driver_orders`
--
ALTER TABLE `driver_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `po_number` (`po_number`);

--
-- Indexes for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `manufacturing_logs`
--
ALTER TABLE `manufacturing_logs`
  ADD PRIMARY KEY (`log_id`);

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
-- Indexes for table `order_status_logs`
--
ALTER TABLE `order_status_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `po_number` (`po_number`);

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
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_status_history`
--
ALTER TABLE `payment_status_history`
  ADD PRIMARY KEY (`id`);

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
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `walkin_products`
--
ALTER TABLE `walkin_products`
  ADD PRIMARY KEY (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `balance_history`
--
ALTER TABLE `balance_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `clients_accounts`
--
ALTER TABLE `clients_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `driver_assignments`
--
ALTER TABLE `driver_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `driver_orders`
--
ALTER TABLE `driver_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `manufacturing_logs`
--
ALTER TABLE `manufacturing_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `monthly_payments`
--
ALTER TABLE `monthly_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=832;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;

--
-- AUTO_INCREMENT for table `order_status_logs`
--
ALTER TABLE `order_status_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `page_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `payment_history`
--
ALTER TABLE `payment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `payment_status_history`
--
ALTER TABLE `payment_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=159;

--
-- AUTO_INCREMENT for table `raw_materials`
--
ALTER TABLE `raw_materials`
  MODIFY `material_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=160;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `walkin_products`
--
ALTER TABLE `walkin_products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
