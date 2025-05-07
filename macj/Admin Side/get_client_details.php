<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../db_connect.php';

header('Content-Type: application/json');

// Check if client_id is provided
if (!isset($_GET['client_id']) || empty($_GET['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Client ID is required']);
    exit;
}

$client_id = intval($_GET['client_id']);

try {
    // Get client details
    $stmt = $conn->prepare("SELECT * FROM clients WHERE client_id = ?");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Client not found']);
        exit;
    }
    
    $client = $result->fetch_assoc();
    
    // Clean location address (remove coordinates)
    if (!empty($client['location_address'])) {
        $client['location_address'] = preg_replace('/\[[-\d\.]+,[-\d\.]+\]$/', '', $client['location_address']);
    }
    
    // Get client's appointments
    $stmt = $conn->prepare("SELECT 
                            appointment_id, 
                            preferred_date, 
                            preferred_time, 
                            location_address, 
                            status, 
                            created_at 
                        FROM appointments 
                        WHERE client_id = ? 
                        ORDER BY preferred_date DESC");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $appointmentsResult = $stmt->get_result();
    
    $appointments = [];
    while ($row = $appointmentsResult->fetch_assoc()) {
        // Clean location address (remove coordinates)
        if (!empty($row['location_address'])) {
            $row['location_address'] = preg_replace('/\[[-\d\.]+,[-\d\.]+\]$/', '', $row['location_address']);
        }
        $appointments[] = $row;
    }
    
    echo json_encode([
        'success' => true, 
        'client' => $client,
        'appointments' => $appointments
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
