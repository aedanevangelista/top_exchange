-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 29, 2025 at 07:07 AM
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
  `role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`id`, `username`, `password`, `created_at`, `role`, `status`) VALUES
(56, 'admin', '123', '2025-03-05 22:58:17', 'Admin', 'Active'),
(57, 'Secretary', '123', '2025-03-05 23:17:38', 'Secretary', 'Active'),
(58, 'aedan', '123', '2025-03-05 23:23:25', 'aed', 'Active'),
(60, 'Manager', '123', '2025-03-05 23:27:49', 'Manager', 'Archived'),
(61, 'Accountant', '123', '2025-03-05 23:27:55', 'Accountant', 'Archived'),
(62, 'Ryan', '123', '2025-03-09 07:14:07', 'Admin', 'Archived');

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
(1, 'Jeff Santonia', 50000.00, '', 'admin', '2025-03-24 05:17:00'),
(2, 'Jeff Santonia', 5000.00, '', 'admin', '2025-03-24 11:30:04'),
(3, 'Jeff Santonia', 25000.00, '', 'admin', '2025-03-26 03:02:36'),
(4, 'Jeff Santonia', 500000.00, '', 'admin', '2025-03-26 04:02:33');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients_accounts`
--

INSERT INTO `clients_accounts` (`id`, `username`, `password`, `email`, `phone`, `region`, `city`, `company`, `company_address`, `business_proof`, `status`, `balance`, `created_at`) VALUES
(3, 'Jeff Santonia', '$2y$10$dwjDK/6QbkEF.qBuozhjneWerFL6jY4qyZ8hchngxdbNZ3k/u80vm', 'jeffsantonia@gmail.com', '1236969420', 'Munoz', 'Quezon City', 'Jeff Company', 'Jeff City', '[\"\\/top_exchange\\/uploads\\/Jeff Santonia\\/3.png\",\"\\/top_exchange\\/uploads\\/Jeff Santonia\\/4.png\"]', 'Active', 473812.00, '2025-03-09 14:12:20'),
(4, 'joe', '$2y$10$0kM1rjCbnDXkL4/.BSrvEuVCSMjLN/ICY5KeSmzJ0wQ0aPQEsyQwe', 'joemama@gmail.com', '123123123', 'Metro Manila', 'QC', 'Joe Mama Corp', 'Joe mama address', '[\"\\/top_exchange\\/uploads\\/joe\\/audience2.png\"]', 'Inactive', 0.00, '2025-03-20 16:08:02'),
(5, 'asdas', '$2y$10$dMplMlvggRnx7M8ln/AjfOkQHL.Ulbj8W.tU7nvAs2KkCGbXZJkge', 'asdsas@g.com', 'asdasdasd', 'asdasas', 'dasasd', 'asdas', 'asdasdas', '[\"\\/top_exchange\\/uploads\\/asdas\\/audience.png\",\"\\/top_exchange\\/uploads\\/asdas\\/audience2.png\",\"\\/top_exchange\\/uploads\\/asdas\\/file copie.png\"]', 'Inactive', 0.00, '2025-03-20 16:09:21'),
(7, 'Boters', '$2y$10$g8CvqtZ45IFW8QiCk1V4/OoeNq0LJT4sZlshs.WFrGQpX/hwbdsFa', 'jefferson_santonia@yahoo.com', '09185585149', 'asd', 'asd', '', '', '[]', 'Active', 0.00, '2025-03-27 12:36:22');

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

--
-- Dumping data for table `monthly_payments`
--

INSERT INTO `monthly_payments` (`id`, `username`, `month`, `year`, `total_amount`, `payment_status`, `created_at`, `updated_at`, `remaining_balance`, `proof_image`, `payment_method`, `payment_type`) VALUES
(1, 'aedan', 1, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(2, 'aedan', 2, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(3, 'aedan', 3, 2025, 1625.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-23 11:45:21', 1625.00, NULL, NULL, NULL),
(4, 'aedan', 4, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(5, 'aedan', 5, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(6, 'aedan', 6, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(7, 'aedan', 7, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(8, 'aedan', 8, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(9, 'aedan', 9, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(10, 'aedan', 10, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(11, 'aedan', 11, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(12, 'aedan', 12, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(13, 'asdasd', 1, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(14, 'asdasd', 2, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(15, 'asdasd', 3, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(16, 'asdasd', 4, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(17, 'asdasd', 5, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(18, 'asdasd', 6, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(19, 'asdasd', 7, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(20, 'asdasd', 8, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(21, 'asdasd', 9, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(22, 'asdasd', 10, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(23, 'asdasd', 11, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(24, 'asdasd', 12, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(25, 'asdasd', 1, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(26, 'asdasd', 2, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(27, 'asdasd', 3, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(28, 'asdasd', 4, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(29, 'asdasd', 5, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(30, 'asdasd', 6, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(31, 'asdasd', 7, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(32, 'asdasd', 8, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(33, 'asdasd', 9, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(34, 'asdasd', 10, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(35, 'asdasd', 11, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(36, 'asdasd', 12, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(145, 'Jeff Santonia', 1, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(146, 'Jeff Santonia', 2, 2025, 15400.00, 'Fully Paid', '2025-03-19 18:05:51', '2025-03-26 04:05:35', 0.00, 'payment_1742961760.png', NULL, 'Internal'),
(147, 'Jeff Santonia', 3, 2025, 4795.00, 'Fully Paid', '2025-03-19 18:05:51', '2025-03-26 15:09:42', 0.00, 'payment_1743001774.png', NULL, 'External'),
(148, 'Jeff Santonia', 4, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(149, 'Jeff Santonia', 5, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(150, 'Jeff Santonia', 6, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(151, 'Jeff Santonia', 7, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(152, 'Jeff Santonia', 8, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(153, 'Jeff Santonia', 9, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(154, 'Jeff Santonia', 10, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(155, 'Jeff Santonia', 11, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(156, 'Jeff Santonia', 12, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(769, 'asdas', 1, 2025, 0.00, 'Unpaid', '2025-03-22 18:47:30', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(770, 'asdas', 2, 2025, 0.00, 'Unpaid', '2025-03-22 18:47:30', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(771, 'asdas', 3, 2025, 0.00, 'Unpaid', '2025-03-22 18:47:30', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(772, 'asdas', 4, 2025, 0.00, 'Unpaid', '2025-03-22 18:47:30', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(773, 'asdas', 5, 2025, 0.00, 'Unpaid', '2025-03-22 18:47:30', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(774, 'asdas', 6, 2025, 0.00, 'Unpaid', '2025-03-22 18:47:30', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(775, 'asdas', 7, 2025, 0.00, 'Unpaid', '2025-03-22 18:47:30', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(776, 'asdas', 8, 2025, 0.00, 'Unpaid', '2025-03-22 18:47:30', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(777, 'asdas', 9, 2025, 0.00, 'Unpaid', '2025-03-22 18:47:30', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(778, 'asdas', 10, 2025, 0.00, 'Unpaid', '2025-03-22 18:47:30', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(779, 'asdas', 11, 2025, 0.00, 'Unpaid', '2025-03-22 18:47:30', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL),
(780, 'asdas', 12, 2025, 0.00, 'Unpaid', '2025-03-22 18:47:30', '2025-03-23 11:45:21', 0.00, NULL, NULL, NULL);

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
(17, 'aedan-1', 'aedan', '2024-03-20', '2024-03-21', NULL, '[{\"product_id\":2,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Med)\",\"packaging\":\"10pcs/pack\",\"price\":325,\"quantity\":5}]', 1625.00, 'Completed', NULL, NULL, NULL, 0.00, 0.00),
(18, 'Jeff Santonia-1', 'Jeff Santonia', '2025-03-20', '2025-03-21', NULL, '[{\"product_id\":6,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (B Small)\",\"packaging\":\"15pcs/pack\",\"price\":205,\"quantity\":10}]', 2050.00, 'Completed', NULL, NULL, NULL, 0.00, 0.00),
(19, 'aedan-2', 'aedan', '2025-03-20', '2025-03-21', NULL, '[{\"product_id\":1,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Large)\",\"packaging\":\"6pcs/pack\",\"price\":280,\"quantity\":5}]', 1400.00, 'Completed', NULL, NULL, NULL, 0.00, 0.00),
(20, 'aedan-3', 'aedan', '2025-03-20', '2025-03-21', NULL, '[{\"product_id\":2,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Med)\",\"packaging\":\"10pcs/pack\",\"price\":325,\"quantity\":10}]', 3250.00, 'Completed', NULL, NULL, NULL, 0.00, 0.00),
(21, 'aedan-4', 'aedan', '2025-03-20', '2025-03-21', NULL, '[{\"product_id\":2,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Med)\",\"packaging\":\"10pcs/pack\",\"price\":325,\"quantity\":12}]', 3900.00, 'Completed', NULL, NULL, NULL, 0.00, 0.00),
(22, 'aedan-5', 'aedan', '2025-03-20', '2025-03-21', NULL, '[{\"product_id\":5,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (B Med)\",\"packaging\":\"10pcs/pack\",\"price\":250,\"quantity\":2}]', 500.00, 'Completed', NULL, NULL, NULL, 0.00, 0.00),
(23, 'Jeff Santonia-2', 'Jeff Santonia', '2025-03-20', '2025-03-21', NULL, '[{\"product_id\":1,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Large)\",\"packaging\":\"6pcs/pack\",\"price\":280,\"quantity\":4}]', 1120.00, 'Completed', NULL, NULL, NULL, 0.00, 0.00),
(24, 'Jeff Santonia-3', 'Jeff Santonia', '2025-03-20', '2025-03-21', NULL, '[{\"product_id\":2,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Med)\",\"packaging\":\"10pcs/pack\",\"price\":325,\"quantity\":5}]', 1625.00, 'Completed', NULL, NULL, NULL, 0.00, 0.00),
(25, 'aedan-6', 'aedan', '2025-03-20', '2025-03-21', NULL, '[{\"product_id\":2,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Med)\",\"packaging\":\"10pcs/pack\",\"price\":325,\"quantity\":5}]', 1625.00, 'Active', NULL, NULL, NULL, 0.00, 0.00),
(26, 'aedan-7', 'aedan', '2025-03-21', '2025-03-28', NULL, '[{\"product_id\":73,\"category\":\"Dimsum & Dumplings\",\"item_description\":\"shumai\",\"packaging\":123,\"price\":123,\"quantity\":5}]', 615.00, 'Active', NULL, NULL, NULL, 0.00, 0.00),
(27, 'asdasd-1', 'asdasd', '2025-03-21', '2025-03-24', NULL, '[{\"product_id\":1,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Large)\",\"packaging\":\"6pcs/pack\",\"price\":280,\"quantity\":5}]', 1400.00, 'Active', NULL, NULL, NULL, 0.00, 0.00),
(28, 'Jeff Santonia-4', 'Jeff Santonia', '2025-02-19', '2025-02-26', NULL, '[{\"product_id\":1,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Large)\",\"packaging\":\"6pcs/pack\",\"price\":280,\"quantity\":55}]', 15400.00, 'Completed', NULL, NULL, NULL, 0.00, 0.00),
(29, 'Jeff Santonia-5', 'Jeff Santonia', '2025-03-24', '2025-03-26', NULL, '[{\"product_id\":2,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Med)\",\"packaging\":\"10pcs/pack\",\"price\":325,\"quantity\":55}]', 17875.00, 'Active', NULL, NULL, NULL, 0.00, 0.00),
(30, 'Jeff Santonia-6', 'Jeff Santonia', '2025-03-24', '2025-03-26', 'Siomai Jeff Address 123', '[{\"product_id\":1,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Large)\",\"packaging\":\"6pcs/pack\",\"price\":280,\"quantity\":5}]', 1400.00, 'Completed', NULL, NULL, NULL, 0.00, 0.00),
(31, 'Jeff Santonia-7', 'Jeff Santonia', '2025-03-24', '2025-03-26', 'Jeff City', '[{\"product_id\":1,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Large)\",\"packaging\":\"6pcs/pack\",\"price\":280,\"quantity\":5}]', 1400.00, 'Active', NULL, NULL, NULL, 0.00, 0.00),
(32, 'admin-1', 'admin', '2025-03-27', '2025-03-28', 'asddsa', '[{\"product_id\":2,\"category\":\"\",\"item_description\":\"Asado Siopao (A Med)\",\"packaging\":\"10pcs\\/pack\",\"price\":\"325.00\",\"quantity\":1},{\"product_id\":3,\"category\":\"\",\"item_description\":\"Asado Siopao (A Small)\",\"packaging\":\"15pcs\\/pack\",\"price\":\"270.00\",\"quantity\":1},{\"product_id\":1,\"category\":\"\",\"item_description\":\"Asado Siopao (A Large)\",\"packaging\":\"6pcs\\/pack\",\"price\":\"280.00\",\"quantity\":1}]', 875.00, 'Pending', '09185585149', '', 'Cash on Delivery', 875.00, 0.00),
(33, 'Boters-1', 'Boters', '2025-03-27', '2025-03-28', 'ASADS', '[{\"product_id\":3,\"category\":\"\",\"item_description\":\"Asado Siopao (A Small)\",\"packaging\":\"15pcs\\/pack\",\"price\":\"270.00\",\"quantity\":1},{\"product_id\":2,\"category\":\"\",\"item_description\":\"Asado Siopao (A Med)\",\"packaging\":\"10pcs\\/pack\",\"price\":\"325.00\",\"quantity\":1},{\"product_id\":1,\"category\":\"\",\"item_description\":\"Asado Siopao (A Large)\",\"packaging\":\"6pcs\\/pack\",\"price\":\"280.00\",\"quantity\":1}]', 875.00, 'Pending', '09185585149', '', 'Cash on Delivery', 875.00, 0.00);

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

--
-- Dumping data for table `payment_history`
--

INSERT INTO `payment_history` (`id`, `username`, `month`, `year`, `amount`, `notes`, `proof_image`, `created_by`, `created_at`, `payment_type`) VALUES
(20, 'Jeff Santonia', 3, 2025, 4795.00, NULL, 'payment_1743001774.png', 'admin', '2025-03-26 15:09:34', 'External');

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
(1, 'Jeff Santonia', 3, 2025, 'For Approval', 'Paid', 'admin', '2025-03-24 06:00:42'),
(2, 'Jeff Santonia', 3, 2025, 'Paid', 'For Approval', 'admin', '2025-03-24 06:03:00'),
(3, 'Jeff Santonia', 3, 2025, 'For Approval', 'Paid', 'admin', '2025-03-24 06:03:03'),
(4, 'Jeff Santonia', 3, 2025, 'Paid', 'Unpaid', 'admin', '2025-03-24 06:03:05'),
(5, 'Jeff Santonia', 3, 2025, 'Unpaid', 'For Approval', 'admin', '2025-03-24 06:03:06'),
(6, 'Jeff Santonia', 3, 2025, 'For Approval', 'Paid', 'admin', '2025-03-24 06:22:37'),
(7, 'Jeff Santonia', 3, 2025, 'Paid', 'Unpaid', 'admin', '2025-03-24 11:29:25'),
(8, 'Jeff Santonia', 3, 2025, 'For Approval', 'Paid', 'admin', '2025-03-24 11:31:27'),
(9, 'Jeff Santonia', 3, 2025, 'Paid', 'Unpaid', 'admin', '2025-03-24 11:39:57'),
(10, 'Jeff Santonia', 3, 2025, 'For Approval', 'Partially Paid', 'admin', '2025-03-26 04:06:01'),
(11, 'Jeff Santonia', 3, 2025, 'For Approval', 'Fully Paid', 'admin', '2025-03-26 14:24:30'),
(12, 'Jeff Santonia', 3, 2025, 'Fully Paid', 'Unpaid', 'admin', '2025-03-26 14:50:47'),
(13, 'Jeff Santonia', 3, 2025, 'For Approval', 'Partially Paid', 'admin', '2025-03-26 14:50:59'),
(14, 'Jeff Santonia', 3, 2025, 'Fully Paid', 'Unpaid', 'admin', '2025-03-26 15:09:30'),
(15, 'Jeff Santonia', 3, 2025, 'For Approval', 'Fully Paid', 'admin', '2025-03-26 15:09:42');

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
  `product_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `category`, `product_name`, `item_description`, `packaging`, `price`, `stock_quantity`, `additional_description`, `product_image`) VALUES
(1, 'Siopao', 'Asado Siopao', 'Asado Siopao (A Large)', '6pcs/pack', 280.00, 100, NULL, NULL),
(2, 'Siopao', 'Asado Siopao', 'Asado Siopao (A Med)', '10pcs/pack', 325.00, 100, NULL, NULL),
(3, 'Siopao', 'Asado Siopao', 'Asado Siopao (A Small)', '15pcs/pack', 270.00, 100, NULL, NULL),
(4, 'Siopao', 'Asado Siopao', 'Asado Siopao (B Large)', '6pcs/pack', 235.00, 100, NULL, NULL),
(5, 'Siopao', 'Asado Siopao', 'Asado Siopao (B Med)', '10pcs/pack', 250.00, 100, NULL, NULL),
(6, 'Siopao', 'Asado Siopao', 'Asado Siopao (B Small)', '15pcs/pack', 205.00, 100, NULL, NULL),
(7, 'Siopao', 'Bola Bola Siopao', 'Bola Bola Siopao (Large)', '6pcs/pack', 310.00, 100, NULL, NULL),
(8, 'Siopao', 'Bola Bola Siopao', 'Bola Bola Siopao (Med)', '10pcs/pack', 350.00, 100, NULL, NULL),
(9, 'Siopao', 'Bola Bola Siopao', 'Bola Bola Siopao (Small)', '15pcs/pack', 290.00, 100, NULL, NULL),
(10, 'Siopao', 'Jumbo Pao', 'Jumbo Pao', '4pcs/pack', 325.00, 100, NULL, NULL),
(11, 'Siopao', 'Cuaopao', 'Cuaopao', '10pcs/pack', 125.00, 0, NULL, NULL),
(12, 'Siopao', 'Minibun Mantao', 'Minibun Mantao', '12pcs/pack', 115.00, 0, NULL, NULL),
(13, 'Siopao', 'Egg Custard Pao', 'Egg Custard Pao (Min 10 packs)', '8pcs/pack', 150.00, 0, NULL, NULL),
(14, 'Dimsum & Dumplings', 'Regular Pork Siomai', 'Regular Pork Siomai', '30pcs/pack', 145.00, 0, '', '/top_exchange/uploads/products/Regular_Pork_Siomai/product_image.png'),
(15, 'Dimsum & Dumplings', 'Special Pork Siomai', 'Special Pork Siomai', '30pcs/pack', 240.00, 0, '', '/top_exchange/uploads/products/Special_Pork_Siomai/product_image.png'),
(16, 'Dimsum & Dumplings', 'Regular Sharksfin Dumpling', 'Regular Sharksfin Dumpling', '30pcs/pack', 180.00, 0, '', '/top_exchange/uploads/products/Regular_Sharksfin_Dumpling/product_image.png'),
(17, 'Dimsum & Dumplings', 'Special Sharksfin Dumpling', 'Special Sharksfin Dumpling', '30pcs/pack', 260.00, 0, '', '/top_exchange/uploads/products/Special_Sharksfin_Dumpling/product_image.png'),
(18, 'Dimsum & Dumplings', 'Kutchay Dumpling', 'Kutchay Dumpling', '30pcs/pack', 275.00, 0, '', '/top_exchange/uploads/products/Kutchay_Dumpling/product_image.png'),
(19, 'Dimsum & Dumplings', 'Chicken Siomai', 'Chicken Siomai', '30pcs/pack', 300.00, 0, '0', '/top_exchange/uploads/products/Chicken_Siomai/product_image.png'),
(20, 'Dimsum & Dumplings', 'Beef Siomai', 'Beef Siomai', '20pcs/pack', 250.00, 0, '0', '/top_exchange/uploads/products/Beef_Siomai/product_image.png'),
(21, 'Dimsum & Dumplings', 'Premium Pork Siomai', 'Premium Pork Siomai (A)', '20pcs/pack', 280.00, 0, '', '/top_exchange/uploads/products/Premium_Pork_Siomai__A_/product_image.png'),
(22, 'Dimsum & Dumplings', 'Premium Pork Siomai w/ Shrimp', 'Premium Pork Siomai w/ Shrimp (A)', '20pcs/pack', 310.00, 0, '', '/top_exchange/uploads/products/Premium_Pork_Siomai_w__Shrimp__A_/product_image.png'),
(23, 'Dimsum & Dumplings', 'Premium Sharksfin Dumpling', 'Premium Sharksfin Dumpling', '20pcs/pack', 300.00, 0, '', '/top_exchange/uploads/products/Premium_Sharksfin_Dumpling/product_image.png'),
(24, 'Dimsum & Dumplings', 'Hakaw', 'Hakaw (Shrimp Dumpling) (A)', '12pcs/pack', 300.00, 0, '0', '/top_exchange/uploads/products/Hakaw__Shrimp_Dumpling___A_/product_image.png'),
(25, 'Dimsum & Dumplings', 'Hakaw', 'Hakaw (Shrimp Dumpling) (B)', '20pcs/pack', 480.00, 0, '0', '/top_exchange/uploads/products/Hakaw__Shrimp_Dumpling___B_/product_image.png'),
(26, 'Dimsum & Dumplings', 'Japanese Pork Siomai', 'Japanese Pork Siomai (A)', '20pcs/pack', 325.00, 0, '', '/top_exchange/uploads/products/Japanese_Pork_Siomai__A_/product_image.png'),
(27, 'Dimsum & Dumplings', 'Polonchay Dumpling', 'Polonchay Dumpling (Min 6 Packs) (A)', '12pcs/pack', 310.00, 0, '', '/top_exchange/uploads/products/Polonchay_Dumpling__Min_6_Packs___A_/product_image.png'),
(28, 'Dimsum & Dumplings', 'Polonchay Dumpling', 'Polonchay Dumpling (Min 6 Packs) (B)', '20pcs/pack', 470.00, 0, '', '/top_exchange/uploads/products/Polonchay_Dumpling__Min_6_Packs___B_/product_image.png'),
(29, 'Dimsum & Dumplings', 'Polonchay Dumpling w/ Shrimp', 'Polonchay Dumpling w/ Shrimp (Min 6 Packs) (A)', '12pcs/pack', 330.00, 0, '', '/top_exchange/uploads/products/Polonchay_Dumpling_w__Shrimp__Min_6_Packs___A_/product_image.png'),
(30, 'Dimsum & Dumplings', 'Polonchay Dumpling w/ Shrimp', 'Polonchay Dumpling w/ Shrimp (Min 6 Packs) (B)', '20pcs/pack', 530.00, 0, '', '/top_exchange/uploads/products/Polonchay_Dumpling_w__Shrimp__Min_6_Packs___B_/product_image.png'),
(31, 'Dimsum & Dumplings', 'Beancurd Roll', 'Beancurd Roll (A)', '12pcs/pack', 310.00, 685, '0', '/top_exchange/uploads/products/Beancurd_Roll__A_/product_image.png'),
(32, 'Dimsum & Dumplings', 'Beancurd Roll', 'Beancurd Roll (B)', '20pcs/pack', 500.00, 0, '0', '/top_exchange/uploads/products/Beancurd_Roll__B_/product_image.png'),
(33, 'Dimsum & Dumplings', 'Pork Gyoza Dumpling', 'Pork Gyoza Dumpling (A)', '20pcs/pack', 390.00, 0, '', '/top_exchange/uploads/products/Pork_Gyoza_Dumpling__A_/product_image.png'),
(34, 'Dimsum & Dumplings', 'Shanghai Dumpling', 'Shanghai Dumpling (A)', '20pcs/pack', 255.00, 0, '', '/top_exchange/uploads/products/Shanghai_Dumpling__A_/product_image.png'),
(35, 'Dimsum & Dumplings', 'Siao Long Pao', 'Siao Long Pao', '15pcs/pack', 270.00, 0, '', '/top_exchange/uploads/products/Siao_Long_Pao/product_image.png'),
(36, 'Dimsum & Dumplings', 'Wanton Regular', 'Wanton Regular', '20pcs/pack', 315.00, 0, '', '/top_exchange/uploads/products/Wanton_Regular/product_image.png'),
(37, 'Dimsum & Dumplings', 'Sesame Butchi Ball', 'Sesame Butchi Ball', '12pcs/pack', 185.00, 0, '', '/top_exchange/uploads/products/Sesame_Butchi_Ball/product_image.png'),
(38, 'Dimsum & Dumplings', 'Machang', 'Machang (Hong Kong)', '6pcs/pack', 250.00, 0, '', '/top_exchange/uploads/products/Machang__Hong_Kong_/product_image.png'),
(39, 'Dimsum & Dumplings', 'Machang w/ Chestnut', 'Machang w/ Chestnut (Min 6 Packs)', '1pc', 110.00, 0, '', '/top_exchange/uploads/products/Machang_w__Chestnut__Min_6_Packs_/product_image.png'),
(40, 'Dimsum & Dumplings', 'Pork Rib Taosi', 'Pork Rib Taosi', '500g', 200.00, 0, '', '/top_exchange/uploads/products/Pork_Rib_Taosi/product_image.png'),
(41, 'Dimsum & Dumplings', 'Pork Spring Roll', 'Pork Spring Roll', '20pcs/pack', 320.00, 0, '', '/top_exchange/uploads/products/Pork_Spring_Roll/product_image.png'),
(42, 'Dimsum & Dumplings', 'Chicken Feet', 'Chicken Feet', '500g', 200.00, 0, '0', '/top_exchange/uploads/products/Chicken_Feet/product_image.png'),
(43, 'Dimsum & Dumplings', 'Radish Cake 1.5kg', 'Radish Cake 1.5kg', '1.5kg', 370.00, 0, '', '/top_exchange/uploads/products/Radish_Cake_1_5kg/product_image.png'),
(44, 'Dimsum & Dumplings', 'Radish Cake 1kg', 'Radish Cake 1kg', '1kg', 300.00, 0, '', '/top_exchange/uploads/products/Radish_Cake_1kg/product_image.png'),
(45, 'Dimsum & Dumplings', 'Pumpkin Cake 1.5kg', 'Pumpkin Cake 1.5kg', '1.5kg', 370.00, 0, '', '/top_exchange/uploads/products/Pumpkin_Cake_1_5kg/product_image.png'),
(46, 'Dimsum & Dumplings', 'Pumpkin Cake 1kg', 'Pumpkin Cake 1kg', '1kg', 300.00, 0, '', '/top_exchange/uploads/products/Pumpkin_Cake_1kg/product_image.png'),
(47, 'Healthy Dimsum', 'Vegetable Dumpling', 'Vegetable Dumpling (A)', '12pcs/pack', 190.00, 0, '', '/top_exchange/uploads/products/Vegetable_Dumpling__A_/product_image.png'),
(48, 'Healthy Dimsum', 'Vegetable Dumpling', 'Vegetable Dumpling (B)', '20pcs/pack', 300.00, 0, '', '/top_exchange/uploads/products/Vegetable_Dumpling__B_/product_image.png'),
(49, 'Healthy Dimsum', 'Vegetable Spring Roll', 'Vegetable Spring Roll (A)', '12pcs/pack', 230.00, 0, '', '/top_exchange/uploads/products/Vegetable_Spring_Roll__A_/product_image.png'),
(50, 'Healthy Dimsum', 'Vegetable Spring Roll', 'Vegetable Spring Roll (B)', '20pcs/pack', 360.00, 0, '', '/top_exchange/uploads/products/Vegetable_Spring_Roll__B_/product_image.png'),
(51, 'Sauces', 'Chili Sauce', 'Chili Sauce (A)', '1.5kg/cntr', 590.00, 0, '0', '/top_exchange/uploads/products/Chili_Sauce__A_/product_image.png'),
(52, 'Sauces', 'Chili Sauce', 'Chili Sauce (B)', '220g/btl', 160.00, 0, '', '/top_exchange/uploads/products/Chili_Sauce__B_/product_image.png'),
(53, 'Sauces', 'Seafood XO Sauce', 'Seafood XO Sauce', '220g/btl', 320.00, 0, '', '/top_exchange/uploads/products/Seafood_XO_Sauce/product_image.png'),
(54, 'Sauces', 'Lemon Sauce', 'Lemon Sauce (A)', '420g/btl', 135.00, 0, '', '/top_exchange/uploads/products/Lemon_Sauce__A_/product_image.png'),
(55, 'Sauces', 'Sweet & Sour Sauce', 'Sweet & Sour Sauce (A)', '420g/btl', 135.00, 0, '', '/top_exchange/uploads/products/Sweet___Sour_Sauce__A_/product_image.png'),
(56, 'Sauces', 'Beef Fillet Sauce', 'Beef Fillet Sauce', '420g/btl', 150.00, 0, NULL, NULL),
(57, 'Sauces', 'Lemon Sauce', 'Lemon Sauce (B)', '3.5kg/Gal', 620.00, 0, '', '/top_exchange/uploads/products/Lemon_Sauce__B_/product_image.png'),
(58, 'Sauces', 'Sweet & Sour Sauce', 'Sweet & Sour Sauce (B)', '3.5kg/Gal', 620.00, 0, '', '/top_exchange/uploads/products/Sweet___Sour_Sauce__B_/product_image.png'),
(59, 'Marinated Items', 'Asado Marinated', 'Asado Marinated (Char Siu)', '1kg', 400.00, 0, NULL, NULL),
(60, 'Marinated Items', 'Asado Cooked', 'Asado Cooked (Char Siu)', '1kg', 700.00, 0, NULL, NULL),
(61, 'Noodles & Wrappers', 'Pancit Canton', 'Pancit Canton', '2kg/pack', 350.00, 0, NULL, NULL),
(62, 'Noodles & Wrappers', 'Dried Egg Noodles', 'Dried Egg Noodles', '1kg/pack', 185.00, 0, NULL, NULL),
(63, 'Noodles & Wrappers', 'Hongkong Noodles', 'Hongkong Noodles (Yellow/White)', '1kg/pack', 185.00, 0, NULL, NULL),
(64, 'Noodles & Wrappers', 'Shanghai Noodles', 'Shanghai Noodles (Yellow/White)', '2kg/pack', 360.00, 0, NULL, NULL),
(65, 'Noodles & Wrappers', 'Hofan Noodles', 'Hofan Noodles (Minimum 6 packs)', '1kg/pack', 170.00, 0, NULL, NULL),
(66, 'Noodles & Wrappers', 'Ramen Noodles', 'Ramen Noodles', '1kg/pack', 195.00, 0, NULL, NULL),
(67, 'Noodles & Wrappers', 'Spinach Noodles', 'Spinach Noodles (Minimum 6 packs)', '1kg/pack', 195.00, 0, NULL, NULL),
(68, 'Noodles & Wrappers', 'Siomai Wrapper', 'Siomai Wrapper (Yellow/White)', '250g/pack', 70.00, 0, NULL, NULL),
(69, 'Noodles & Wrappers', 'Wanton Wrapper', 'Wanton Wrapper (Yellow/White)', '250g/pack', 70.00, 0, NULL, NULL),
(70, 'Noodles & Wrappers', 'Beancurd Wrapper', 'Beancurd Wrapper', '1kg/pack', 1600.00, 0, NULL, NULL),
(71, 'Noodles & Wrappers', 'Spring Roll Wrapper', 'Spring Roll Wrapper', '25pcs/pack', 90.00, 0, NULL, NULL),
(72, 'Noodles & Wrappers', 'Gyoza Wrapper', 'Gyoza Wrapper (Minimum 10 Packs)', '250g/pack', 70.00, 0, NULL, NULL);

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

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`),
  ADD UNIQUE KEY `unique_role` (`role_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `balance_history`
--
ALTER TABLE `balance_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `clients_accounts`
--
ALTER TABLE `clients_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `monthly_payments`
--
ALTER TABLE `monthly_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=829;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `page_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `payment_history`
--
ALTER TABLE `payment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `payment_status_history`
--
ALTER TABLE `payment_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
