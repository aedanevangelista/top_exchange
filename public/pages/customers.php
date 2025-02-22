<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole(['admin', 'secretary']); // Only admins and secretaries can access

function handleAjaxResponse($success, $message = '', $reload = false) {
    echo json_encode(['success' => $success, 'message' => $message, 'reload' => $reload]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    try {
        if ($_POST['formType'] == 'add') {
            $customer_name = trim($_POST['customer_name']);
            $created_at = gmdate('Y-m-d H:i:s'); // Use gmdate to get UTC time

            // Check for duplicate customer name
            $checkStmt = $conn->prepare("SELECT customer_id FROM customers WHERE customer_name = ?");
            if ($checkStmt === false) throw new Exception($conn->error);

            $checkStmt->bind_param("s", $customer_name);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                handleAjaxResponse(false, "Customer name already exists.");
            }
            $checkStmt->close();

            $stmt = $conn->prepare("INSERT INTO customers (customer_name, created_at) VALUES (?, ?)");
            if ($stmt === false) throw new Exception($conn->error);

            $stmt->bind_param("ss", $customer_name, $created_at);
            if ($stmt->execute()) {
                handleAjaxResponse(true, "Customer added successfully.", true);
            } else {
                handleAjaxResponse(false, $stmt->error);
            }
            $stmt->close();
        }

        if ($_POST['formType'] == 'edit') {
            $customer_id = $_POST['customer_id'];
            $customer_name = trim($_POST['customer_name']);

            // Check for duplicate customer name
            $checkStmt = $conn->prepare("SELECT customer_id FROM customers WHERE customer_name = ? AND customer_id != ?");
            if ($checkStmt === false) throw new Exception($conn->error);

            $checkStmt->bind_param("si", $customer_name, $customer_id);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                handleAjaxResponse(false, "Customer name already exists.");
            }
            $checkStmt->close();

            $stmt = $conn->prepare("UPDATE customers SET customer_name = ? WHERE customer_id = ?");
            if ($stmt === false) throw new Exception($conn->error);

            $stmt->bind_param("si", $customer_name, $customer_id);
            if ($stmt->execute()) {
                handleAjaxResponse(true, "Customer updated successfully.", true);
            } else {
                handleAjaxResponse(false, $stmt->error);
            }
            $stmt->close();
        }

        if ($_POST['formType'] == 'delete') {
            $customer_id = $_POST['customer_id'];

            $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ?");
            if ($stmt === false) throw new Exception($conn->error);

            $stmt->bind_param("i", $customer_id);
            if ($stmt->execute()) {
                handleAjaxResponse(true, "Customer deleted successfully.", true);
            } else {
                handleAjaxResponse(false, $stmt->error);
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        handleAjaxResponse(false, $e->getMessage());
    }
}

$sql = "SELECT customer_id, customer_name, created_at FROM customers ORDER BY customer_id";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers</title>
    <link rel="stylesheet" href="../css/customers.css">
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <div class="main-content">
        <div class="accounts-header">
            <h1>Customers</h1>
            <button onclick="openAddCustomerForm()" class="add-account-btn">
                <i class="fas fa-user-plus"></i> Add New Customer
            </button>
        </div>
        <div class="accounts-table-container">
            <table class="accounts-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer Name</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $created_at = $row['created_at'];
                            echo "<tr>
                                    <td>{$row['customer_id']}</td>
                                    <td>{$row['customer_name']}</td>
                                    <td class='created-at' data-created-at='{$created_at}'></td>
                                    <td class='action-buttons'>
                                        <button class='edit-btn' data-id='{$row['customer_id']}' data-name='{$row['customer_name']}'><i class='fas fa-edit'></i> Edit</button>
                                        <button class='delete-btn' data-id='{$row['customer_id']}'><i class='fas fa-trash'></i> Delete</button>
                                    </td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' class='no-accounts'>No customers found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add/Edit Customer Modal -->
    <div id="customer-modal" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2 id="modal-title"><i class="fas fa-user-plus"></i> Add Customer</h2>
            <span id="customerError" class="error"></span>
            <form id="customer-form" class="account-form">
                <input type="hidden" name="formType" id="formType" value="add">
                <input type="hidden" name="customer_id" id="customer_id">
                <label for="customer_name">Customer Name:</label>
                <input type="text" name="customer_name" id="customer_name" required>
                <div class="form-buttons">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddCustomerForm()"><i class="fas fa-times"></i> Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Customer Confirmation Modal -->
    <div id="delete-modal" class="overlay" style="display: none;">
        <div class="overlay-content">
            <h2><i class="fas fa-trash"></i> Delete Customer</h2>
            <p>Are you sure you want to delete this customer?</p>
            <form id="delete-form" class="account-form">
                <input type="hidden" name="customer_id" id="delete_customer_id">
                <div class="form-buttons">
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteCustomerForm()"><i class="fas fa-times"></i> Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment-timezone/0.5.34/moment-timezone-with-data.min.js"></script>
    <script src="../js/customers.js"></script>
</body>
</html>