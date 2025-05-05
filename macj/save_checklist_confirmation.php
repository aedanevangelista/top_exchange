<?php
/**
 * Save technician checklist confirmation
 */
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

// Check if user is a technician
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get technician ID from session
$technician_id = $_SESSION['user_id'];

// Get data from POST request
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

// Extract data
$checked_items_raw = isset($data['checked_items']) ? $data['checked_items'] : [];
// Extract only the IDs from the checked items
$checked_item_ids = array_map(function($item) {
    return (int)$item['id'];
}, $checked_items_raw);
$checked_items = json_encode($checked_item_ids);
$total_items = isset($data['total_items']) ? (int)$data['total_items'] : 0;
$checked_count = isset($data['checked_count']) ? (int)$data['checked_count'] : 0;
$checklist_date = date('Y-m-d'); // Today's date

try {
    // Check if a log already exists for today
    $check_stmt = $conn->prepare("SELECT log_id FROM technician_checklist_logs WHERE technician_id = ? AND checklist_date = ?");
    $check_stmt->bind_param("is", $technician_id, $checklist_date);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        // Update existing log
        $log = $result->fetch_assoc();
        $log_id = $log['log_id'];

        $update_stmt = $conn->prepare("UPDATE technician_checklist_logs SET checked_items = ?, total_items = ?, checked_count = ? WHERE log_id = ?");
        $update_stmt->bind_param("siii", $checked_items, $total_items, $checked_count, $log_id);

        if ($update_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Checklist confirmation updated']);
        } else {
            throw new Exception("Failed to update checklist confirmation: " . $conn->error);
        }
    } else {
        // Insert new log
        $insert_stmt = $conn->prepare("INSERT INTO technician_checklist_logs (technician_id, checklist_date, checked_items, total_items, checked_count) VALUES (?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("issii", $technician_id, $checklist_date, $checked_items, $total_items, $checked_count);

        if ($insert_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Checklist confirmation saved']);
        } else {
            throw new Exception("Failed to save checklist confirmation: " . $conn->error);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
