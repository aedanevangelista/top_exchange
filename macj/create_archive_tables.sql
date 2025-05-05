-- Create archive tables for the archiving system

-- Archive table for chemical_inventory
CREATE TABLE IF NOT EXISTS `archived_chemical_inventory` (
  `archive_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `created_at` timestamp NOT NULL,
  `target_pest` varchar(255) DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `scheduled_deletion_date` date NOT NULL,
  PRIMARY KEY (`archive_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Archive table for clients
CREATE TABLE IF NOT EXISTS `archived_clients` (
  `archive_id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `registered_at` timestamp NOT NULL,
  `location_address` varchar(255) DEFAULT NULL,
  `type_of_place` varchar(50) DEFAULT NULL,
  `location_lat` varchar(20) DEFAULT NULL,
  `location_lng` varchar(20) DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `scheduled_deletion_date` date NOT NULL,
  PRIMARY KEY (`archive_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Archive table for technicians
CREATE TABLE IF NOT EXISTS `archived_technicians` (
  `archive_id` int(11) NOT NULL AUTO_INCREMENT,
  `technician_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `tech_contact_number` varchar(20) NOT NULL,
  `tech_fname` varchar(50) NOT NULL,
  `tech_lname` varchar(50) NOT NULL,
  `technician_picture` varchar(255) NOT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `scheduled_deletion_date` date NOT NULL,
  PRIMARY KEY (`archive_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Archive table for tools_equipment
CREATE TABLE IF NOT EXISTS `archived_tools_equipment` (
  `archive_id` int(11) NOT NULL AUTO_INCREMENT,
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `scheduled_deletion_date` date NOT NULL,
  PRIMARY KEY (`archive_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
