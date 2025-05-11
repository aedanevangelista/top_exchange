-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 11, 2025 at 02:16 PM
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
(73, 'Owner', '123', '2025-04-29 17:09:36', 'Owner', 'Active', NULL, NULL),
(76, 'User-Developer', '123', '2025-05-05 02:19:53', 'Super Admin', 'Active', NULL, NULL),
(77, 'User-Aedan', '123', '2025-05-05 02:27:32', 'Super Admin', 'Active', NULL, NULL),
(78, 'Acc-Lisa', '123', '2025-05-05 08:21:48', 'Accountant', 'Active', NULL, NULL);

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
(55, 'solaire', '$2y$10$w.AIdSs/MTO96fDVnKCy2eoety7bxCFQxUJ2sJ.Iz5RuNSAb2mphS', 'solairemanilainc@gmail.com', '09234892374', '130000000', 'Quezon City', 'Solaire Manila', '123 Solaire Manila', '123 Solaire Manila', '[\"\\/admin\\/uploads\\/solaire\\/solaire_team_cfo_2bf5753710.jpg\"]', 'Active', 0.00, '2025-05-07 19:12:02', NULL, NULL, NULL, NULL),
(56, 'Boters', '$2y$10$gb0fmX.We4nlW/XfodSWcOrN/M6OFTAmjoNIyOl1AuT0KOqm6fnNm', 'jefferson45santonia@gmail.com', '09185585149', '130000000', 'Quezon City', 'Meow', 'hh', 'hh', '[\"\\/admin\\/uploads\\/Boters\\/Boters_A_a1aafc2761.png\"]', 'Active', 0.00, '2025-05-08 16:47:41', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `contact_no` varchar(50) NOT NULL,
  `availability` enum('Available','Not Available') NOT NULL DEFAULT 'Available',
  `area` enum('North','South') NOT NULL DEFAULT 'North',
  `status` enum('Active','Archive') NOT NULL DEFAULT 'Active',
  `current_deliveries` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`id`, `name`, `username`, `password`, `address`, `contact_no`, `availability`, `area`, `status`, `current_deliveries`, `created_at`, `updated_at`) VALUES
(4, 'Aedan Evangelista', 'aedan', '$2y$10$uPIjJ2VgVwgs6tdY3B8Z7Oh5P.7ANstwEniWBsGblMeXcxsAbb36.', '123 Aedan\'s Restaurant', '094289374892', 'Available', 'North', 'Active', 7, '2025-05-04 11:31:37', '2025-05-05 07:09:31'),
(5, 'Ryan Rodriguez', 'Driver123', '$2y$10$ikFjRhq6PKJhoWhP7otfb.C4WKdviANcsGFps17qOTauCl398Scem', '9A Alibangbang Street Quezon City', '09164435991', 'Available', 'South', 'Active', 0, '2025-05-05 07:14:10', '2025-05-05 07:14:10'),
(6, 'Mark James', 'Mark', '$2y$10$/xltp5uTw.Z3FfRJgXw43OgK1l8pzH2zefrrKFSap/eZDy6nzlrm2', '9A Alibangbang Street Quezon City', '09164435991', 'Available', 'North', 'Active', 1, '2025-05-05 08:43:42', '2025-05-05 09:15:57');

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
(30, 'PO-aedantiu-001', 4, '2025-05-05 00:15:30', 'Assigned'),
(31, 'PO-Boters-001', 4, '2025-05-05 02:46:35', 'Assigned'),
(32, 'PO-aedantiu-002', 4, '2025-05-05 07:09:31', 'Assigned'),
(33, 'PO-solaire-001', 6, '2025-05-05 09:15:57', 'Assigned');

-- --------------------------------------------------------

--
-- Table structure for table `email_notifications`
--

CREATE TABLE `email_notifications` (
  `id` int(11) NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `recipient` varchar(255) NOT NULL,
  `email_type` varchar(50) NOT NULL COMMENT 'new_order, status_update, delivery_change',
  `sent_by` varchar(50) NOT NULL,
  `sent_at` datetime NOT NULL,
  `sent_status` tinyint(1) NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_notifications`
--

INSERT INTO `email_notifications` (`id`, `po_number`, `recipient`, `email_type`, `sent_by`, `sent_at`, `sent_status`, `error_message`) VALUES
(1, 'PO-solaire-002', 'solairemanilainc@gmail.com', 'status_update', 'admin', '2025-05-08 16:55:41', 0, NULL),
(2, 'PO-solaire-002', 'solairemanilainc@gmail.com', 'status_update', 'admin', '2025-05-08 16:56:02', 0, NULL),
(3, 'PO-solaire-002', 'solairemanilainc@gmail.com', 'status_update', 'admin', '2025-05-08 16:56:19', 0, NULL);

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
(71, 'Hakaw (Shrimp Dumpling) (A)', 'finished_product', 1.00, 'PO-250502-HEY-727402', 'Order Status Change Return', 'system', '2025-05-01 17:33:01'),
(72, 'Hakaw (Shrimp Dumpling) (B)', 'finished_product', 1.00, 'PO-250502-HEY-727402', 'Order Status Change Return', 'system', '2025-05-01 17:33:01'),
(73, 'Machang (Hong Kong)', 'finished_product', 1.00, 'PO-250502-HEY-727402', 'Order Status Change Return', 'system', '2025-05-01 17:33:01'),
(74, 'Machang w/ Chestnut (Min 6 Packs)', 'finished_product', 1.00, 'PO-250502-HEY-727402', 'Order Status Change Return', 'system', '2025-05-01 17:33:01'),
(75, 'Kutchay Dumpling', 'finished_product', 1.00, 'PO-250502-HEY-727402', 'Order Status Change Return', 'system', '2025-05-01 17:33:01'),
(76, 'Japanese Pork Siomai (A)', 'finished_product', 1.00, 'PO-250502-HEY-727402', 'Order Status Change Return', 'system', '2025-05-01 17:33:01'),
(77, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-HeyHeyHey-001', 'Order Activated', 'system', '2025-05-01 17:41:34'),
(78, 'Beancurd Roll (B)', 'finished_product', 1.00, 'PO-HeyHeyHey-001', 'Order Activated', 'system', '2025-05-01 17:41:34'),
(79, 'Beef Siomai', 'finished_product', 1.00, 'PO-HeyHeyHey-001', 'Order Activated', 'system', '2025-05-01 17:41:34'),
(80, 'Chicken Feet', 'finished_product', 1.00, 'PO-HeyHeyHey-001', 'Order Activated', 'system', '2025-05-01 17:41:34'),
(81, 'Chicken Siomai', 'finished_product', 1.00, 'PO-HeyHeyHey-001', 'Order Activated', 'system', '2025-05-01 17:41:34'),
(82, 'Beancurd Roll (B)', 'finished_product', 8.00, 'Boters-1', 'Order Activated', 'system', '2025-05-01 19:02:10'),
(83, 'Beef Siomai', 'finished_product', 2.00, 'Boters-1', 'Order Activated', 'system', '2025-05-01 19:02:10'),
(84, 'Chicken Feet', 'finished_product', 3.00, 'Boters-1', 'Order Activated', 'system', '2025-05-01 19:02:10'),
(85, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-002', 'Order Activated', 'system', '2025-05-01 19:05:02'),
(86, 'Beancurd Roll (B)', 'finished_product', 1.00, 'PO-Boters-002', 'Order Activated', 'system', '2025-05-01 19:05:02'),
(87, 'Beef Siomai', 'finished_product', 1.00, 'PO-Boters-002', 'Order Activated', 'system', '2025-05-01 19:05:02'),
(88, 'Chicken Feet', 'finished_product', 1.00, 'PO-Boters-002', 'Order Activated', 'system', '2025-05-01 19:05:02'),
(89, 'Chicken Siomai', 'finished_product', 1.00, 'PO-Boters-002', 'Order Activated', 'system', '2025-05-01 19:05:02'),
(90, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-002', 'Order Status Change Return', 'system', '2025-05-01 19:16:19'),
(91, 'Beancurd Roll (B)', 'finished_product', 1.00, 'PO-Boters-002', 'Order Status Change Return', 'system', '2025-05-01 19:16:19'),
(92, 'Beef Siomai', 'finished_product', 1.00, 'PO-Boters-002', 'Order Status Change Return', 'system', '2025-05-01 19:16:19'),
(93, 'Chicken Feet', 'finished_product', 1.00, 'PO-Boters-002', 'Order Status Change Return', 'system', '2025-05-01 19:16:19'),
(94, 'Chicken Siomai', 'finished_product', 1.00, 'PO-Boters-002', 'Order Status Change Return', 'system', '2025-05-01 19:16:19'),
(95, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-001', 'Order Activated', 'system', '2025-05-01 20:12:17'),
(96, 'Beancurd Roll (B)', 'finished_product', 1.00, 'PO-Boters-001', 'Order Activated', 'system', '2025-05-01 20:12:17'),
(97, 'Beef Siomai', 'finished_product', 1.00, 'PO-Boters-001', 'Order Activated', 'system', '2025-05-01 20:12:17'),
(98, 'Beef Siomai', 'finished_product', 96.00, 'PO-Boters-001', 'Order Activated (Partial Stock)', 'system', '2025-05-01 20:56:34'),
(99, 'Beef Sliced Seasoned', 'raw_material', 2000.00, 'PO-Boters-001', 'Manufacturing for Beef Siomai', 'system', '2025-05-01 20:56:34'),
(100, 'Soy Sauce', 'raw_material', 80.00, 'PO-Boters-001', 'Manufacturing for Beef Siomai', 'system', '2025-05-01 20:56:34'),
(101, 'Hoisin Sauce', 'raw_material', 40.00, 'PO-Boters-001', 'Manufacturing for Beef Siomai', 'system', '2025-05-01 20:56:34'),
(102, 'Flour', 'raw_material', 240.00, 'PO-Boters-001', 'Manufacturing for Beef Siomai', 'system', '2025-05-01 20:56:34'),
(103, 'Butter (Anchor)', 'raw_material', 16.00, 'PO-Boters-001', 'Manufacturing for Beef Siomai', 'system', '2025-05-01 20:56:34'),
(104, 'Beancurd Roll (A)', 'finished_product', 3.00, 'PO-sheila-001', 'Order Activated', 'system', '2025-05-02 05:35:09'),
(105, 'Chicken Feet', 'finished_product', 15.00, 'PO-sheila-001', 'Order Activated', 'system', '2025-05-02 05:35:09'),
(106, 'Hakaw (Shrimp Dumpling) (B)', 'finished_product', 1.00, 'PO-sheila-001', 'Order Activated', 'system', '2025-05-02 05:35:09'),
(107, 'Machang (Hong Kong)', 'finished_product', 1.00, 'PO-sheila-001', 'Order Activated', 'system', '2025-05-02 05:35:09'),
(108, 'Beancurd Roll (B)', 'finished_product', 1.00, 'PO-Boters-012', 'Order Activated', 'system', '2025-05-02 05:42:56'),
(109, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-HeyHeyHey-002', 'Order Activated', 'system', '2025-05-02 17:37:14'),
(110, 'Beancurd Roll (B)', 'finished_product', 1.00, 'PO-HeyHeyHey-002', 'Order Activated', 'system', '2025-05-02 17:37:14'),
(111, 'Vegetable Dumpling (B)', 'finished_product', 1.00, 'PO-HeyHeyHey-002', 'Order Activated', 'system', '2025-05-02 17:37:14'),
(112, 'Vegetable Dumpling (A)', 'finished_product', 1.00, 'PO-HeyHeyHey-002', 'Order Activated', 'system', '2025-05-02 17:37:14'),
(113, 'Siomai Wrapper (Yellow/White)', 'finished_product', 1.00, 'PO-HeyHeyHey-002', 'Order Activated', 'system', '2025-05-02 17:37:14'),
(114, 'Vegetable Dumpling (A)', 'finished_product', 20.00, 'PO-Boters-001', 'Order Activated', 'system', '2025-05-03 19:44:22'),
(115, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-002', 'Order Activated', 'system', '2025-05-04 05:13:08'),
(116, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Activated', 'system', '2025-05-04 05:18:47'),
(117, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:28:57'),
(118, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Activated', 'system', '2025-05-04 05:29:01'),
(119, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:30:34'),
(120, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:30:34'),
(121, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:30:34'),
(122, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Activated', 'system', '2025-05-04 05:30:38'),
(123, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:33:32'),
(124, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:33:32'),
(125, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:33:32'),
(126, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:33:32'),
(127, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:33:32'),
(128, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:33:32'),
(129, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:33:32'),
(130, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Activated', 'system', '2025-05-04 05:33:38'),
(131, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:46:30'),
(132, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:46:30'),
(133, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:46:30'),
(134, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:46:30'),
(135, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:46:30'),
(136, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:46:30'),
(137, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:46:30'),
(138, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:46:30'),
(139, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:46:30'),
(140, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:46:30'),
(141, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:46:30'),
(142, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:46:30'),
(143, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:46:30'),
(144, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:46:30'),
(145, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Status Change Return', 'system', '2025-05-04 05:46:30'),
(146, 'Beancurd Roll (A)', 'finished_product', 1.00, 'PO-Boters-003', 'Order Activated', 'system', '2025-05-04 05:46:36');

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
  `item_progress_percentages` longtext DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT 'check_payment',
  `payment_status` varchar(20) DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `po_number`, `username`, `company`, `order_date`, `delivery_date`, `delivery_address`, `orders`, `total_amount`, `status`, `driver_assigned`, `contact_number`, `special_instructions`, `subtotal`, `progress`, `completed_items`, `item_progress_data`, `quantity_progress_data`, `item_progress_percentages`, `payment_method`, `payment_status`) VALUES
(198, 'PO-solaire-001', 'solaire', NULL, '2025-05-05', '2025-05-07', '123 Manila St.', '[{\"product_id\":164,\"category\":\"Dimsum & Dumplings\",\"item_description\":\"Chocolate\",\"packaging\":\"12pcs/pack\",\"price\":250,\"quantity\":5},{\"product_id\":42,\"category\":\"Dimsum & Dumplings\",\"item_description\":\"Chicken Feet\",\"packaging\":\"500g\",\"price\":200,\"quantity\":5}]', 2250.00, 'Completed', 1, '09760268643', 'Fresh', 2250.00, 0, NULL, NULL, NULL, NULL, 'check_payment', 'Pending'),
(199, 'PO-solaire-002', 'solaire', 'Solaire Company Inc', '2025-05-05', '2025-05-14', '123 Manila St.', '[{\"product_id\":31,\"category\":\"Dimsum & Dumplings\",\"item_description\":\"Beancurd Roll\",\"packaging\":\"12pcs/pack\",\"price\":310,\"quantity\":1},{\"product_id\":47,\"category\":\"Healthy Dimsum\",\"item_description\":\"Vegetable Dumpling\",\"packaging\":\"12pcs/pack\",\"price\":190,\"quantity\":1},{\"product_id\":62,\"category\":\"Noodles & Wrappers\",\"item_description\":\"Dried Egg Noodles\",\"packaging\":\"1kg/pack\",\"price\":185,\"quantity\":1},{\"product_id\":59,\"category\":\"Marinated Items\",\"item_description\":\"Asado Marinated (Char Siu)\",\"packaging\":\"1kg\",\"price\":400,\"quantity\":3}]', 1885.00, 'Active', 0, NULL, '', 0.00, 0, NULL, NULL, NULL, NULL, 'check_payment', 'Pending'),
(200, 'PO-Boters-001', 'Boters', NULL, '2025-05-08', '2025-05-09', 'hh', '[{\"product_id\":20,\"category\":\"Dimsum & Dumplings\",\"item_description\":\"Beef Siomai\",\"packaging\":\"20pcs/pack\",\"price\":250,\"quantity\":2,\"is_preorder\":true}]', 500.00, 'Pending', 0, '09185585149', '', 500.00, 0, NULL, NULL, NULL, NULL, 'qr_payment', 'Pending'),
(201, 'PO-Boters-002', 'Boters', NULL, '2025-05-08', '2025-05-09', 'hh', '[{\"product_id\":20,\"category\":\"Dimsum & Dumplings\",\"item_description\":\"Beef Siomai\",\"packaging\":\"20pcs/pack\",\"price\":250,\"quantity\":1,\"is_preorder\":true},{\"product_id\":42,\"category\":\"Dimsum & Dumplings\",\"item_description\":\"Chicken Feet\",\"packaging\":\"500g\",\"price\":200,\"quantity\":1,\"is_preorder\":false}]', 450.00, 'Pending', 0, '09185585149', '', 450.00, 0, NULL, NULL, NULL, NULL, 'qr_payment', 'Completed'),
(202, 'PO-Boters-003', 'Boters', NULL, '2025-05-08', '2025-05-09', 'hh', '[{\"product_id\":31,\"category\":\"Dimsum & Dumplings\",\"item_description\":\"Beancurd Roll\",\"packaging\":\"12pcs/pack\",\"price\":310,\"quantity\":10,\"is_preorder\":false}]', 3100.00, 'Pending', 0, '09185585149', '', 3100.00, 0, NULL, NULL, NULL, NULL, 'qr_payment', 'Completed');

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
(121, 'PO-aedantiu-001', 'Pending', 'Active', 'admin', '2025-05-05 00:15:13'),
(122, 'PO-aedantiu-001', 'Active', 'For Delivery', 'admin', '2025-05-05 00:15:21'),
(123, 'PO-Boters-001', 'Pending', 'Active', 'User-Developer', '2025-05-05 02:45:54'),
(124, 'PO-Boters-001', 'Active', 'For Delivery', 'User-Developer', '2025-05-05 02:46:16'),
(125, 'PO-aedantiu-002', 'Pending', 'Active', 'admin', '2025-05-05 07:06:47'),
(126, 'PO-aedantiu-002', 'Active', 'For Delivery', 'admin', '2025-05-05 07:09:22'),
(127, 'PO-aedantiu-002', 'For Delivery', 'In Transit', 'system', '2025-05-05 07:09:34'),
(128, 'PO-aedantiu-002', 'In Transit', 'For Delivery', 'system', '2025-05-05 07:09:37'),
(129, 'PO-solaire-001', 'Pending', 'Active', 'User-Developer', '2025-05-05 09:05:13'),
(130, 'PO-solaire-001', 'Active', 'For Delivery', 'User-Developer', '2025-05-05 09:07:06'),
(131, 'PO-solaire-002', 'Pending', 'Active', 'User-Developer', '2025-05-05 09:10:05'),
(132, 'PO-solaire-002', 'Active', 'Pending', 'admin', '2025-05-08 16:17:42'),
(133, 'PO-solaire-002', 'Pending', 'Rejected', 'admin', '2025-05-08 16:17:51'),
(134, 'PO-solaire-002', 'Rejected', 'Pending', 'admin', '2025-05-08 16:18:15'),
(135, 'PO-solaire-002', 'Pending', 'Active', 'admin', '2025-05-08 16:18:32'),
(136, 'PO-solaire-002', 'Active', 'Rejected', 'admin', '2025-05-08 16:55:40'),
(137, 'PO-solaire-002', 'Rejected', 'Pending', 'admin', '2025-05-08 16:56:02'),
(138, 'PO-solaire-002', 'Pending', 'Active', 'admin', '2025-05-08 16:56:19');

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
(17, 'Drivers', 'drivers.php', 'Accounts'),
(19, 'Deliverable Orders', 'deliverable_orders.php', 'Ordering'),
(23, 'Forecast', 'forecast.php', 'Production'),
(24, 'Reporting', 'reporting.php', 'Reporting');

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
  `ingredients` text DEFAULT NULL,
  `expiration` varchar(50) DEFAULT NULL COMMENT 'Stores the expiration or shelf life of the product, e.g., 1-2 months, 6 months'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `category`, `product_name`, `item_description`, `packaging`, `price`, `stock_quantity`, `additional_description`, `product_image`, `ingredients`, `expiration`) VALUES
(1, 'Siopao', 'Asado Siopao', 'Asado Siopao', '6pcs/pack', 280.00, 99, '', '/uploads/products/Asado_Siopao__A_Large_/product_image.png', '[[\"Minced Pork\", 480], [\"Soy Sauce\", 30], [\"Sugar\", 30], [\"Hoisin Sauce\", 18], [\"Star Anise\", 0.6], [\"Flour\", 600], [\"Yeast\", 6], [\"Milk\", 30], [\"Butter (Anchor)\", 18], [\"Baking Powder\", 6]]', '1-2 months'),
(2, 'Siopao', 'Asado Siopao', 'Asado Siopao (A Med)', '10pcs/pack', 325.00, 99, '', '/uploads/products/Asado_Siopao__A_Med_/product_image.png', '[[\"Minced Pork\", 800], [\"Soy Sauce\", 50], [\"Sugar\", 50], [\"Hoisin Sauce\", 30], [\"Star Anise\", 1], [\"Flour\", 1000], [\"Yeast\", 10], [\"Milk\", 50], [\"Butter (Anchor)\", 30], [\"Baking Powder\", 10]]', '1-2 months'),
(3, 'Siopao', 'Asado Siopao', 'Asado Siopao (A Small)', '15pcs/pack', 270.00, 99, '', '/uploads/products/Asado_Siopao__A_Small_/product_image.png', '[[\"Minced Pork\", 1200], [\"Soy Sauce\", 75], [\"Sugar\", 75], [\"Hoisin Sauce\", 45], [\"Star Anise\", 1.5], [\"Flour\", 1500], [\"Yeast\", 15], [\"Milk\", 75], [\"Butter (Anchor)\", 45], [\"Baking Powder\", 15]]', '1-2 months'),
(4, 'Siopao', 'Asado Siopao', 'Asado Siopao (B Large)', '6pcs/pack', 235.00, 99, '', '/uploads/products/Asado_Siopao__B_Large_/product_image.png', '[[\"Minced Pork\", 480], [\"Soy Sauce\", 30], [\"Sugar\", 30], [\"Hoisin Sauce\", 18], [\"Star Anise\", 0.6], [\"Flour\", 600], [\"Yeast\", 6], [\"Milk\", 30], [\"Butter (Anchor)\", 18], [\"Baking Powder\", 6]]', '1-2 months'),
(5, 'Siopao', 'Asado Siopao', 'Asado Siopao (B Med)', '10pcs/pack', 250.00, 100, '', '/uploads/products/Asado_Siopao__B_Med_/product_image.png', '[[\"Minced Pork\", 800], [\"Soy Sauce\", 50], [\"Sugar\", 50], [\"Hoisin Sauce\", 30], [\"Star Anise\", 1], [\"Flour\", 1000], [\"Yeast\", 10], [\"Milk\", 50], [\"Butter (Anchor)\", 30], [\"Baking Powder\", 10]]', '1-2 months'),
(6, 'Siopao', 'Asado Siopao', 'Asado Siopao (B Small)', '15pcs/pack', 205.00, 100, '', '/uploads/products/Asado_Siopao__B_Small_/product_image.png', '[[\"Minced Pork\", 1200], [\"Soy Sauce\", 75], [\"Sugar\", 75], [\"Hoisin Sauce\", 45], [\"Star Anise\", 1.5], [\"Flour\", 1500], [\"Yeast\", 15], [\"Milk\", 75], [\"Butter (Anchor)\", 45], [\"Baking Powder\", 15]]', '1-2 months'),
(7, 'Siopao', 'Bola Bola Siopao', 'Bola Bola Siopao (Large)', '6pcs/pack', 310.00, 100, '', '/uploads/products/Bola_Bola_Siopao__Large_/product_image.png', '[[\"Minced Pork\", 420], [\"Shrimp\", 60], [\"Soy Sauce\", 30], [\"Sugar\", 18], [\"Hoisin Sauce\", 18], [\"Flour\", 600], [\"Yeast\", 6], [\"Milk\", 30], [\"Butter (Anchor)\", 18], [\"Baking Powder\", 6]]', '1-2 months'),
(8, 'Siopao', 'Bola Bola Siopao', 'Bola Bola Siopao (Med)', '10pcs/pack', 350.00, 100, '', '/uploads/products/Bola_Bola_Siopao__Med_/product_image.png', '[[\"Minced Pork\", 700], [\"Shrimp\", 100], [\"Soy Sauce\", 50], [\"Sugar\", 30], [\"Hoisin Sauce\", 30], [\"Flour\", 1000], [\"Yeast\", 10], [\"Milk\", 50], [\"Butter (Anchor)\", 30], [\"Baking Powder\", 10]]', '1-2 months'),
(9, 'Siopao', 'Bola Bola Siopao', 'Bola Bola Siopao (Small)', '15pcs/pack', 290.00, 100, '', '/uploads/products/Bola_Bola_Siopao__Small_/product_image.png', '[[\"Minced Pork\", 1050], [\"Shrimp\", 150], [\"Soy Sauce\", 75], [\"Sugar\", 45], [\"Hoisin Sauce\", 45], [\"Flour\", 1500], [\"Yeast\", 15], [\"Milk\", 75], [\"Butter (Anchor)\", 45], [\"Baking Powder\", 15]]', '1-2 months'),
(10, 'Siopao', 'Jumbo Pao', 'Jumbo Pao', '4pcs/pack', 325.00, 100, '', '/uploads/products/Jumbo_Pao/product_image.png', '[[\"Minced Pork\", 360], [\"Shrimp\", 40], [\"Sugar\", 20], [\"Soy Sauce\", 20], [\"Hoisin Sauce\", 20], [\"Flour\", 480], [\"Yeast\", 4.8], [\"Milk\", 24], [\"Butter (Anchor)\", 16], [\"Baking Powder\", 4]]', '1-2 months'),
(11, 'Siopao', 'Cuaopao', 'Cuaopao', '10pcs/pack', 125.00, 100, '', '/uploads/products/Cuaopao/product_image.png', '[[\"Minced Pork\", 600], [\"Soy Sauce\", 40], [\"Sugar\", 30], [\"Hoisin Sauce\", 30], [\"Flour\", 900], [\"Yeast\", 10], [\"Milk\", 50], [\"Butter (Anchor)\", 30], [\"Baking Powder\", 10]]', '1-2 months'),
(12, 'Siopao', 'Minibun Mantao', 'Minibun Mantao', '12pcs/pack', 115.00, 100, '', '/uploads/products/Minibun_Mantao/product_image.png', '[[\"Flour\", 840], [\"Yeast\", 12], [\"Milk\", 48], [\"Sugar\", 48], [\"Butter (Anchor)\", 24], [\"Baking Powder\", 12]]', NULL),
(13, 'Siopao', 'Egg Custard Pao', 'Egg Custard Pao (Min 10 packs)', '8pcs/pack', 150.00, 100, '', '/uploads/products/Egg_Custard_Pao__Min_10_packs_/product_image.png', '[[\"Flour\", 640], [\"Milk\", 80], [\"Sugar\", 64], [\"Butter (Anchor)\", 32], [\"Yeast\", 8], [\"Baking Powder\", 8]]', NULL),
(14, 'Dimsum & Dumplings', 'Regular Pork Siomai', 'Regular Pork Siomai', '30pcs/pack', 145.00, 100, '', '/uploads/products/Regular_Pork_Siomai/product_image.png', '[[\"Minced Pork\", 750], [\"Soy Sauce\", 30], [\"Sugar\", 15], [\"Flour\", 90], [\"Milk\", 6], [\"Butter (Anchor)\", 6]]', '1-2 months'),
(15, 'Dimsum & Dumplings', 'Special Pork Siomai', 'Special Pork Siomai', '30pcs/pack', 240.00, 100, '', '/uploads/products/Special_Pork_Siomai/product_image.png', '[[\"Minced Pork\", 660], [\"Shrimp\", 90], [\"Soy Sauce\", 30], [\"Sugar\", 15], [\"Hoisin Sauce\", 15], [\"Flour\", 90], [\"Butter (Anchor)\", 9]]', NULL),
(16, 'Dimsum & Dumplings', 'Regular Sharksfin Dumpling', 'Regular Sharksfin Dumpling', '30pcs/pack', 180.00, 100, '', '/uploads/products/Regular_Sharksfin_Dumpling/product_image.png', '[[\"Minced Pork\", 690], [\"Soy Sauce\", 30], [\"Sugar\", 15], [\"Flour\", 90], [\"Milk\", 9]]', '1-2 months'),
(17, 'Dimsum & Dumplings', 'Special Sharksfin Dumpling', 'Special Sharksfin Dumpling', '30pcs/pack', 260.00, 100, '', '/uploads/products/Special_Sharksfin_Dumpling/product_image.png', '[[\"Minced Pork\", 630], [\"Shrimp\", 120], [\"Soy Sauce\", 30], [\"Hoisin Sauce\", 15], [\"Flour\", 90]]', NULL),
(18, 'Dimsum & Dumplings', 'Kutchay Dumpling', 'Kutchay Dumpling', '30pcs/pack', 275.00, 100, 'Kuchay with the juicy texture of our pork filling wrapped in our very own thin and chewy dumpling dough.', '/uploads/products/Kutchay_Dumpling/product_image.png', '[[\"Minced Pork\", 600], [\"Soy Sauce\", 30], [\"Sugar\", 15], [\"Flour\", 90], [\"Butter (Anchor)\", 9]]', NULL),
(19, 'Dimsum & Dumplings', 'Chicken Siomai', 'Chicken Siomai', '30pcs/pack', 300.00, 99, 'It is commonly steamed, with a popular variant being fried, resulting in a crisp exterior.', '/uploads/products/Chicken_Siomai/product_image.png', '[[\"Chicken Diced Seasoned\", 690], [\"Soy Sauce\", 30], [\"Sugar\", 15], [\"Flour\", 90]]', '1-2 months'),
(20, 'Dimsum & Dumplings', 'Beef Siomai', 'Beef Siomai', '20pcs/pack', 250.00, 0, 'It is commonly steamed, with a popular variant being fried, resulting in a crisp exterior.', '/uploads/products/Beef_Siomai/product_image.png', '[[\"Beef Sliced Seasoned\", 500], [\"Soy Sauce\", 20], [\"Hoisin Sauce\", 10], [\"Flour\", 60], [\"Butter (Anchor)\", 4]]', '1-2 months'),
(21, 'Dimsum & Dumplings', 'Premium Pork Siomai', 'Premium Pork Siomai', '20pcs/pack', 280.00, 100, '', '/uploads/products/Premium_Pork_Siomai__A_/product_image.png', '[[\"Minced Pork\", 440], [\"Shrimp\", 60], [\"Soy Sauce\", 20], [\"Hoisin Sauce\", 10], [\"Flour\", 60]]', '1-2 months'),
(22, 'Dimsum & Dumplings', 'Premium Pork Siomai w/ Shrimp', 'Premium Pork Siomai w/ Shrimp', '20pcs/pack', 310.00, 100, '', '/uploads/products/Premium_Pork_Siomai_w__Shrimp__A_/product_image.png', '[[\"Minced Pork\", 400], [\"Shrimp\", 100], [\"Soy Sauce\", 20], [\"Hoisin Sauce\", 10], [\"Flour\", 60]]', NULL),
(23, 'Dimsum & Dumplings', 'Premium Sharksfin Dumpling', 'Premium Sharksfin Dumpling', '20pcs/pack', 300.00, 100, '', '/uploads/products/Premium_Sharksfin_Dumpling/product_image.png', '[[\"Minced Pork\", 400], [\"Shrimp\", 100], [\"Soy Sauce\", 20], [\"Flour\", 60], [\"Butter (Anchor)\", 6]]', NULL),
(24, 'Dimsum & Dumplings', 'Hakaw', 'Hakaw (Shrimp Dumpling)', '12pcs/pack', 300.00, 100, 'Made with succulent shrimp and wrapped in a delicate, translucent dough.', '/uploads/products/Hakaw__Shrimp_Dumpling___A_/product_image.png', '[[\"Shrimp\", 300], [\"Butter (Anchor)\", 3.6], [\"Flour\", 36], [\"Sugar\", 6]]', '1-2 months'),
(26, 'Dimsum & Dumplings', 'Japanese Pork Siomai', 'Japanese Pork Siomai', '20pcs/pack', 325.00, 100, 'Delicious Japanese Siomai is made of delicious steamed pork meatball wrapped in our Delicious Special Seaweeds wrapper.', '/uploads/products/Japanese_Pork_Siomai__A_/product_image.png', '[[\"Minced Pork\", 400], [\"Soy Sauce\", 20], [\"Japanese Soy Sauce\", 20], [\"Flour\", 60], [\"Sugar\", 10]]', '1-2 months'),
(27, 'Dimsum & Dumplings', 'Polonchay Dumpling', 'Polonchay Dumpling (Min 6 Packs)', '12pcs/pack', 310.00, 100, '', '/uploads/products/Polonchay_Dumpling__Min_6_Packs___A_/product_image.png', '[[\"Minced Pork\", 240], [\"Soy Sauce\", 12], [\"Flour\", 36]]', NULL),
(29, 'Dimsum & Dumplings', 'Polonchay Dumpling w/ Shrimp', 'Polonchay Dumpling w/ Shrimp (Min 6 Packs)', '12pcs/pack', 330.00, 100, '', '/uploads/products/Polonchay_Dumpling_w__Shrimp__Min_6_Packs___A_/product_image.png', '[[\"Minced Pork\", 216], [\"Shrimp\", 48], [\"Soy Sauce\", 12], [\"Flour\", 36]]', NULL),
(31, 'Dimsum & Dumplings', 'Beancurd Roll', 'Beancurd Roll', '12pcs/pack', 310.00, 114, 'A roll made by wrapping the filling with a beancurd sheet or yuba or tofu skin.', '/uploads/products/Beancurd_Roll__A_/product_image.png', '[[\"Minced Pork\",264],[\"Soy Sauce\",12],[\"Sugar\",6],[\"Veg. Spring Roll (Ham)\",36]]', '1-2 months'),
(33, 'Dimsum & Dumplings', 'Pork Gyoza Dumpling', 'Pork Gyoza Dumpling', '20pcs/pack', 390.00, 100, '', '/uploads/products/Pork_Gyoza_Dumpling__A_/product_image.png', '[[\"Minced Pork\", 440], [\"Soy Sauce\", 20], [\"Crispy Powder\", 40], [\"Flour\", 60]]', '1-2 months'),
(34, 'Dimsum & Dumplings', 'Shanghai Dumpling', 'Shanghai Dumpling', '20pcs/pack', 255.00, 100, '', '/uploads/products/Shanghai_Dumpling__A_/product_image.png', '[[\"Minced Pork\", 440], [\"Soy Sauce\", 20], [\"Sugar\", 10], [\"Flour\", 60]]', '1-2 months'),
(35, 'Dimsum & Dumplings', 'Siao Long Pao', 'Siao Long Pao', '15pcs/pack', 270.00, 100, '', '/uploads/products/Siao_Long_Pao/product_image.png', '[[\"Minced Pork\", 375], [\"Soy Sauce\", 15], [\"Hoisin Sauce\", 7.5], [\"Flour\", 45]]', NULL),
(36, 'Dimsum & Dumplings', 'Wanton Regular', 'Wanton Regular', '20pcs/pack', 315.00, 100, '', '/uploads/products/Wanton_Regular/product_image.png', '[[\"Minced Pork\", 400], [\"Soy Sauce\", 20], [\"Flour\", 60]]', '1-2 months'),
(37, 'Dimsum & Dumplings', 'Sesame Butchi Ball', 'Sesame Butchi Ball', '12pcs/pack', 185.00, 100, '', '/uploads/products/Sesame_Butchi_Ball/product_image.png', '[[\"Glutinous Rice\", 300], [\"Sugar\", 60], [\"Dark Chocolate Bar\", 48], [\"Butter (Anchor)\", 12]]', NULL),
(38, 'Dimsum & Dumplings', 'Machang', 'Machang (Hong Kong)', '6pcs/pack', 250.00, 99, '', '/uploads/products/Machang__Hong_Kong_/product_image.png', '[[\"Glutinous Rice\", 720], [\"Pork Belly (Skin On)\", 240], [\"Soy Sauce\", 18], [\"Chestnut\", 60]]', NULL),
(39, 'Dimsum & Dumplings', 'Machang w/ Chestnut', 'Machang w/ Chestnut (Min 6 Packs)', '1pc', 110.00, 100, '', '/uploads/products/Machang_w__Chestnut__Min_6_Packs_/product_image.png', '[[\"Glutinous Rice\", 130], [\"Pork Belly (Skin On)\", 40], [\"Soy Sauce\", 3], [\"Chestnut\", 20]]', NULL),
(40, 'Dimsum & Dumplings', 'Pork Rib Taosi', 'Pork Rib Taosi', '500g', 200.00, 100, '', '/uploads/products/Pork_Rib_Taosi/product_image.png', '[[\"Pork Ribs\", 500], [\"Soy Sauce\", 15], [\"Star Anise\", 5]]', '1-2 months'),
(41, 'Dimsum & Dumplings', 'Pork Spring Roll', 'Pork Spring Roll', '20pcs/pack', 320.00, 100, '', '/uploads/products/Pork_Spring_Roll/product_image.png', '[[\"Minced Pork\", 360], [\"Veg. Spring Roll (Ham)\", 60], [\"Soy Sauce\", 20]]', NULL),
(42, 'Dimsum & Dumplings', 'Chicken Feet', 'Chicken Feet', '500g', 200.00, 81, 'Most people enjoy it for the cartilage, tendons, and skin that boasts a distinctly gelatinous texture due to its high collagen content.', '/uploads/products/Chicken_Feet/product_image.png', '[[\"Chicken Feet\", 500], [\"Soy Sauce\", 10], [\"Star Anise\", 5]]', '1-2 months'),
(44, 'Dimsum & Dumplings', 'Radish Cake', 'Radish Cake 1kg', '1kg', 300.00, 100, '', '/uploads/products/Radish_Cake_1kg/product_image.png', '[[\"Minced Pork\", 200], [\"Flour\", 135], [\"Soy Sauce\", 20], [\"Sugar\", 13]]', NULL),
(46, 'Dimsum & Dumplings', 'Pumpkin Cake', 'Pumpkin Cake 1kg', '1kg', 300.00, 100, '', '/uploads/products/Pumpkin_Cake_1kg/product_image.png', '[[\"Minced Pork\", 200], [\"Flour\", 135], [\"Sugar\", 20], [\"Butter (Anchor)\", 13]]', NULL),
(47, 'Healthy Dimsum', 'Vegetable Dumpling', 'Vegetable Dumpling', '12pcs/pack', 190.00, 79, '', '/uploads/products/Vegetable_Dumpling__A_/product_image.png', '[[\"Veg. Spring Roll (Ham)\", 120], [\"Tofu\", 60], [\"Soy Sauce\", 12], [\"Flour\", 36]]', '3 months'),
(49, 'Healthy Dimsum', 'Vegetable Spring Roll', 'Vegetable Spring Roll', '12pcs/pack', 230.00, 100, '', '/uploads/products/Vegetable_Spring_Roll__A_/product_image.png', '[[\"Veg. Spring Roll (Ham)\", 144], [\"Tofu\", 48], [\"Spring Roll Wrapper\", 12]]', '3 months'),
(51, 'Sauces', 'Chili Sauce', 'Chili Sauce', '1.5kg/cntr', 590.00, 100, '', '/uploads/products/Chili_Sauce__A_/product_image.png', '[[\"Chili\", 750], [\"Garlic\", 300], [\"Vinegar\", 200], [\"Oil\", 150], [\"Sugar\", 50], [\"Salt\", 50]]', '6-12 months'),
(53, 'Sauces', 'Seafood XO Sauce', 'Seafood XO Sauce', '220g/btl', 320.00, 100, '', '/uploads/products/Seafood_XO_Sauce/product_image.png', '[[\"Dried Japanese Scallop\", 40], [\"Dried Shrimp\", 40], [\"Garlic\", 30], [\"Chili\", 20], [\"Oil\", 60], [\"Soy Sauce\", 30]]', '6-12 months'),
(54, 'Sauces', 'Lemon Sauce', 'Lemon Sauce', '420g/btl', 135.00, 0, '', '/uploads/products/Lemon_Sauce__A_/product_image.png', '[[\"Lemon\", 200], [\"Sugar\", 100], [\"Cornstarch\", 60], [\"Vinegar\", 60]]', '6-12 months'),
(55, 'Sauces', 'Sweet & Sour Sauce', 'Sweet & Sour Sauce', '420g/btl', 135.00, 100, '', '/uploads/products/Sweet___Sour_Sauce__A_/product_image.png', '[[\"Ketchup\", 150], [\"Pineapple\", 100], [\"Vinegar\", 80], [\"Sugar\", 50], [\"Cornstarch\", 40]]', '6-12 months'),
(56, 'Sauces', 'Beef Fillet Sauce', 'Beef Fillet Sauce', '420g/btl', 150.00, 100, '', '/uploads/products/Beef_Fillet_Sauce/product_image.png', '[[\"Soy Sauce\", 120], [\"Sugar\", 80], [\"Garlic\", 60], [\"Star Anise\", 20], [\"Cornstarch\", 140]]', '6-12 months'),
(59, 'Marinated Items', 'Asado Marinated', 'Asado Marinated (Char Siu)', '1kg', 400.00, 100, '', '/uploads/products/Asado_Marinated__Char_Siu_/product_image.png', '[[\"Pork Belly (Skin On)\", 1000], [\"Soy Sauce\", 50], [\"Sugar\", 40], [\"Star Anise\", 10], [\"Sausana/Chinese\", 30]]', '2 months'),
(60, 'Marinated Items', 'Asado Cooked', 'Asado Cooked (Char Siu)', '1kg', 700.00, 100, '', '/uploads/products/Asado_Cooked__Char_Siu_/product_image.png', '[[\"Pork Belly (Skin On)\", 1000], [\"Soy Sauce\", 50], [\"Sugar\", 40], [\"Star Anise\", 10], [\"Sausana/Chinese\", 30]]', '1 month'),
(61, 'Noodles & Wrappers', 'Pancit Canton', 'Pancit Canton', '2kg/pack', 350.00, 100, '', '/uploads/products/Pancit_Canton/product_image.png', NULL, '6 months'),
(62, 'Noodles & Wrappers', 'Dried Egg Noodles', 'Dried Egg Noodles', '1kg/pack', 185.00, 100, '', '/uploads/products/Dried_Egg_Noodles/product_image.png', NULL, '6 months'),
(63, 'Noodles & Wrappers', 'Hongkong Noodles', 'Hongkong Noodles (Yellow/White)', '1kg/pack', 185.00, 100, '', '/uploads/products/Hongkong_Noodles__Yellow_White_/product_image.png', NULL, '6 months'),
(64, 'Noodles & Wrappers', 'Shanghai Noodles', 'Shanghai Noodles (Yellow/White)', '2kg/pack', 360.00, 100, '', '/uploads/products/Shanghai_Noodles__Yellow_White_/product_image.png', NULL, '6 months'),
(65, 'Noodles & Wrappers', 'Hofan Noodles', 'Hofan Noodles (Minimum 6 packs)', '1kg/pack', 170.00, 100, '', '/uploads/products/Hofan_Noodles__Minimum_6_packs_/product_image.png', NULL, '6 months'),
(66, 'Noodles & Wrappers', 'Ramen Noodles', 'Ramen Noodles', '1kg/pack', 195.00, 100, '', '/uploads/products/Ramen_Noodles/product_image.png', NULL, '6 months'),
(67, 'Noodles & Wrappers', 'Spinach Noodles', 'Spinach Noodles (Minimum 6 packs)', '1kg/pack', 195.00, 100, '', '/uploads/products/Spinach_Noodles__Minimum_6_packs_/product_image.png', NULL, '6 months'),
(68, 'Noodles & Wrappers', 'Siomai Wrapper', 'Siomai Wrapper (Yellow/White)', '250g/pack', 70.00, 99, '', '/uploads/products/Siomai_Wrapper__Yellow_White_/product_image.png', NULL, '3 months'),
(69, 'Noodles & Wrappers', 'Wanton Wrapper', 'Wanton Wrapper (Yellow/White)', '250g/pack', 70.00, 100, '', '/uploads/products/Wanton_Wrapper__Yellow_White_/product_image.png', NULL, '3 months'),
(70, 'Noodles & Wrappers', 'Beancurd Wrapper', 'Beancurd Wrapper', '1kg/pack', 1600.00, 0, '', '/uploads/products/Beancurd_Wrapper/product_image.png', NULL, '3 months'),
(71, 'Noodles & Wrappers', 'Spring Roll Wrapper', 'Spring Roll Wrapper', '25pcs/pack', 90.00, 100, '', '/uploads/products/Spring_Roll_Wrapper/product_image.png', NULL, '3 months'),
(72, 'Noodles & Wrappers', 'Gyoza Wrapper', 'Gyoza Wrapper (Minimum 10 Packs)', '250g/pack', 70.00, 100, '', '/uploads/products/Gyoza_Wrapper__Minimum_10_Packs_/product_image.png', NULL, '3 months'),
(164, 'Dimsum & Dumplings', 'Chocolate Xiao Long Bao', 'Chocolate', '12pcs/pack', 250.00, 8, 'Delicious SLB', '/uploads/products/Chocolate/product_image.png', NULL, NULL);

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
(14, 'Pork Belly (Skinless)', 2200.00, '2025-04-07 16:57:58', '2025-05-05 07:21:24'),
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
(36, 'Beef Sliced Seasoned', 8300.00, '2025-04-07 16:57:58', '2025-05-01 20:56:34'),
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
(52, 'Soy Sauce', 98250.00, '2025-04-07 16:57:58', '2025-05-01 20:56:34'),
(53, 'Japanese Soy Sauce', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:26'),
(54, 'Hoisin Sauce', 7932.00, '2025-04-07 16:57:58', '2025-05-01 20:56:34'),
(55, 'Star Anise', 237.00, '2025-04-07 16:57:58', '2025-04-22 04:10:21'),
(56, 'Butter (Anchor)', 7404.00, '2025-04-07 16:57:58', '2025-05-01 20:56:34'),
(57, 'Margarine Buttercup (Buttercup)', 10500.00, '2025-04-07 16:57:58', '2025-05-05 07:21:12'),
(58, 'Cheese Quickmelt (Magnolia)', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:35:48'),
(59, 'Cheese Unsalted (Magnolia)', 10400.00, '2025-04-07 16:57:58', '2025-04-15 12:35:50'),
(60, 'Cheese (Eden)', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:35:47'),
(61, 'Dark Chocolate Bar', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:10'),
(62, 'Flour', 130820.00, '2025-04-07 16:57:58', '2025-05-01 20:56:34'),
(63, 'Sugar', 96771.00, '2025-04-07 16:57:58', '2025-04-30 08:26:44'),
(64, 'Yeast', 99450.00, '2025-04-07 16:57:58', '2025-04-26 06:50:41'),
(65, 'Baking Powder', 9649.50, '2025-04-07 16:57:58', '2025-04-26 06:50:41'),
(66, 'Milk', 6769.02, '2025-04-07 16:57:58', '2025-05-05 07:21:09'),
(67, 'Glutinous Rice', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:21'),
(68, 'Chestnut', 10400.00, '2025-04-07 16:57:58', '2025-04-15 12:35:51'),
(69, 'Tofu', 99500.00, '2025-04-07 16:57:58', '2025-04-26 06:50:41'),
(70, 'Chili', 10400.00, '2025-04-07 16:57:58', '2025-04-15 12:36:02'),
(71, 'Garlic', 10300.00, '2025-04-07 16:57:58', '2025-04-15 12:36:19'),
(72, 'Lemon', 210300.00, '2025-04-07 16:57:58', '2025-05-05 07:21:16'),
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
(1, 'Super Admin', 'active', 'Accounts - Admin, Accounts - Clients, User Roles, Dashboard, Inventory, Raw Materials, Deliverable Orders, Order History, Orders, Payment History, Department Forecast, Forecast, Drivers, Reporting'),
(2, 'Manager', 'active', 'Accounts - Clients, Drivers, Dashboard, Order History, Payment History, Department Forecast, Forecast'),
(3, 'Secretary', 'active', 'Dashboard, Inventory, Order History, Orders, Payment History, Raw Materials, Deliverable Orders'),
(4, 'Accountant', 'active', 'Dashboard, Payment History, Reporting'),
(38, 'Staff', 'active', 'Dashboard, Inventory, Order History, Orders, Raw Materials, Deliverable Orders'),
(39, 'Owner', 'active', 'Drivers, Dashboard, Inventory, Raw Materials, Deliverable Orders, Order History, Orders, Payment History, Department Forecast, Forecast');

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
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `username_2` (`username`);

--
-- Indexes for table `driver_assignments`
--
ALTER TABLE `driver_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `driver_id` (`driver_id`);

--
-- Indexes for table `email_notifications`
--
ALTER TABLE `email_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_number` (`po_number`),
  ADD KEY `email_type` (`email_type`),
  ADD KEY `sent_status` (`sent_status`);

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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `balance_history`
--
ALTER TABLE `balance_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `clients_accounts`
--
ALTER TABLE `clients_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `driver_assignments`
--
ALTER TABLE `driver_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `email_notifications`
--
ALTER TABLE `email_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=147;

--
-- AUTO_INCREMENT for table `manufacturing_logs`
--
ALTER TABLE `manufacturing_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `monthly_payments`
--
ALTER TABLE `monthly_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=833;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=203;

--
-- AUTO_INCREMENT for table `order_status_logs`
--
ALTER TABLE `order_status_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `page_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `payment_history`
--
ALTER TABLE `payment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `payment_status_history`
--
ALTER TABLE `payment_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=167;

--
-- AUTO_INCREMENT for table `raw_materials`
--
ALTER TABLE `raw_materials`
  MODIFY `material_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=160;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
