<?php
session_start();
require_once '../db_config.php';
require_once '../notification_functions.php';

// Check if user is logged in as office staff
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'office_staff') {
    header("Location: SignIn.php");
    exit;
}

$message = '';
$error = '';
$debug_info = [];

// Get user information
$user_id = $_SESSION['user_id'];
$user_type = 'admin'; // Map office_staff to admin for notifications

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        // Create a test notification
        $title = "Test Notification";
        $message_text = "This is a test notification created at " . date('Y-m-d H:i:s');
        
        // Create notification directly using SQL
        try {
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, user_type, title, message, related_type, is_read, created_at) 
                                  VALUES (?, ?, ?, ?, 'test', 0, NOW())");
            $result = $stmt->execute([$user_id, $user_type, $title, $message_text]);
            
            if ($result) {
                $message = "Test notification created successfully!";
                $notification_id = $pdo->lastInsertId();
                $debug_info['created_notification_id'] = $notification_id;
            } else {
                $error = "Failed to create test notification.";
                $debug_info['pdo_error_info'] = $stmt->errorInfo();
            }
        } catch (Exception $e) {
            $error = "Exception: " . $e->getMessage();
            $debug_info['exception'] = $e->getMessage();
        }
    } elseif ($_POST['action'] === 'mark_read' && isset($_POST['notification_id'])) {
        // Mark a notification as read
        $notification_id = intval($_POST['notification_id']);
        
        try {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
            $result = $stmt->execute([$notification_id]);
            
            if ($result) {
                $message = "Notification #{$notification_id} marked as read!";
            } else {
                $error = "Failed to mark notification as read.";
                $debug_info['pdo_error_info'] = $stmt->errorInfo();
            }
        } catch (Exception $e) {
            $error = "Exception: " . $e->getMessage();
            $debug_info['exception'] = $e->getMessage();
        }
    } elseif ($_POST['action'] === 'mark_all_read') {
        // Mark all notifications as read
        try {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND user_type = ?");
            $result = $stmt->execute([$user_id, $user_type]);
            
            if ($result) {
                $message = "All notifications marked as read!";
            } else {
                $error = "Failed to mark all notifications as read.";
                $debug_info['pdo_error_info'] = $stmt->errorInfo();
            }
        } catch (Exception $e) {
            $error = "Exception: " . $e->getMessage();
            $debug_info['exception'] = $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['notification_id'])) {
        // Delete a notification
        $notification_id = intval($_POST['notification_id']);
        
        try {
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE notification_id = ?");
            $result = $stmt->execute([$notification_id]);
            
            if ($result) {
                $message = "Notification #{$notification_id} deleted!";
            } else {
                $error = "Failed to delete notification.";
                $debug_info['pdo_error_info'] = $stmt->errorInfo();
            }
        } catch (Exception $e) {
            $error = "Exception: " . $e->getMessage();
            $debug_info['exception'] = $e->getMessage();
        }
    }
}

// Get current notifications
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND user_type = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id, $user_type]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Failed to fetch notifications: " . $e->getMessage();
    $notifications = [];
}

// Get unread count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND user_type = ? AND is_read = 0");
    $stmt->execute([$user_id, $user_type]);
    $unread_count = $stmt->fetchColumn();
} catch (Exception $e) {
    $error = "Failed to fetch unread count: " . $e->getMessage();
    $unread_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Direct Notification Test - MacJ Pest Control</title>
    <link rel="stylesheet" href="css/reports-page.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <style>
        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            color: #3B82F6;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        button {
            background-color: #3B82F6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        
        button:hover {
            background-color: #2563EB;
        }
        
        .success-message {
            color: #10B981;
            padding: 10px;
            background-color: #ECFDF5;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .error-message {
            color: #EF4444;
            padding: 10px;
            background-color: #FEF2F2;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .notification-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .notification-table th, .notification-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #E5E7EB;
        }
        
        .notification-table th {
            background-color: #F3F4F6;
            font-weight: 600;
        }
        
        .notification-table tr:hover {
            background-color: #F9FAFB;
        }
        
        .notification-unread {
            font-weight: bold;
            background-color: #F0F7FF;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .action-buttons button {
            padding: 5px 10px;
            font-size: 14px;
        }
        
        .delete-btn {
            background-color: #EF4444;
        }
        
        .delete-btn:hover {
            background-color: #DC2626;
        }
        
        .mark-read-btn {
            background-color: #10B981;
        }
        
        .mark-read-btn:hover {
            background-color: #059669;
        }
        
        .debug-info {
            margin-top: 30px;
            padding: 15px;
            background-color: #F3F4F6;
            border-radius: 4px;
        }
        
        .debug-info h2 {
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .debug-info pre {
            background-color: #E5E7EB;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-bell"></i> Direct Notification Test</h1>
        
        <?php if (!empty($message)): ?>
        <div class="success-message">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="error-message">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="form-group">
            <form method="post">
                <input type="hidden" name="action" value="create">
                <button type="submit">Create Test Notification</button>
            </form>
        </div>
        
        <div class="form-group">
            <form method="post">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit">Mark All Notifications as Read</button>
            </form>
        </div>
        
        <h2>Your Notifications (<?php echo count($notifications); ?> total, <?php echo $unread_count; ?> unread)</h2>
        
        <?php if (count($notifications) > 0): ?>
        <table class="notification-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Message</th>
                    <th>Created</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notifications as $notification): ?>
                <tr class="<?php echo $notification['is_read'] == 0 ? 'notification-unread' : ''; ?>">
                    <td><?php echo $notification['notification_id']; ?></td>
                    <td><?php echo htmlspecialchars($notification['title']); ?></td>
                    <td><?php echo htmlspecialchars($notification['message']); ?></td>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($notification['created_at'])); ?></td>
                    <td><?php echo $notification['is_read'] == 1 ? 'Read' : 'Unread'; ?></td>
                    <td class="action-buttons">
                        <?php if ($notification['is_read'] == 0): ?>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="mark_read">
                            <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                            <button type="submit" class="mark-read-btn">Mark Read</button>
                        </form>
                        <?php endif; ?>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                            <button type="submit" class="delete-btn">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No notifications found.</p>
        <?php endif; ?>
        
        <?php if (!empty($debug_info)): ?>
        <div class="debug-info">
            <h2>Debug Information</h2>
            <pre><?php print_r($debug_info); ?></pre>
        </div>
        <?php endif; ?>
        
        <p><a href="reports.php">&larr; Back to Reports</a></p>
    </div>
</body>
</html>
