<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";

// Ensure only admins can access this page
if (!isset($_SESSION['admin_user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: ../login.php");
    exit;
}

$message = "";
$updated_count = 0;

// Process auto-update if requested
if (isset($_POST['run_update'])) {
    // Get current date
    $current_date = date('Y-m-d');
    
    // Begin transaction
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
        
        $updated_count = count($updated_orders);
        
        if ($updated_count > 0) {
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
                    VALUES (?, 'For Delivery', 'In Transit', ?, NOW())
                ");
                $changed_by = $_SESSION['username'] ?? 'manual_update';
                $log_stmt->bind_param("ss", $po_number, $changed_by);
                $log_stmt->execute();
            }
            
            $message = "Successfully updated $updated_count orders to In Transit status";
        } else {
            $message = "No orders needed to be updated today";
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error: " . $e->getMessage();
    }
}

// Get count of orders that would be updated if run now
$current_date = date('Y-m-d');
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM orders 
    WHERE delivery_date = ? 
    AND status = 'For Delivery'
");
$count_stmt->bind_param("s", $current_date);
$count_stmt->execute();
$count_result = $count_stmt->get_result()->fetch_assoc();
$pending_count = $count_result['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Update Orders</title>
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .main-content {
            padding: 20px;
        }
        
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .card-title {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #f1f1f1;
            padding-bottom: 10px;
        }
        
        .info-section {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 4px;
        }
        
        .status-section {
            margin-bottom: 20px;
        }
        
        .status-count {
            font-size: 18px;
            font-weight: bold;
            color: #fd7e14;
        }
        
        .message {
            padding: 10px;
            margin: 15px 0;
            border-radius: 4px;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message.info {
            background-color: #e2f0fb;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .btn {
            display: inline-block;
            font-weight: 400;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            user-select: none;
            border: 1px solid transparent;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            border-radius: 0.25rem;
            transition: all 0.15s ease-in-out;
            cursor: pointer;
        }
        
        .btn-primary {
            color: #fff;
            background-color: #fd7e14;
            border-color: #fd7e14;
        }
        
        .btn-primary:hover {
            background-color: #e67211;
            border-color: #e67211;
        }
        
        .btn-primary:disabled {
            background-color: #fda666;
            border-color: #fda666;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <?php include '../../public/sidebar.php'; ?>
    <div class="main-content">
        <div class="card">
            <h1 class="card-title">
                <i class="fas fa-sync"></i> Auto Update Deliverable Orders
            </h1>
            
            <div class="info-section">
                <p><strong>Current Date:</strong> <?= date('Y-m-d') ?></p>
                <p><strong>Purpose:</strong> This tool automatically updates orders with today's delivery date from "For Delivery" to "In Transit" status.</p>
                <p><strong>Note:</strong> This process also runs automatically when users visit the Deliverable Orders page.</p>
            </div>
            
            <div class="status-section">
                <p>There are currently <span class="status-count"><?= $pending_count ?></span> orders with today's delivery date that could be updated to "In Transit".</p>
                
                <?php if (!empty($message)): ?>
                    <div class="message <?= $updated_count > 0 ? 'success' : ($pending_count > 0 ? 'info' : 'error') ?>">
                        <?= $message ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <button type="submit" name="run_update" class="btn btn-primary" <?= $pending_count == 0 ? 'disabled' : '' ?>>
                        <i class="fas fa-play"></i> Run Auto Update Now
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>