<?php
session_start();
require_once '../../db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if user is logged in as technician
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Create job_order_report table if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS job_order_report (
    id INT(11) NOT NULL AUTO_INCREMENT,
    job_order_id INT(11) NOT NULL,
    technician_id INT(11) NOT NULL,
    observation_notes TEXT NOT NULL,
    recommendation TEXT NOT NULL,
    attachments VARCHAR(255) DEFAULT NULL,
    chemical_usage TEXT DEFAULT NULL,
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (job_order_id) REFERENCES job_order(job_order_id),
    FOREIGN KEY (technician_id) REFERENCES technicians(technician_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$conn->query($createTableSQL);

// Add status column to job_order table if it doesn't exist
$checkStatusColumn = $conn->query("SHOW COLUMNS FROM job_order LIKE 'status'");
if ($checkStatusColumn->num_rows == 0) {
    $conn->query("ALTER TABLE job_order ADD COLUMN status VARCHAR(20) DEFAULT 'scheduled'");
}

// Handle POST request for creating a job order report
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Extract and validate required fields
    $job_order_id = isset($_POST['job_order_id']) ? intval($_POST['job_order_id']) : 0;
    $technician_id = $_SESSION['user_id'];
    $observation_notes = isset($_POST['observation_notes']) ? trim($_POST['observation_notes']) : '';
    $recommendation = isset($_POST['recommendation']) ? trim($_POST['recommendation']) : '';

    // Process chemical usage data if available
    $chemical_usage = null;
    if (isset($_POST['chemical_name']) && is_array($_POST['chemical_name']) &&
        isset($_POST['chemical_dosage']) && is_array($_POST['chemical_dosage'])) {

        $chemicals = [];
        $count = count($_POST['chemical_name']);

        for ($i = 0; $i < $count; $i++) {
            if (isset($_POST['chemical_name'][$i]) && isset($_POST['chemical_dosage'][$i])) {
                // Ensure dosage is a valid positive number
                $dosage = $_POST['chemical_dosage'][$i];

                // Remove any non-numeric characters except decimal point
                $dosage = preg_replace('/[^\d.]/', '', $dosage);

                // Ensure there's only one decimal point
                $parts = explode('.', $dosage);
                if (count($parts) > 2) {
                    $dosage = $parts[0] . '.' . implode('', array_slice($parts, 1));
                }

                // Convert to float
                $dosage = floatval($dosage);

                // If negative or NaN, set to 0
                if ($dosage < 0 || is_nan($dosage)) {
                    $dosage = 0;
                }

                // Get recommended dosage
                $recommended_dosage = isset($_POST['chemical_recommended_dosage'][$i]) ?
                    floatval($_POST['chemical_recommended_dosage'][$i]) : 0;

                // Add to chemicals array
                $chemicals[] = [
                    'name' => $_POST['chemical_name'][$i],
                    'type' => isset($_POST['chemical_type'][$i]) ? $_POST['chemical_type'][$i] : '',
                    'target_pest' => isset($_POST['chemical_target_pest'][$i]) ? $_POST['chemical_target_pest'][$i] : '',
                    'dosage' => $dosage,
                    'recommended_dosage' => $recommended_dosage,
                    'dosage_unit' => isset($_POST['chemical_dosage_unit'][$i]) ? $_POST['chemical_dosage_unit'][$i] : 'ml'
                ];
            }
        }

        if (!empty($chemicals)) {
            $chemical_usage = json_encode($chemicals);
        }
    }

    // Validate required fields
    $errors = [];
    if ($job_order_id <= 0) {
        $errors[] = 'Invalid job order ID';
    }
    if (empty($observation_notes)) {
        $errors[] = 'Observation notes are required';
    }
    if (empty($recommendation)) {
        $errors[] = 'Recommendation is required';
    }

    // Check if the technician is the primary technician for this job order
    $checkPrimaryStmt = $conn->prepare("SELECT is_primary FROM job_order_technicians
                                      WHERE job_order_id = ? AND technician_id = ?");
    $checkPrimaryStmt->bind_param("ii", $job_order_id, $technician_id);
    $checkPrimaryStmt->execute();
    $checkPrimaryResult = $checkPrimaryStmt->get_result();

    if ($checkPrimaryResult->num_rows === 0) {
        $errors[] = 'You are not assigned to this job order';
    } else {
        $primaryRow = $checkPrimaryResult->fetch_assoc();
        if (!(bool)$primaryRow['is_primary']) {
            $errors[] = 'Only the primary technician can submit reports for this job order';
        }
    }

    // If there are validation errors, return them
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
        exit;
    }

    // Handle file uploads
    $attachments = [];
    if (empty($_FILES['attachments']) || empty($_FILES['attachments']['name'][0])) {
        $errors[] = 'At least one attachment is required';
    } else {
        $uploadDir = '../../uploads/';

        // Create uploads directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($_FILES['attachments']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['attachments']['error'][$key] === 0) {
                $fileName = uniqid() . '_' . basename($_FILES['attachments']['name'][$key]);
                $targetPath = $uploadDir . $fileName;

                // Check file type (allow only images)
                $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
                if (!in_array($fileType, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $errors[] = 'Only JPG, JPEG, PNG & GIF files are allowed';
                    continue;
                }

                // Check file size (max 5MB)
                if ($_FILES['attachments']['size'][$key] > 5000000) {
                    $errors[] = 'File size should not exceed 5MB';
                    continue;
                }

                if (move_uploaded_file($tmpName, $targetPath)) {
                    $attachments[] = $fileName;
                } else {
                    $errors[] = 'Failed to upload file: ' . $_FILES['attachments']['name'][$key];
                }
            }
        }

        // Check if at least one attachment was successfully uploaded
        if (empty($attachments)) {
            $errors[] = 'Failed to upload any attachments. Please try again.';
        }
    }

    // If there are file upload errors, return them
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => 'File upload failed', 'errors' => $errors]);
        exit;
    }

    $attachmentsStr = implode(',', $attachments);

    // Start transaction to ensure data integrity
    $conn->begin_transaction();

    try {
        // Check if recommendation column exists
        $result = $conn->query("SHOW COLUMNS FROM job_order_report LIKE 'recommendation'");
        $recommendationExists = $result->num_rows > 0;

        // Check if chemical_usage column exists
        $result = $conn->query("SHOW COLUMNS FROM job_order_report LIKE 'chemical_usage'");
        $chemicalUsageExists = $result->num_rows > 0;

        // Insert job order report based on column existence
        if ($recommendationExists && $chemicalUsageExists) {
            $stmt = $conn->prepare("
                INSERT INTO job_order_report
                (job_order_id, technician_id, observation_notes, recommendation, attachments, chemical_usage)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iissss", $job_order_id, $technician_id, $observation_notes, $recommendation, $attachmentsStr, $chemical_usage);
        } elseif ($recommendationExists) {
            $stmt = $conn->prepare("
                INSERT INTO job_order_report
                (job_order_id, technician_id, observation_notes, recommendation, attachments)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iisss", $job_order_id, $technician_id, $observation_notes, $recommendation, $attachmentsStr);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO job_order_report
                (job_order_id, technician_id, observation_notes, attachments)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("iiss", $job_order_id, $technician_id, $observation_notes, $attachmentsStr);
        }

        if (!$stmt->execute()) {
            throw new Exception('Failed to insert job order report: ' . $conn->error);
        }

        $report_id = $conn->insert_id;

        // Update job order status to completed
        $updateStmt = $conn->prepare("UPDATE job_order SET status = 'completed' WHERE job_order_id = ?");
        $updateStmt->bind_param("i", $job_order_id);

        if (!$updateStmt->execute()) {
            throw new Exception('Failed to update job order status: ' . $conn->error);
        }

        // Log the update for debugging
        error_log("Updated job_order status to 'completed' for job_order_id: $job_order_id. Affected rows: " . $updateStmt->affected_rows);

        // Also update the status in the job_order_technicians table if it exists
        $checkJOTTable = $conn->query("SHOW TABLES LIKE 'job_order_technicians'");
        if ($checkJOTTable->num_rows > 0) {
            // Check if the table has a status column
            $checkStatusColumn = $conn->query("SHOW COLUMNS FROM job_order_technicians LIKE 'status'");
            if ($checkStatusColumn->num_rows > 0) {
                $updateJOTStmt = $conn->prepare("UPDATE job_order_technicians SET status = 'completed' WHERE job_order_id = ?");
                $updateJOTStmt->bind_param("i", $job_order_id);
                $updateJOTStmt->execute();
                error_log("Updated job_order_technicians status to 'completed' for job_order_id: $job_order_id. Affected rows: " . $updateJOTStmt->affected_rows);
            }
        }

        // Commit transaction
        $conn->commit();

        // Prepare report data based on column existence
        $reportData = [
            'id' => $report_id,
            'job_order_id' => $job_order_id,
            'technician_id' => $technician_id,
            'observation_notes' => $observation_notes,
            'attachments' => $attachmentsStr,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Add recommendation field if it exists
        if ($recommendationExists) {
            $reportData['recommendation'] = $recommendation;
        }

        // Add chemical usage field if it exists
        if ($chemicalUsageExists && $chemical_usage) {
            $reportData['chemical_usage'] = $chemical_usage;
        }

        // Return success response with report data
        echo json_encode([
            'success' => true,
            'message' => 'Job order report submitted successfully',
            'report' => $reportData
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

} else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['job_order_id'])) {
    // Handle GET request to fetch report data
    $job_order_id = intval($_GET['job_order_id']);

    $stmt = $conn->prepare("SELECT * FROM job_order_report WHERE job_order_id = ?");
    $stmt->bind_param("i", $job_order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $report = $result->fetch_assoc();
        echo json_encode(['success' => true, 'report' => $report]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No report found for this job order']);
    }

} else {
    // Invalid request method
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Close database connection
$conn->close();
?>
