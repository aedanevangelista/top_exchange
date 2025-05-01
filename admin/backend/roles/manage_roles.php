<?php
session_start();
include "../db_connection.php";

// Function to respond with JSON
function jsonResponse($success, $message, $redirect = null) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($redirect) {
        $response['redirect'] = $redirect;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $roleId = $_POST['role_id'] ?? '';
    $roleName = trim($_POST['role_name'] ?? '');
    $pageIds = isset($_POST['page_ids']) ? implode(", ", $_POST['page_ids']) : '';
    
    // Determine if this is an AJAX request
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    // Prevent modifying the 'admin' role
    if ($roleName === 'admin') {
        if ($isAjax) {
            jsonResponse(false, "The 'admin' role cannot be modified.");
        } else {
            header("Location: ../../public/pages/user_roles.php?error=restricted");
        }
        exit;
    }

    // Check if role name already exists (except for the current role being edited)
    $stmt = $conn->prepare("SELECT role_id FROM roles WHERE role_name = ? AND role_id != ?");
    $stmt->bind_param("si", $roleName, $roleId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        if ($isAjax) {
            jsonResponse(false, "Role name already exists. Please choose a different name.");
        } else {
            header("Location: ../../public/pages/user_roles.php?error=duplicate");
        }
        exit;
    }
    $stmt->close();
    
    // Perform action based on the request
    $successMessage = '';
    
    if ($action === 'add') {
        // Add new role
        $stmt = $conn->prepare("INSERT INTO roles (role_name, pages, status) VALUES (?, ?, 'active')");
        $stmt->bind_param("ss", $roleName, $pageIds);
        $stmt->execute();
        $stmt->close();
        $successMessage = 'added';
    } elseif ($action === 'edit' && !empty($roleId)) {
        // Edit existing role
        $stmt = $conn->prepare("UPDATE roles SET role_name = ?, pages = ? WHERE role_id = ?");
        $stmt->bind_param("ssi", $roleName, $pageIds, $roleId);
        $stmt->execute();
        $stmt->close();
        $successMessage = 'edited';
    } elseif ($action === 'archive' && !empty($roleId)) {
        // Archive role
        $stmt = $conn->prepare("UPDATE roles SET status = 'inactive' WHERE role_id = ?");
        $stmt->bind_param("i", $roleId);
        $stmt->execute();
        $stmt->close();
        $successMessage = 'archived';
    } elseif ($action === 'activate' && !empty($roleId)) {
        // Activate role
        $stmt = $conn->prepare("UPDATE roles SET status = 'active' WHERE role_id = ?");
        $stmt->bind_param("i", $roleId);
        $stmt->execute();
        $stmt->close();
        $successMessage = 'activated';
    }

    // Send response based on request type
    if ($isAjax) {
        jsonResponse(true, "Operation completed successfully.");
    } else {
        header("Location: ../../public/pages/user_roles.php?success=" . $successMessage);
    }
    exit;
}
?>