<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole(['admin', 'secretary']); // Only admins and secretaries can access

// Enable error logging
ini_set('log_errors', 'On');
ini_set('error_log', 'error_log.log');
error_reporting(E_ALL);

function handleAjaxResponse($success, $message = '', $reload = false) {
    echo json_encode(['success' => $success, 'message' => $message, 'reload' => $reload]);
    exit;
}

function handleDatabaseError($stmt) {
    handleAjaxResponse(false, $stmt->error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    try {
        if ($_POST['formType'] == 'add') {
            $customer_name = trim($_POST['customer_name']);
            $contact_number = trim($_POST['contact_number']) ?: null;
            $email = trim($_POST['email']) ?: null;
            $address = trim($_POST['address']);
            $created_at = date('Y-m-d H:i:s');

            $checkStmt = $conn->prepare("SELECT customer_id FROM customers WHERE email = ?");
            if ($checkStmt === false) throw new Exception($conn->error);

            $checkStmt->bind_param("s", $email);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0 && $email !== null) {
                handleAjaxResponse(false, 'Customer with this email already exists.');
            }
            $checkStmt->close();

            $stmt = $conn->prepare("INSERT INTO customers (customer_name, contact_number, email, address, created_at) VALUES (?, ?, ?, ?, ?)");
            if ($stmt === false) throw new Exception($conn->error);

            $stmt->bind_param("sssss", $customer_name, $contact_number, $email, $address, $created_at);
            if ($stmt->execute()) {
                handleAjaxResponse(true, 'Customer added successfully.', true);
            } else {
                handleDatabaseError($stmt);
            }
            $stmt->close();
        }

        if ($_POST['formType'] == 'edit') {
            $customer_id = $_POST['customer_id'];
            $customer_name = trim($_POST['customer_name']);
            $contact_number = trim($_POST['contact_number']) ?: null;
            $email = trim($_POST['email']) ?: null;
            $address = trim($_POST['address']);

            $stmt = $conn->prepare("UPDATE customers SET customer_name = ?, contact_number = ?, email = ?, address = ? WHERE customer_id = ?");
            if ($stmt === false) throw new Exception($conn->error);

            $stmt->bind_param("ssssi", $customer_name, $contact_number, $email, $address, $customer_id);
            if ($stmt->execute()) {
                handleAjaxResponse(true, 'Customer edited successfully.', true);
            } else {
                handleDatabaseError($stmt);
            }
            $stmt->close();
        }

        if ($_POST['formType'] == 'delete') {
            $customer_id = $_POST['customer_id'];

            $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ?");
            if ($stmt === false) throw new Exception($conn->error);

            $stmt->bind_param("i", $customer_id);
            if ($stmt->execute()) {
                handleAjaxResponse(true, 'Customer deleted successfully.', true);
            } else {
                handleDatabaseError($stmt);
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        handleAjaxResponse(false, $e->getMessage());
    }
}

$sql = "SELECT customer_id, customer_name, contact_number, email, address FROM customers ORDER BY customer_name";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers</title>
    <link rel="stylesheet" href="../css/customer.css">
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>

    <!-- Sidebar -->
    <?php include '../sidebar.php'; ?>

    <div class="main-content">
        <div class="accounts-header">
            <h1>Customers</h1>
            <button id="add-customer-btn" class="add-account-btn"><i class="fas fa-plus"></i> Add Customer</button>
        </div>

        <div class="accounts-table-container">
            <table class="accounts-table">
                <thead>
                    <tr>
                        <th>Customer ID</th>
                        <th>Customer Name</th>
                        <th>Contact Number</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>
                                    <td>{$row['customer_id']}</td>
                                    <td>{$row['customer_name']}</td>
                                    <td>{$row['contact_number']}</td>
                                    <td>{$row['email']}</td>
                                    <td>{$row['address']}</td>
                                    <td class='action-buttons'>
                                        <button class='edit-btn' data-id='{$row['customer_id']}'><i class='fas fa-edit'></i> Edit</button>
                                        <button class='delete-btn' data-id='{$row['customer_id']}'><i class='fas fa-trash'></i> Delete</button>
                                    </td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' class='no-accounts'>No customers found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add/Edit Customer Modal -->
    <div id="customer-modal" class="overlay" style="display: none;">
        <div class="overlay-content">
            <span class="close">&times;</span>
            <h2 id="modal-title">Add Customer</h2>
            <form id="customer-form" class="account-form">
                <input type="hidden" name="formType" id="formType" value="add">
                <input type="hidden" name="customer_id" id="customer_id">
                <label for="customer_name">Customer Name:</label>
                <input type="text" name="customer_name" id="customer_name" required>
                <label for="contact_number">Contact Number:</label>
                <input type="text" name="contact_number" id="contact_number">
                <label for="email">Email:</label>
                <input type="email" name="email" id="email">
                <label for="address">Address:</label>
                <input type="text" name="address" id="address" required>
                <div class="form-buttons">
                    <button type="submit" class="save-btn">Submit</button>
                    <button type="button" class="cancel-btn">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/customer.js"></script>
</body>
</html>