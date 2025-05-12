-- Add payment_method and payment_status columns to the orders table if they don't exist
ALTER TABLE `orders` 
ADD COLUMN IF NOT EXISTS `payment_method` VARCHAR(50) DEFAULT 'check_payment' AFTER `item_progress_percentages`,
ADD COLUMN IF NOT EXISTS `payment_status` VARCHAR(20) DEFAULT 'Pending' AFTER `payment_method`;
