<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";

// Check if the user is logged in as an admin
if (!isset($_SESSION['admin_user_id'])) {
    // Redirect to admin login page
    header("Location: ../login.php");
    exit();
}

// Check role permission for Drivers
checkRole('Drivers');

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_driver'])) {
        // Add new driver
        $name = $conn->real_escape_string($_POST['name']);
        $address = $conn->real_escape_string($_POST['address']);
        $contact_no = $conn->real_escape_string($_POST['contact_no']);
        $availability = $conn->real_escape_string($_POST['availability']);
        
        $sql = "INSERT INTO drivers (name, address, contact_no, availability) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $name, $address, $contact_no, $availability);
        
        if ($stmt->execute()) {
            $success_message = "Driver added successfully";
        } else {
            $error_message = "Error: " . $stmt->error;
        }
        $stmt->close();
    } elseif (isset($_POST['update_driver'])) {
        // Update driver
        $driver_id = $conn->real_escape_string($_POST['driver_id']);
        $name = $conn->real_escape_string($_POST['name']);
        $address = $conn->real_escape_string($_POST['address']);
        $contact_no = $conn->real_escape_string($_POST['contact_no']);
        $availability = $conn->real_escape_string($_POST['availability']);
        
        $sql = "UPDATE drivers SET name = ?, address = ?, contact_no = ?, availability = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $name, $address, $contact_no, $availability, $driver_id);
        
        if ($stmt->execute()) {
            $success_message = "Driver updated successfully";
        } else {
            $error_message = "Error: " . $stmt->error;
        }
        $stmt->close();
    } elseif (isset($_POST['delete_driver'])) {
        // Delete driver
        $driver_id = $conn->real_escape_string($_POST['driver_id']);
        
        $sql = "DELETE FROM drivers WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $driver_id);
        
        if ($stmt->execute()) {
            $success_message = "Driver deleted successfully";
        } else {
            $error_message = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get all drivers
$sql = "SELECT * FROM drivers ORDER BY name ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drivers Management</title>
    <link rel="stylesheet" href="/css/dashboard.css">
    <link rel="stylesheet" href="/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .drivers-container {
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .drivers-form {
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .button-group {
            margin-top: 15px;
        }
        
        .button-group button {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .button-group button.primary {
            background-color: #4CAF50;
            color: white;
        }
        
        .drivers-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .drivers-table th, .drivers-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .drivers-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        
        .status-available {
            color: green;
            font-weight: bold;
        }
        
        .status-unavailable {
            color: red;
            font-weight: bold;
        }
        
        .action-buttons button {
            margin-right: 5px;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .edit-btn {
            background-color: #2196F3;
            color: white;
        }
        
        .delete-btn {
            background-color: #f44336;
            color: white;
        }
        
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .success {
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }
        
        .error {
            background-color: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../sidebar.php'; ?>
        
        <div class="main-content">
            <div class="overview-container">
                <h2>Drivers Management</h2>
            </div>
            
            <div class="drivers-container">
                <?php if (isset($success_message)): ?>
                    <div class="message success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="message error"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <div class="drivers-form">
                    <h3>Add New Driver</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="name">Name</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" required>
                        </div>
                        <div class="form-group">
                            <label for="contact_no">Contact No.</label>
                            <input type="text" id="contact_no" name="contact_no" required>
                        </div>
                        <div class="form-group">
                            <label for="availability">Availability</label>
                            <select id="availability" name="availability" required>
                                <option value="Available">Available</option>
                                <option value="Not Available">Not Available</option>
                            </select>
                        </div>
                        <div class="button-group">
                            <button type="submit" name="add_driver" class="primary">Add Driver</button>
                        </div>
                    </form>
                </div>
                
                <h3>Drivers List</h3>
                <table class="drivers-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Address</th>
                            <th>Contact No.</th>
                            <th>Availability</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['address']); ?></td>
                                    <td><?php echo htmlspecialchars($row['contact_no']); ?></td>
                                    <td class="<?php echo $row['availability'] == 'Available' ? 'status-available' : 'status-unavailable'; ?>">
                                        <?php echo htmlspecialchars($row['availability']); ?>
                                    </td>
                                    <td class="action-buttons">
                                        <button class="edit-btn" onclick="editDriver(<?php echo $row['id']; ?>, '<?php echo addslashes($row['name']); ?>', '<?php echo addslashes($row['address']); ?>', '<?php echo addslashes($row['contact_no']); ?>', '<?php echo $row['availability']; ?>')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="delete-btn" onclick="deleteDriver(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No drivers found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Driver Modal -->
    <div id="editModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
        <div style="background-color: white; margin: 10% auto; padding: 20px; border-radius: 8px; width: 60%; box-shadow: 0 0 20px rgba(0,0,0,0.2);">
            <span onclick="document.getElementById('editModal').style.display='none'" style="float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
            <h3>Edit Driver</h3>
            <form method="POST" action="" id="editForm">
                <input type="hidden" id="edit_driver_id" name="driver_id">
                <div class="form-group">
                    <label for="edit_name">Name</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="edit_address">Address</label>
                    <input type="text" id="edit_address" name="address" required>
                </div>
                <div class="form-group">
                    <label for="edit_contact_no">Contact No.</label>
                    <input type="text" id="edit_contact_no" name="contact_no" required>
                </div>
                <div class="form-group">
                    <label for="edit_availability">Availability</label>
                    <select id="edit_availability" name="availability" required>
                        <option value="Available">Available</option>
                        <option value="Not Available">Not Available</option>
                    </select>
                </div>
                <div class="button-group">
                    <button type="submit" name="update_driver" class="primary">Update Driver</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
        <div style="background-color: white; margin: 15% auto; padding: 20px; border-radius: 8px; width: 40%; box-shadow: 0 0 20px rgba(0,0,0,0.2);">
            <h3>Confirm Delete</h3>
            <p>Are you sure you want to delete this driver?</p>
            <form method="POST" action="" id="deleteForm">
                <input type="hidden" id="delete_driver_id" name="driver_id">
                <div class="button-group">
                    <button type="button" onclick="document.getElementById('deleteModal').style.display='none'" style="background-color: #ccc;">Cancel</button>
                    <button type="submit" name="delete_driver" style="background-color: #f44336; color: white;">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editDriver(id, name, address, contact_no, availability) {
            document.getElementById('edit_driver_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_address').value = address;
            document.getElementById('edit_contact_no').value = contact_no;
            document.getElementById('edit_availability').value = availability;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function deleteDriver(id) {
            document.getElementById('delete_driver_id').value = id;
            document.getElementById('deleteModal').style.display = 'block';
        }
    </script>
</body>
</html>