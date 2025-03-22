-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 22, 2025 at 07:25 PM
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
  `role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`id`, `username`, `password`, `created_at`, `role`, `status`) VALUES
(56, 'admin', '123', '2025-03-05 22:58:17', 'Admin', 'Active'),
(57, 'Secretary', '123', '2025-03-05 23:17:38', 'Secretary', 'Active'),
(58, 'aedan', '123', '2025-03-05 23:23:25', 'Admin', 'Active'),
(60, 'Manager', '123', '2025-03-05 23:27:49', 'Manager', 'Archived'),
(61, 'Accountant', '123', '2025-03-05 23:27:55', 'Accountant', 'Archived'),
(62, 'Ryan', '123', '2025-03-09 07:14:07', 'Admin', 'Archived'),
(66, 'asd', '123', '2025-03-20 10:20:01', 'Admin', 'Active'),
(67, 'asds', '123', '2025-03-20 22:33:16', 'Admin', 'Active');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients_accounts`
--

INSERT INTO `clients_accounts` (`id`, `username`, `password`, `email`, `phone`, `region`, `city`, `company`, `company_address`, `business_proof`, `status`, `created_at`) VALUES
(1, 'aedan', '$2y$10$7/zp6DLomjy19Q4hMcU3S.cxz6e/h2eVmpHDNsPpG/eo87epMAs12', '123@gmail.com', '123', '123', '123', 'company aedan', '123', '[]', 'Active', '2025-03-07 09:58:07'),
(2, 'asdasd', '$2y$10$dojkOKe2Z7y.NwwuAiFmh.E4TYS1yKf.Z1fnUeKk5jqVTm4dN2Hu6', 'asd@gmail.com', '123', 'asd', 'asd', 'sds', 'asdas', '[\"\\/top_exchange\\/uploads\\/asdasd\\/4.png\"]', 'Active', '2025-03-07 10:06:55'),
(3, 'Jeff Santonia', '$2y$10$dwjDK/6QbkEF.qBuozhjneWerFL6jY4qyZ8hchngxdbNZ3k/u80vm', 'jeffsantonia@gmail.com', '1236969420', 'Munoz', 'Quezon City', 'Jeff Company', 'Jeff City', '[\"\\/top_exchange\\/uploads\\/Jeff Santonia\\/3.png\",\"\\/top_exchange\\/uploads\\/Jeff Santonia\\/4.png\"]', 'Inactive', '2025-03-09 14:12:20'),
(4, 'joe', '$2y$10$0kM1rjCbnDXkL4/.BSrvEuVCSMjLN/ICY5KeSmzJ0wQ0aPQEsyQwe', 'joemama@gmail.com', '123123123', 'Metro Manila', 'QC', 'Joe Mama Corp', 'Joe mama address', '[\"\\/top_exchange\\/uploads\\/joe\\/audience2.png\"]', 'Inactive', '2025-03-20 16:08:02'),
(5, 'asdas', '$2y$10$VkHI738QyX3HdbYtKjzFZeh0G1JKSqCPvLRAY2UAe3t3N8K9akYDy', 'asdsas@g.com', 'asdasdasd', 'asdasas', 'dasasd', 'asdas', 'asdasdas', '[\"\\/top_exchange\\/uploads\\/asdas\\/youtube.png\"]', 'Inactive', '2025-03-20 16:09:21'),
(6, 'sdfsdf', '$2y$10$ih6oNCRNeXWjd2UuR9wtM.f1mcUu6QrF1q34yRo6x10kRsyiiqXVi', 'asdas@gmail.com', '12312', '1231231', '123123', '12312312', '312312', '[]', 'Pending', '2025-03-20 16:11:19');

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
  `payment_status` enum('Paid','Unpaid') NOT NULL DEFAULT 'Unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `monthly_payments`
--

INSERT INTO `monthly_payments` (`id`, `username`, `month`, `year`, `total_amount`, `payment_status`, `created_at`, `updated_at`) VALUES
(1, 'aedan', 1, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-19 18:22:57'),
(2, 'aedan', 2, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-19 18:34:25'),
(3, 'aedan', 3, 2025, 1625.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-20 15:38:12'),
(4, 'aedan', 4, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-19 18:34:57'),
(5, 'aedan', 5, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-19 16:59:07'),
(6, 'aedan', 6, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-19 16:59:07'),
(7, 'aedan', 7, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-19 16:59:07'),
(8, 'aedan', 8, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-19 16:59:07'),
(9, 'aedan', 9, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-19 16:59:07'),
(10, 'aedan', 10, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-19 16:59:07'),
(11, 'aedan', 11, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-19 16:59:07'),
(12, 'aedan', 12, 2025, 0.00, 'Unpaid', '2025-03-19 16:59:07', '2025-03-19 16:59:07'),
(13, 'asdasd', 1, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-19 17:13:14'),
(14, 'asdasd', 2, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-19 17:13:14'),
(15, 'asdasd', 3, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-19 18:36:53'),
(16, 'asdasd', 4, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-19 17:13:14'),
(17, 'asdasd', 5, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-19 17:13:14'),
(18, 'asdasd', 6, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-19 17:13:14'),
(19, 'asdasd', 7, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-19 17:13:14'),
(20, 'asdasd', 8, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-19 17:13:14'),
(21, 'asdasd', 9, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-19 17:13:14'),
(22, 'asdasd', 10, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-19 17:13:14'),
(23, 'asdasd', 11, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-19 17:13:14'),
(24, 'asdasd', 12, 2025, 0.00, 'Unpaid', '2025-03-19 17:13:14', '2025-03-19 17:13:14'),
(25, 'asdasd', 1, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-19 17:49:56'),
(26, 'asdasd', 2, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-19 17:49:56'),
(27, 'asdasd', 3, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-19 18:36:55'),
(28, 'asdasd', 4, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-19 17:49:56'),
(29, 'asdasd', 5, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-19 17:49:56'),
(30, 'asdasd', 6, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-19 17:49:56'),
(31, 'asdasd', 7, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-19 17:49:56'),
(32, 'asdasd', 8, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-19 17:49:56'),
(33, 'asdasd', 9, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-19 17:49:56'),
(34, 'asdasd', 10, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-19 17:49:56'),
(35, 'asdasd', 11, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-19 17:49:56'),
(36, 'asdasd', 12, 2024, 0.00, 'Unpaid', '2025-03-19 17:49:56', '2025-03-19 17:49:56'),
(145, 'Jeff Santonia', 1, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-19 18:05:51'),
(146, 'Jeff Santonia', 2, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-19 18:05:51'),
(147, 'Jeff Santonia', 3, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-19 18:36:56'),
(148, 'Jeff Santonia', 4, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-19 18:05:51'),
(149, 'Jeff Santonia', 5, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-19 18:05:51'),
(150, 'Jeff Santonia', 6, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-19 18:05:51'),
(151, 'Jeff Santonia', 7, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-19 18:05:51'),
(152, 'Jeff Santonia', 8, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-19 18:05:51'),
(153, 'Jeff Santonia', 9, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-19 18:05:51'),
(154, 'Jeff Santonia', 10, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-19 18:05:51'),
(155, 'Jeff Santonia', 11, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-19 18:05:51'),
(156, 'Jeff Santonia', 12, 2025, 0.00, 'Unpaid', '2025-03-19 18:05:51', '2025-03-19 18:05:51');

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
  `orders` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`orders`)),
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('Pending','Active','Rejected','Completed') NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `po_number`, `username`, `order_date`, `delivery_date`, `orders`, `total_amount`, `status`) VALUES
(17, 'aedan-1', 'aedan', '2025-03-20', '2025-03-21', '[{\"product_id\":2,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Med)\",\"packaging\":\"10pcs/pack\",\"price\":325,\"quantity\":5}]', 1625.00, 'Completed'),
(18, 'Jeff Santonia-1', 'Jeff Santonia', '2025-03-20', '2025-03-21', '[{\"product_id\":6,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (B Small)\",\"packaging\":\"15pcs/pack\",\"price\":205,\"quantity\":10}]', 2050.00, 'Completed'),
(19, 'aedan-2', 'aedan', '2025-03-20', '2025-03-21', '[{\"product_id\":1,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Large)\",\"packaging\":\"6pcs/pack\",\"price\":280,\"quantity\":5}]', 1400.00, 'Completed'),
(20, 'aedan-3', 'aedan', '2025-03-20', '2025-03-21', '[{\"product_id\":2,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Med)\",\"packaging\":\"10pcs/pack\",\"price\":325,\"quantity\":10}]', 3250.00, 'Completed'),
(21, 'aedan-4', 'aedan', '2025-03-20', '2025-03-21', '[{\"product_id\":2,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Med)\",\"packaging\":\"10pcs/pack\",\"price\":325,\"quantity\":12}]', 3900.00, 'Completed'),
(22, 'aedan-5', 'aedan', '2025-03-20', '2025-03-21', '[{\"product_id\":5,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (B Med)\",\"packaging\":\"10pcs/pack\",\"price\":250,\"quantity\":2}]', 500.00, 'Completed'),
(23, 'Jeff Santonia-2', 'Jeff Santonia', '2025-03-20', '2025-03-21', '[{\"product_id\":1,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Large)\",\"packaging\":\"6pcs/pack\",\"price\":280,\"quantity\":4}]', 1120.00, 'Completed'),
(24, 'Jeff Santonia-3', 'Jeff Santonia', '2025-03-20', '2025-03-21', '[{\"product_id\":2,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Med)\",\"packaging\":\"10pcs/pack\",\"price\":325,\"quantity\":5}]', 1625.00, 'Completed'),
(25, 'aedan-6', 'aedan', '2025-03-20', '2025-03-21', '[{\"product_id\":2,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Med)\",\"packaging\":\"10pcs/pack\",\"price\":325,\"quantity\":5}]', 1625.00, 'Pending'),
(26, 'aedan-7', 'aedan', '2025-03-21', '2025-03-28', '[{\"product_id\":73,\"category\":\"Dimsum & Dumplings\",\"item_description\":\"shumai\",\"packaging\":123,\"price\":123,\"quantity\":5}]', 615.00, 'Active'),
(27, 'asdasd-1', 'asdasd', '2025-03-21', '2025-03-24', '[{\"product_id\":1,\"category\":\"Siopao\",\"item_description\":\"Asado Siopao (A Large)\",\"packaging\":\"6pcs/pack\",\"price\":280,\"quantity\":5}]', 1400.00, 'Active');

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
(31, 'Dimsum & Dumplings', 'Beancurd Roll (A)', '12pcs/pack', 310.00, 691),
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
(72, 'Noodles & Wrappers', 'Gyoza Wrapper (Minimum 10 Packs)', '250g/pack', 70.00, 0),
(73, 'Dimsum & Dumplings', 'shumai', '123', 123.00, 0);

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
(1, 'Admin', 'active', 'Accounts - Admin, Accounts - Clients, Customers, Dashboard, User Roles, Inventory, Orders, Order History, Payment History'),
(2, 'Manager', 'active', 'Accounts - Clients, Customers, Dashboard, Inventory, Order History, Orders, Payment History'),
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `clients_accounts`
--
ALTER TABLE `clients_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `monthly_payments`
--
ALTER TABLE `monthly_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=769;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `page_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounts`
--
ALTER TABLE `accounts`
  ADD CONSTRAINT `fk_accounts_roles` FOREIGN KEY (`role`) REFERENCES `roles` (`role_name`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
