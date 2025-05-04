<?php
// This file can be run via cron job once per day to automatically update order statuses
// Recommended to run this early morning (e.g., 5:00 AM) every day

include "db_connection.php";

// Get current date
$current_date = date('Y-m-d');
error_log("Running auto_transit_orders.php for date: $current_date");

// Update orders whose delivery date is today to "In Transit"
$conn->begin_transaction();

try {
    // First, get orders that need to be updated
    $select_stmt = $conn->prepare("
        SELECT po_number FROM orders 
        WHERE delivery_date = ? 
        AND status = 'For Delivery'
    ");
    $select_stmt->bind_param("s", $current_date);
    $select_stmt->execute();
    $result = $select_stmt->get_result();
    
    $updated_orders = [];
    while ($row = $result->fetch_assoc()) {
        $updated_orders[] = $row['po_number'];
    }
    
    $count = count($updated_orders);
    error_log("Found $count orders to update to In Transit status");
    
    if ($count > 0) {
        // Update the orders to "In Transit"
        $update_stmt = $conn->prepare("
            UPDATE orders SET status = 'In Transit' 
            WHERE delivery_date = ? 
            AND status = 'For Delivery'
        ");
        $update_stmt->bind_param("s", $current_date);
        $update_stmt->execute();
        
        // Update the driver assignments
        $update_driver_stmt = $conn->prepare("
            UPDATE driver_assignments 
            SET status = 'In Transit' 
            WHERE po_number IN (
                SELECT po_number FROM orders 
                WHERE delivery_date = ? 
                AND status = 'In Transit'
            )
        ");
        $update_driver_stmt->bind_param("s", $current_date);
        $update_driver_stmt->execute();
        
        // Log the status changes
        foreach ($updated_orders as $po_number) {
            $log_stmt = $conn->prepare("
                INSERT INTO order_status_logs 
                (po_number, old_status, new_status, changed_by, changed_at) 
                VALUES (?, 'For Delivery', 'In Transit', 'auto_system', NOW())
            ");
            $log_stmt->bind_param("s", $po_number);
            $log_stmt->execute();
        }
    }
    
    $conn->commit();
    error_log("Successfully updated $count orders to In Transit status");
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error updating orders to In Transit: " . $e->getMessage());
}

// Close connection
$conn->close();
?>