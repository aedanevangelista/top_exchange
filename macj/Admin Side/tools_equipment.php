<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: ../SignIn.php");
    exit;
}
require_once '../db_connect.php';
require_once '../notification_functions.php';

// Get Dashboard Metrics
try {
    // Check if the tools_equipment table exists
    $result = $conn->query("SHOW TABLES LIKE 'tools_equipment'");
    if ($result->num_rows == 0) {
        // Table doesn't exist, show a message with a link to create it
        echo '<div style="padding: 20px; background-color: #f8d7da; color: #721c24; margin: 20px; border-radius: 5px;">
                <h3>Table Not Found</h3>
                <p>The tools_equipment table does not exist in the database.</p>
                <p><a href="create_tools_table.php" class="btn btn-primary">Create Table and Add Sample Data</a></p>
              </div>';
        exit;
    }

    // Total Tools and Equipment
    $result = $conn->query("SELECT COUNT(*) AS total FROM tools_equipment");
    $row = $result->fetch_assoc();
    $total_tools = $row['total'];

    // We're removing the quantity field, so we don't need this query anymore

    // Count by Category
    $result = $conn->query("SELECT category, COUNT(*) as count FROM tools_equipment GROUP BY category");
    $category_counts = [];
    while ($cat = $result->fetch_assoc()) {
        $category_counts[$cat['category']] = $cat['count'];
    }

} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Handle form submission for NEW tool
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        $name = $_POST['name'];
        $category = $_POST['category'];
        $description = $_POST['description'] ?? null;

        $stmt = $conn->prepare("INSERT INTO tools_equipment
                (name, category, description)
                VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $category, $description);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Tool added successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to add tool']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Get all tools and equipment
try {
    $baseQuery = "SELECT * FROM tools_equipment";

    // Category filter
    if (isset($_GET['category']) && !empty($_GET['category'])) {
        $category = $_GET['category'];
        $baseQuery .= " WHERE category = '" . $conn->real_escape_string($category) . "'";
    }

    // Sorting
    $baseQuery .= " ORDER BY category, name";

    // Execute query
    $result = $conn->query($baseQuery);
    $tools = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tools[] = $row;
        }
    }
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tools and Equipment - MacJ Pest Control</title>
    <link rel="stylesheet" href="css/tools-equipment-page.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <style>
        /* Additional notification styles for Admin Side */
        .notification-container {
            position: relative;
            margin-right: 20px;
            cursor: pointer;
        }

        .notification-icon {
            font-size: 1.5rem;
            color: var(--primary-color);
            transition: color 0.3s ease;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #e74c3c;
            color: white;
            font-size: 0.75rem;
            font-weight: bold;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 350px;
            max-height: 400px;
            overflow-y: auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            display: none;
        }

        .notification-dropdown.show {
            display: block;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-color);
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--text-light);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-title">
            <h1>Admin Dashboard</h1>
        </div>
        <div class="user-menu">
            <!-- Notification Icon -->
            <div class="notification-container">
                <i class="fas fa-bell notification-icon"></i>
                <span class="notification-badge" style="display: none;">0</span>

                <!-- Notification Dropdown -->
                <div class="notification-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <span class="mark-all-read">Mark all as read</span>
                    </div>
                    <ul class="notification-list">
                        <!-- Notifications will be loaded here -->
                    </ul>
                </div>
            </div>

            <div class="user-info">
                <?php
                // Check if profile picture exists
                $staff_id = $_SESSION['user_id'];
                $profile_picture = '';

                // Check if the office_staff table has profile_picture column
                $result = $conn->query("SHOW COLUMNS FROM office_staff LIKE 'profile_picture'");
                if ($result->num_rows > 0) {
                    $stmt = $conn->prepare("SELECT profile_picture FROM office_staff WHERE staff_id = ?");
                    $stmt->bind_param("i", $staff_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $profile_picture = $row['profile_picture'];
                    }
                }

                $profile_picture_url = !empty($profile_picture)
                    ? "../uploads/admin/" . $profile_picture
                    : "../assets/default-profile.jpg";
                ?>
                <img src="<?php echo $profile_picture_url; ?>" alt="Profile" class="user-avatar" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;">
                <div>
                    <div class="user-name"><?= $_SESSION['username'] ?? 'Admin' ?></div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>MacJ Pest Control</h2>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                    <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                    <li><a href="calendar.php"><i class="fas fa-calendar-alt"></i> Calendar</a></li>
                    <li><a href="assessment_report.php"><i class="fas fa-clipboard-check"></i> Assessment Report</a></li>
                    <li><a href="joborder_report.php"><i class="fas fa-tasks"></i> Job Order Report</a></li>
                    <li><a href="chemical_inventory.php"><i class="fas fa-flask"></i> Chemical Inventory</a></li>
                    <li class="active"><a href="tools_equipment.php"><i class="fas fa-tools"></i> Tools and Equipment</a></li>
                    <li><a href="technicians.php"><i class="fas fa-user-md"></i> Technicians</a></li>
                    <li><a href="clients.php"><i class="fas fa-users"></i> Clients</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="../SignOut.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="tools-content">
                <div class="tools-header">
                    <h1>Tools and Equipment</h1>
                    <div>
                        <a href="tools_archive.php" class="btn btn-secondary mr-2">
                            <i class="fas fa-archive"></i> View Archive
                        </a>
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#toolModal">
                            <i class="fas fa-plus"></i> Add New Tool/Equipment
                        </button>
                    </div>
                </div>

                <!-- Inventory Summary -->
                <div class="inventory-summary">
                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--primary-color);">
                            <i class="fas fa-tools"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Total Items</h3>
                            <p><?= $total_tools ?></p>
                        </div>
                    </div>



                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--info-color);">
                            <i class="fas fa-spray-can"></i>
                        </div>
                        <div class="summary-info">
                            <h3>General Pest Control</h3>
                            <p><?= $category_counts['General Pest Control'] ?? 0 ?></p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-icon" style="background-color: var(--warning-color);">
                            <i class="fas fa-bug"></i>
                        </div>
                        <div class="summary-info">
                            <h3>Termite Equipment</h3>
                            <p><?= ($category_counts['Termite'] ?? 0) + ($category_counts['Termite Treatment'] ?? 0) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Filter Controls -->
                <div class="filter-container">
                    <form id="filterForm" method="GET" style="display: flex; flex-wrap: wrap; gap: 15px; width: 100%;">
                        <div class="filter-group">
                            <label for="category-filter">Category:</label>
                            <select id="category-filter" name="category" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <option value="General Pest Control" <?= isset($_GET['category']) && $_GET['category'] === 'General Pest Control' ? 'selected' : '' ?>>General Pest Control</option>
                                <option value="Termite" <?= isset($_GET['category']) && $_GET['category'] === 'Termite' ? 'selected' : '' ?>>Termite</option>
                                <option value="Termite Treatment" <?= isset($_GET['category']) && $_GET['category'] === 'Termite Treatment' ? 'selected' : '' ?>>Termite Treatment</option>
                                <option value="Weed Control" <?= isset($_GET['category']) && $_GET['category'] === 'Weed Control' ? 'selected' : '' ?>>Weed Control</option>
                                <option value="Bed Bugs" <?= isset($_GET['category']) && $_GET['category'] === 'Bed Bugs' ? 'selected' : '' ?>>Bed Bugs</option>
                            </select>
                        </div>
                    </form>
                </div>

                <div class="tools-table-container">
                    <table class="tools-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tools as $tool): ?>
                            <tr>
                                <td><?= $tool['id'] ?></td>
                                <td><?= htmlspecialchars($tool['name']) ?></td>
                                <td>
                                    <span class="category-badge <?= strtolower(str_replace(' ', '-', $tool['category'])) === 'general-pest-control' ? 'category-general' :
                                        (strtolower(str_replace(' ', '-', $tool['category'])) === 'termite' ? 'category-termite' :
                                        (strtolower(str_replace(' ', '-', $tool['category'])) === 'termite-treatment' ? 'category-termite-treatment' :
                                        (strtolower(str_replace(' ', '-', $tool['category'])) === 'weed-control' ? 'category-weed' : 'category-bed-bugs'))) ?>">
                                        <?= $tool['category'] ?>
                                    </span>
                                </td>

                                <td><?= date('M d, Y', strtotime($tool['updated_at'])) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-sm btn-info view-btn" data-id="<?= $tool['id'] ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn-sm btn-primary edit-btn" data-id="<?= $tool['id'] ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-sm btn-danger delete-btn" data-id="<?= $tool['id'] ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Create Modal -->
                <div class="modal fade" id="toolModal">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <form id="toolForm">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title"><i class="fas fa-tools mr-2"></i>Add New Tool/Equipment</h5>
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                </div>
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="required">Name</label>
                                                <input type="text" class="form-control" name="name" required>
                                            </div>
                                            <div class="form-group">
                                                <label class="required">Category</label>
                                                <select class="form-control" name="category" required>
                                                    <option value="">Select Category</option>
                                                    <option>General Pest Control</option>
                                                    <option>Termite</option>
                                                    <option>Termite Treatment</option>
                                                    <option>Weed Control</option>
                                                    <option>Bed Bugs</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Description</label>
                                                <textarea class="form-control" name="description" rows="3" placeholder="Brief description of the tool/equipment"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Tool</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- View Tool Modal -->
                <div class="modal fade" id="viewToolModal">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-info text-white">
                                <h5 class="modal-title"><i class="fas fa-eye mr-2"></i>Tool/Equipment Details</h5>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="detail-item">
                                            <dt>Name</dt>
                                            <dd id="viewName"></dd>
                                        </div>

                                        <div class="detail-item">
                                            <dt>Category</dt>
                                            <dd id="viewCategory"></dd>
                                        </div>
                                    </div>
                                    <div class="col-md-6">


                                        <div class="detail-item">
                                            <dt>Description</dt>
                                            <dd id="viewDescription"></dd>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Modal -->
                <div class="modal fade" id="editToolModal">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <form id="editToolForm">
                                <div class="modal-header bg-warning text-white">
                                    <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Tool/Equipment</h5>
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="id" id="editToolId">

                                    <div class="alert alert-info mb-4">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        <strong>Note:</strong> All fields are displayed for reference only. To modify a tool, please delete it and create a new one.
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Name</label>
                                                <input type="text" class="form-control" id="editName" readonly>
                                            </div>
                                            <div class="form-group">
                                                <label>Category</label>
                                                <input type="text" class="form-control" id="editCategory" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Description</label>
                                                <textarea class="form-control" id="editDescription" rows="3" readonly></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Create new tool/equipment
        $('#toolForm').submit(function(e) {
            e.preventDefault();
            const formData = $(this).serialize();

            $.ajax({
                type: 'POST',
                url: 'tools_equipment.php',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#toolModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Error: ' + (response.error || 'Failed to save tool/equipment'));
                    }
                }
            });
        });

        // View tool details in edit modal
        $(document).on('click', '.edit-btn', function() {
            const toolId = $(this).data('id');

            $.ajax({
                url: 'get_tool.php',
                method: 'GET',
                data: { id: toolId },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        $('#editToolId').val(response.data.id);
                        $('#editName').val(response.data.name);
                        $('#editCategory').val(response.data.category);
                        $('#editDescription').val(response.data.description);
                        $('#editToolModal').modal('show');
                    }
                }
            });
        });

        // Delete tool/equipment
        $(document).on('click', '.delete-btn', function() {
            const toolId = $(this).data('id');
            if(confirm('WARNING: This will permanently delete the record!\n\nProceed?')) {
                $.ajax({
                    url: 'delete_tool.php',
                    method: 'POST',
                    data: { id: toolId },
                    dataType: 'json',
                    success: function(response) {
                        if(response.success) location.reload();
                    }
                });
            }
        });

        // View tool/equipment
        $(document).on('click', '.view-btn', function() {
            const toolId = $(this).data('id');

            $.ajax({
                url: 'get_tool.php',
                method: 'GET',
                data: { id: toolId },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        // Populate view modal
                        $('#viewName').text(response.data.name);
                        $('#viewCategory').text(response.data.category);
                        $('#viewDescription').text(response.data.description || 'No description');
                        $('#viewToolModal').modal('show');
                    }
                }
            });
        });

        // No quantity-related functions needed anymore
    });
    </script>

    <!-- Notification Scripts -->
    <script src="js/notifications.js"></script>
    <script src="js/chemical-notifications.js"></script>
    <script>
        // Initialize mobile menu and notifications when the page loads
        $(document).ready(function() {
            // Mobile menu toggle
            $('#menuToggle').on('click', function() {
                $('.sidebar').toggleClass('active');
            });

            // Fetch notifications immediately
            if (typeof fetchNotifications === 'function') {
                fetchNotifications();

                // Set up periodic notification checks for real-time updates
                setInterval(fetchNotifications, 5000); // Check every 5 seconds
            } else {
                console.error("fetchNotifications function not found");
            }
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>
