-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 04, 2025 at 02:40 PM
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
-- Database: `macj_pest_control`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `client_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `preferred_date` date NOT NULL,
  `preferred_time` time NOT NULL,
  `kind_of_place` varchar(50) NOT NULL,
  `location_address` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `technician_id` int(11) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'assigned',
  `pest_problems` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointment_technicians`
--

CREATE TABLE `appointment_technicians` (
  `appointment_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_chemical_inventory`
--

CREATE TABLE `archived_chemical_inventory` (
  `archive_id` int(11) NOT NULL,
  `id` int(11) NOT NULL,
  `chemical_name` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` enum('Liters','Kilograms','Grams','Pieces') NOT NULL,
  `manufacturer` varchar(255) DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `safety_info` text DEFAULT NULL,
  `expiration_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `target_pest` varchar(255) DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `scheduled_deletion_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_clients`
--

CREATE TABLE `archived_clients` (
  `archive_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `location_address` varchar(255) DEFAULT NULL,
  `type_of_place` varchar(50) DEFAULT NULL,
  `location_lat` varchar(20) DEFAULT NULL,
  `location_lng` varchar(20) DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `scheduled_deletion_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_technicians`
--

CREATE TABLE `archived_technicians` (
  `archive_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `tech_contact_number` varchar(20) NOT NULL,
  `tech_fname` varchar(50) NOT NULL,
  `tech_lname` varchar(50) NOT NULL,
  `technician_picture` varchar(255) NOT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `scheduled_deletion_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_technician_checklist_logs`
--

CREATE TABLE `archived_technician_checklist_logs` (
  `archive_id` int(11) NOT NULL,
  `log_id` int(11) DEFAULT NULL,
  `technician_id` int(11) DEFAULT NULL,
  `checklist_date` datetime DEFAULT NULL,
  `checked_items` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `scheduled_deletion_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_tools_equipment`
--

CREATE TABLE `archived_tools_equipment` (
  `archive_id` int(11) NOT NULL,
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `scheduled_deletion_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assessment_report`
--

CREATE TABLE `assessment_report` (
  `report_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `end_time` time NOT NULL,
  `area` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `attachments` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `pest_types` varchar(255) DEFAULT NULL,
  `problem_area` varchar(255) DEFAULT NULL,
  `recommendation` text DEFAULT NULL,
  `chemical_recommendations` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chemical_inventory`
--

CREATE TABLE `chemical_inventory` (
  `id` int(11) NOT NULL,
  `chemical_name` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` enum('Liters','Kilograms','Grams','Pieces') NOT NULL,
  `manufacturer` varchar(255) DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `safety_info` text DEFAULT NULL,
  `expiration_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) GENERATED ALWAYS AS (case when `quantity` <= 0 then 'Out of Stock' when `quantity` < 10 then 'Low Stock' else 'In Stock' end) STORED,
  `target_pest` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chemical_inventory`
--

INSERT INTO `chemical_inventory` (`id`, `chemical_name`, `type`, `quantity`, `unit`, `manufacturer`, `supplier`, `description`, `safety_info`, `expiration_date`, `created_at`, `target_pest`) VALUES
(8, 'Cypermethrin', 'Insecticide', 12.00, 'Kilograms', '1', '1', '1', 'a', '2025-04-10', '2025-04-01 10:06:23', 'Crawling & Flying Pest'),
(9, 'Cypermethrin', 'Insecticide', 12.00, 'Kilograms', 'qw', '1', '12', 'sdd', '2025-04-09', '2025-04-01 10:07:11', 'Crawling & Flying Pest'),
(10, 'Cypermethrin', 'Insecticide', 12.00, 'Kilograms', 'qw', '1', '12', 'sdd', '2025-04-09', '2025-04-01 10:07:11', 'Crawling & Flying Pest'),
(11, 'Alpha Cypermethrin', 'Insecticide', 2.00, 'Kilograms', '2', '2', '2', '2', '2025-04-07', '2025-04-01 10:07:42', 'Crawling & Flying Pest'),
(14, 'Permethrin', 'Insecticide', 11.00, 'Kilograms', 'beer', 'jhkjhkj', 'kjhkjh', 'lmklkjlj', '2025-04-17', '2025-04-04 01:20:48', 'Crawling & Flying Pest'),
(16, 'Cypermethrin', 'Insecticide', 12.00, 'Liters', 'Www', 'www', 'aaaa', 'wwwww', '2025-05-11', '2025-04-11 01:32:46', 'Crawling & Flying Pest'),
(18, 'Fipronil', 'Insecticide', 15.00, 'Liters', 'BASF', 'Pest Control Supplies Inc.', 'Effective against termites and other wood-destroying insects.', 'Use in well-ventilated areas. Avoid contact with skin, eyes, and clothing. Keep away from food and water sources.', '2026-04-27', '2025-04-27 10:41:32', 'Termites'),
(19, 'Imidacloprid', 'Insecticide', 10.00, 'Kilograms', 'Bayer', 'Agri-Chem Distributors', 'Systemic insecticide effective for termite control and prevention.', 'Harmful if swallowed or inhaled. Avoid breathing dust. Wash thoroughly after handling.', '2026-04-27', '2025-04-27 10:41:32', 'Termites'),
(20, 'Emamectin Benzoate', 'Insecticide', 20.00, 'Liters', 'Ginebra', 'Ginebra San Miguel', 'For Cockroach', 'Use with precaution', '2025-06-29', '2025-04-29 11:21:53', 'Cockroaches');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `client_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `location_address` varchar(255) DEFAULT NULL,
  `type_of_place` varchar(50) DEFAULT NULL,
  `location_lat` varchar(20) DEFAULT NULL,
  `location_lng` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`client_id`, `first_name`, `last_name`, `email`, `contact_number`, `password`, `registered_at`, `location_address`, `type_of_place`, `location_lat`, `location_lng`) VALUES
(8, 'Francis', 'Gernan', 'gernan123@gmail.com', '09202544398', '$2y$10$bAZv1VaklaOYBFUcDJhndOwFyv8jpGhoDG338zG3BUv27CVYPRciS', '2025-03-30 11:54:04', NULL, NULL, NULL, NULL),
(9, 'Paul Klarence A.', 'De Guzman', 'deguzman0369@gmail.com', '09690381171', '$2y$10$i0jXOIJ6g9Hh1Y41VEKT0eko798.dsn1qhNCBw7tjkD5o4ydEafci', '2025-04-12 02:44:43', 'Halcon 2 Street, Santa Teresita, Santa Mesa Heights, 1st District, Quezon City, Eastern Manila District, Metro Manila, 1114, Philippines [14.619218994370428,120.99877277997442]', 'Office', '14.619218994370428', '120.99877277997442'),
(10, 'Gorge', 'Cooper', 'Cooper@gmail.com', '123', '$2y$10$gRFu2athyehRtpYMREbUmOwjdYVgmyvlOmCyj7Kq89RezhAd46nl6', '2025-04-14 05:36:32', 'Fishrmall', 'House', NULL, NULL),
(11, 'Klarence', 'De Guzman', 'klarence@yahoo.com', '0957463811', '$2y$10$PpwZMTaGNNEQZgk2/IajN.QXOwFshxrNTviLZapym8rqMpVxG/O1u', '2025-04-16 09:08:58', 'Halcon 2 Street, Santa Teresita, Santa Mesa Heights, 1st District, Quezon City, Eastern Manila District, Metro Manila, 1114, Philippines [14.619897,120.998094]', 'House', '14.619897', '120.998094'),
(12, 'Francis', 'Gernan', 'gernan1234@gmail.com', '09202544398', '$2y$10$LJbK8RUfNOrOWNyxvdSVK.JXBhdfbfx421j7J7.S6JfXQk5QZ9IrW', '2025-04-20 16:17:42', '55, Bahawan Street, Masambong, San Francisco del Monte, 1st District, Quezon City, Eastern Manila District, Metro Manila, 1105, Philippines [14.644037,121.008090]', 'House', '14.644037', '121.008090'),
(13, 'Zak', 'Agbalo', 'agbalo@gmail.com', '123', '$2y$10$ag9fTlOPHilmG8PNr802kel2T8OkUqUF8Eu8aS5P3yNLaAATXohiW', '2025-04-21 08:19:11', 'Nexus Enterprises & Electrical Supply, Congressional Avenue, Ramon Magsaysay, Bago Bantay, 1st District, Quezon City, Eastern Manila District, Metro Manila, 1105, Philippines [14.658428,121.019301]', 'Restaurant', '14.658428', '121.019301'),
(14, 'John', 'Jake', 'deguzman0361@gmail.com', '09202544398', '$2y$10$66vK96sWs6pjSoSjzIpojO3eMJotrJVrjNox6g2S5HHUys5tjb.ca', '2025-04-30 02:38:39', 'Halcon 2 Street, Santa Teresita, Santa Mesa Heights, 1st District, Quezon City, Eastern Manila District, Metro Manila, 1114, Philippines [14.619939,120.998061]', 'House', '14.619939', '120.998061'),
(26, 'Rean', 'Nartea', 'narteareanfredrick@gmail.com', '09202544398', '$2y$10$a3Uc8jgesxvj1dKPMi3DVuC1WYSrYVfnKXd8heshDdtTlaD5T1jzS', '2025-05-01 15:49:24', '29, Bahawan Street, Masambong, San Francisco del Monte, 1st District, Quezon City, Eastern Manila District, Metro Manila, 1115, Philippines', 'House', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `joborder_feedback`
--

CREATE TABLE `joborder_feedback` (
  `feedback_id` int(11) NOT NULL,
  `job_order_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `rating` int(1) NOT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `technician_arrived` tinyint(1) NOT NULL DEFAULT 0,
  `job_completed` tinyint(1) NOT NULL DEFAULT 0,
  `verification_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_order`
--

CREATE TABLE `job_order` (
  `job_order_id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `type_of_work` varchar(50) NOT NULL,
  `preferred_date` date NOT NULL,
  `preferred_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `frequency` enum('one-time','weekly','monthly','quarterly') NOT NULL DEFAULT 'one-time',
  `client_approval_status` enum('pending','approved','declined','one-time') NOT NULL DEFAULT 'pending',
  `client_approval_date` datetime DEFAULT NULL,
  `chemical_recommendations` text DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `payment_amount` decimal(10,2) DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `status` varchar(20) DEFAULT 'scheduled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_order_report`
--

CREATE TABLE `job_order_report` (
  `report_id` int(11) NOT NULL,
  `job_order_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `observation_notes` text NOT NULL,
  `attachments` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `chemical_usage` text DEFAULT NULL,
  `recommendation` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_order_technicians`
--

CREATE TABLE `job_order_technicians` (
  `job_order_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('client','technician','admin') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `office_staff`
--

CREATE TABLE `office_staff` (
  `staff_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `office_staff`
--

INSERT INTO `office_staff` (`staff_id`, `username`, `password`, `full_name`, `email`, `contact_number`, `profile_picture`) VALUES
(1, 'admin_mike', '4a169480fb6c63f85a2bdb42192bb7c6', '', '', '', '680fa652e374b_3a2115b888673fecde00c4317f42eb5d.jpg'),
(2, 'staff_jane', 'de9bf5643eabf80f4a56fda3bbb84483', NULL, NULL, NULL, NULL),
(3, 'staff_john', 'e10adc3949ba59abbe56e057f20f883e', '', '', '', '680f94e41b1bb_Playbutton2.png');

-- --------------------------------------------------------

--
-- Table structure for table `technicians`
--

CREATE TABLE `technicians` (
  `technician_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `tech_contact_number` varchar(20) NOT NULL,
  `tech_fname` varchar(50) NOT NULL,
  `tech_lname` varchar(50) NOT NULL,
  `technician_picture` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `technicians`
--

INSERT INTO `technicians` (`technician_id`, `username`, `password`, `tech_contact_number`, `tech_fname`, `tech_lname`, `technician_picture`) VALUES
(1, 'tech_one', '$2y$10$v22wEDOCq6YRqK3GjFxys.TLenskIW9wKXyewxfp3Tqcc5DHpBBzq', '09202544398', 'Jake', 'Paul', 'uploads/technicians/680de8765421e_42643.jpg'),
(10, 'tech_two', '$2y$10$TjOGYB7t/ippfnDaT8DtHe7HRon.xypNWC63PfHl3bXJ.ohFGYgJu', '09690381171', 'John', 'Paul', 'uploads/technicians/67fe2d6221640_Screenshot 2025-04-12 141855.png'),
(16, 'tech_three', '$2y$10$k4H1tq2BH917Ky8.yQz/2.tzYS7akR11qIpULjIHx.UcwD9u3CQlS', '09202544398', 'John', 'Jake', 'uploads/technicians/68105e2cec793_baby-elephant-3526681_1280.png'),
(17, 'tech_four', '$2y$10$N6pAkmact.7kpxwJX9bnn.AzKgWNVSDGQ2Pzr6h8U/htb40.So78a', '09202544398', 'Four', 'Chan', 'uploads/technicians/6810c888d0288_cat-7563332_1280.png'),
(18, 'tech_five', '$2y$10$kYI6gDSuvxz/du/QLZsS9uqc/zAAgOsLXhdLyUNWQymhnwZsKJ9wG', '0965385692', 'Five', 'Six', 'uploads/technicians/6810ce41c1249_Skunk.png');

-- --------------------------------------------------------

--
-- Table structure for table `technician_checklist_logs`
--

CREATE TABLE `technician_checklist_logs` (
  `log_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `checklist_date` date NOT NULL,
  `checked_items` text DEFAULT NULL,
  `total_items` int(11) NOT NULL,
  `checked_count` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `technician_checklist_logs`
--

INSERT INTO `technician_checklist_logs` (`log_id`, `technician_id`, `checklist_date`, `checked_items`, `total_items`, `checked_count`, `created_at`) VALUES
(6, 1, '2025-04-21', '[]', 0, 0, '2025-04-21 07:58:06'),
(7, 1, '2025-04-27', '[]', 0, 0, '2025-04-27 07:47:24'),
(8, 10, '2025-04-27', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\"},{\"id\":\"22\",\"name\":\"Bed Bug Vacuum\"},{\"id\":\"20\",\"name\":\"Heat Treatment Unit\"},{\"id\":\"21\",\"name\":\"Mattress Encasement\"},{\"id\":\"24\",\"name\":\"1\"},{\"id\":\"1\",\"name\":\"Backpack Sprayer\"},{\"id\":\"6\",\"name\":\"Bait Gun\"},{\"id\":\"7\",\"name\":\"Dust Applicator\"},{\"id\":\"5\",\"name\":\"Flashlight\"},{\"id\":\"3\",\"name\":\"Fogger Machine\"},{\"id\":\"8\",\"name\":\"Glue Traps\"},{\"id\":\"2\",\"name\":\"Hand Sprayer\"},{\"id\":\"4\",\"name\":\"Inspection Mirror\"},{\"id\":\"11\",\"name\":\"Drill\"},{\"id\":\"12\",\"name\":\"Injection Rod\"},{\"id\":\"10\",\"name\":\"Moisture Meter\"},{\"id\":\"15\",\"name\":\"Trenching Shovel\"}]', 24, 17, '2025-04-27 09:54:42'),
(9, 1, '2025-04-28', '[]', 0, 0, '2025-04-28 02:26:03'),
(10, 10, '2025-04-28', '[]', 0, 0, '2025-04-28 02:26:52'),
(11, 10, '2025-04-29', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\"},{\"id\":\"22\",\"name\":\"Bed Bug Vacuum\"},{\"id\":\"20\",\"name\":\"Heat Treatment Unit\"},{\"id\":\"21\",\"name\":\"Mattress Encasement\"}]', 24, 4, '2025-04-29 01:56:42'),
(12, 1, '2025-04-29', '[]', 0, 0, '2025-04-29 03:22:18'),
(13, 16, '2025-04-29', '[]', 0, 0, '2025-04-29 05:08:37'),
(14, 17, '2025-04-29', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\"},{\"id\":\"22\",\"name\":\"Bed Bug Vacuum\"},{\"id\":\"20\",\"name\":\"Heat Treatment Unit\"},{\"id\":\"24\",\"name\":\"1\"},{\"id\":\"1\",\"name\":\"Backpack Sprayer\"},{\"id\":\"6\",\"name\":\"Bait Gun\"},{\"id\":\"7\",\"name\":\"Dust Applicator\"},{\"id\":\"11\",\"name\":\"Drill\"},{\"id\":\"12\",\"name\":\"Injection Rod\"},{\"id\":\"10\",\"name\":\"Moisture Meter\"},{\"id\":\"14\",\"name\":\"Foam Applicator\"},{\"id\":\"16\",\"name\":\"Soil Injector\"},{\"id\":\"17\",\"name\":\"Backpack Herbicide Sprayer\"},{\"id\":\"19\",\"name\":\"Spreader\"}]', 24, 14, '2025-04-29 12:52:03'),
(17, 1, '2025-04-30', '[23,22,20,21,7,3,11,12,10,9,13]', 24, 11, '2025-04-30 09:13:25'),
(18, 1, '2025-05-01', '[]', 0, 0, '2025-05-01 08:20:10'),
(19, 1, '2025-05-02', '[]', 0, 0, '2025-05-02 13:08:11'),
(20, 10, '2025-05-02', '[]', 0, 0, '2025-05-02 13:09:51'),
(21, 1, '2025-05-03', '[]', 0, 0, '2025-05-03 05:17:37'),
(22, 18, '2025-04-29', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\"},{\"id\":\"22\",\"name\":\"Bed Bug Vacuum\"},{\"id\":\"20\",\"name\":\"Heat Treatment Unit\"},{\"id\":\"21\",\"name\":\"Mattress Encasement\"},{\"id\":\"11\",\"name\":\"Drill\"},{\"id\":\"12\",\"name\":\"Injection Rod\"},{\"id\":\"10\",\"name\":\"Moisture Meter\"},{\"id\":\"9\",\"name\":\"Termite Bait Station\"},{\"id\":\"13\",\"name\":\"Termite Inspection Tool Kit\"}]', 0, 0, '2025-05-03 17:17:41'),
(23, 18, '2025-04-30', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\"},{\"id\":\"22\",\"name\":\"Bed Bug Vacuum\"},{\"id\":\"20\",\"name\":\"Heat Treatment Unit\"},{\"id\":\"21\",\"name\":\"Mattress Encasement\"},{\"id\":\"11\",\"name\":\"Drill\"},{\"id\":\"12\",\"name\":\"Injection Rod\"},{\"id\":\"10\",\"name\":\"Moisture Meter\"},{\"id\":\"9\",\"name\":\"Termite Bait Station\"},{\"id\":\"13\",\"name\":\"Termite Inspection Tool Kit\"}]', 0, 0, '2025-05-03 17:17:41');

-- --------------------------------------------------------

--
-- Table structure for table `technician_feedback`
--

CREATE TABLE `technician_feedback` (
  `feedback_id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `rating` int(1) NOT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `technician_arrived` tinyint(1) NOT NULL DEFAULT 0,
  `job_completed` tinyint(1) NOT NULL DEFAULT 0,
  `verification_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tools_equipment`
--

CREATE TABLE `tools_equipment` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tools_equipment`
--

INSERT INTO `tools_equipment` (`id`, `name`, `category`, `quantity`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Backpack Sprayer', 'General Pest Control', 15, 'Professional-grade backpack sprayer with adjustable nozzle for applying liquid pesticides', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(2, 'Hand Sprayer', 'General Pest Control', 25, 'Handheld sprayer for targeted application of pesticides in small areas', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(3, 'Fogger Machine', 'General Pest Control', 8, 'ULV cold fogger for dispersing insecticide in enclosed spaces', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(4, 'Inspection Mirror', 'General Pest Control', 12, 'Telescopic inspection mirror for checking hard-to-reach areas', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(5, 'Flashlight', 'General Pest Control', 20, 'High-powered LED flashlight for inspections in dark areas', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(6, 'Bait Gun', 'General Pest Control', 10, 'Precision applicator for gel baits and pastes', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(7, 'Dust Applicator', 'General Pest Control', 12, 'Tool for applying insecticidal dust in cracks and crevices', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(8, 'Glue Traps', 'General Pest Control', 150, 'Non-toxic monitoring traps for insects and rodents', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(9, 'Termite Bait Station', 'Termite', 50, 'In-ground monitoring and baiting system for termite control', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(10, 'Moisture Meter', 'Termite', 6, 'Digital device for measuring moisture content in wood and building materials', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(11, 'Drill', 'Termite', 8, 'Cordless drill for creating treatment holes in concrete and wood', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(12, 'Injection Rod', 'Termite', 10, 'Specialized tool for injecting termiticide into soil', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(13, 'Termite Inspection Tool Kit', 'Termite', 5, 'Complete kit with probes, scrapers, and inspection tools', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(14, 'Foam Applicator', 'Termite Treatment', 7, 'Device for applying termiticide foam in wall voids and galleries', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(15, 'Trenching Shovel', 'Termite Treatment', 12, 'Specialized shovel for creating treatment trenches around foundations', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(16, 'Soil Injector', 'Termite Treatment', 8, 'Tool for injecting termiticide into soil at precise depths', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(17, 'Backpack Herbicide Sprayer', 'Weed Control', 6, 'Heavy-duty sprayer specifically for herbicide application', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(18, 'Weed Torch', 'Weed Control', 4, 'Propane torch for thermal weed control', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(19, 'Spreader', 'Weed Control', 5, 'Broadcast spreader for granular herbicide application', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(20, 'Heat Treatment Unit', 'Bed Bugs', 3, 'Portable heater for thermal bed bug elimination', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(21, 'Mattress Encasement', 'Bed Bugs', 30, 'Protective covers to prevent bed bug infestations in mattresses', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(22, 'Bed Bug Vacuum', 'Bed Bugs', 5, 'Specialized vacuum with HEPA filter for bed bug removal', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(23, 'Bed Bug Monitor', 'Bed Bugs', 40, 'Passive monitoring device for detecting bed bug presence', '2025-04-22 13:04:06', '2025-04-22 13:04:06'),
(24, '1', 'General Pest Control', 0, '', '0000-00-00 00:00:00', '2025-04-26 16:10:55');

-- --------------------------------------------------------

--
-- Table structure for table `work_types`
--

CREATE TABLE `work_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `appointment_technicians`
--
ALTER TABLE `appointment_technicians`
  ADD PRIMARY KEY (`appointment_id`,`technician_id`),
  ADD KEY `technician_id` (`technician_id`);

--
-- Indexes for table `archived_chemical_inventory`
--
ALTER TABLE `archived_chemical_inventory`
  ADD PRIMARY KEY (`archive_id`);

--
-- Indexes for table `archived_clients`
--
ALTER TABLE `archived_clients`
  ADD PRIMARY KEY (`archive_id`);

--
-- Indexes for table `archived_technicians`
--
ALTER TABLE `archived_technicians`
  ADD PRIMARY KEY (`archive_id`);

--
-- Indexes for table `archived_technician_checklist_logs`
--
ALTER TABLE `archived_technician_checklist_logs`
  ADD PRIMARY KEY (`archive_id`);

--
-- Indexes for table `archived_tools_equipment`
--
ALTER TABLE `archived_tools_equipment`
  ADD PRIMARY KEY (`archive_id`);

--
-- Indexes for table `assessment_report`
--
ALTER TABLE `assessment_report`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `chemical_inventory`
--
ALTER TABLE `chemical_inventory`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`client_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `joborder_feedback`
--
ALTER TABLE `joborder_feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD UNIQUE KEY `job_order_id` (`job_order_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `technician_id` (`technician_id`);

--
-- Indexes for table `job_order`
--
ALTER TABLE `job_order`
  ADD PRIMARY KEY (`job_order_id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `job_order_report`
--
ALTER TABLE `job_order_report`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `job_order_id` (`job_order_id`),
  ADD KEY `technician_id` (`technician_id`);

--
-- Indexes for table `job_order_technicians`
--
ALTER TABLE `job_order_technicians`
  ADD PRIMARY KEY (`job_order_id`,`technician_id`),
  ADD KEY `technician_id` (`technician_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `user_type` (`user_type`),
  ADD KEY `is_read` (`is_read`);

--
-- Indexes for table `office_staff`
--
ALTER TABLE `office_staff`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `technicians`
--
ALTER TABLE `technicians`
  ADD PRIMARY KEY (`technician_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `technician_checklist_logs`
--
ALTER TABLE `technician_checklist_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD UNIQUE KEY `technician_date` (`technician_id`,`checklist_date`),
  ADD KEY `technician_id` (`technician_id`),
  ADD KEY `checklist_date` (`checklist_date`);

--
-- Indexes for table `technician_feedback`
--
ALTER TABLE `technician_feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD UNIQUE KEY `report_id` (`report_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `technician_id` (`technician_id`);

--
-- Indexes for table `tools_equipment`
--
ALTER TABLE `tools_equipment`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `work_types`
--
ALTER TABLE `work_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `type_name` (`type_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `archived_chemical_inventory`
--
ALTER TABLE `archived_chemical_inventory`
  MODIFY `archive_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `archived_clients`
--
ALTER TABLE `archived_clients`
  MODIFY `archive_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `archived_technicians`
--
ALTER TABLE `archived_technicians`
  MODIFY `archive_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `archived_technician_checklist_logs`
--
ALTER TABLE `archived_technician_checklist_logs`
  MODIFY `archive_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `archived_tools_equipment`
--
ALTER TABLE `archived_tools_equipment`
  MODIFY `archive_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `assessment_report`
--
ALTER TABLE `assessment_report`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `chemical_inventory`
--
ALTER TABLE `chemical_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `client_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `joborder_feedback`
--
ALTER TABLE `joborder_feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `job_order`
--
ALTER TABLE `job_order`
  MODIFY `job_order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=448;

--
-- AUTO_INCREMENT for table `job_order_report`
--
ALTER TABLE `job_order_report`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=678;

--
-- AUTO_INCREMENT for table `office_staff`
--
ALTER TABLE `office_staff`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `technicians`
--
ALTER TABLE `technicians`
  MODIFY `technician_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `technician_checklist_logs`
--
ALTER TABLE `technician_checklist_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `technician_feedback`
--
ALTER TABLE `technician_feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `tools_equipment`
--
ALTER TABLE `tools_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `work_types`
--
ALTER TABLE `work_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`);

--
-- Constraints for table `appointment_technicians`
--
ALTER TABLE `appointment_technicians`
  ADD CONSTRAINT `appointment_technicians_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`),
  ADD CONSTRAINT `appointment_technicians_ibfk_2` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`);

--
-- Constraints for table `assessment_report`
--
ALTER TABLE `assessment_report`
  ADD CONSTRAINT `assessment_report_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`);

--
-- Constraints for table `joborder_feedback`
--
ALTER TABLE `joborder_feedback`
  ADD CONSTRAINT `joborder_feedback_ibfk_1` FOREIGN KEY (`job_order_id`) REFERENCES `job_order` (`job_order_id`),
  ADD CONSTRAINT `joborder_feedback_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`),
  ADD CONSTRAINT `joborder_feedback_ibfk_3` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`);

--
-- Constraints for table `job_order`
--
ALTER TABLE `job_order`
  ADD CONSTRAINT `job_order_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `assessment_report` (`report_id`);

--
-- Constraints for table `job_order_report`
--
ALTER TABLE `job_order_report`
  ADD CONSTRAINT `job_order_report_ibfk_1` FOREIGN KEY (`job_order_id`) REFERENCES `job_order` (`job_order_id`),
  ADD CONSTRAINT `job_order_report_ibfk_2` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`);

--
-- Constraints for table `job_order_technicians`
--
ALTER TABLE `job_order_technicians`
  ADD CONSTRAINT `job_order_technicians_ibfk_1` FOREIGN KEY (`job_order_id`) REFERENCES `job_order` (`job_order_id`),
  ADD CONSTRAINT `job_order_technicians_ibfk_2` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`);

--
-- Constraints for table `technician_checklist_logs`
--
ALTER TABLE `technician_checklist_logs`
  ADD CONSTRAINT `technician_checklist_logs_ibfk_1` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`);

--
-- Constraints for table `technician_feedback`
--
ALTER TABLE `technician_feedback`
  ADD CONSTRAINT `technician_feedback_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `assessment_report` (`report_id`),
  ADD CONSTRAINT `technician_feedback_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`),
  ADD CONSTRAINT `technician_feedback_ibfk_3` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
