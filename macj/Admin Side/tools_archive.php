<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: ../SignIn.php");
    exit;
}
require_once '../db_connect.php';

// Get archived tools
$stmt = $conn->prepare("SELECT * FROM archived_tools_equipment ORDER BY archived_at DESC");
$stmt->execute();
$result = $stmt->get_result();
$archivedTools = [];
while ($row = $result->fetch_assoc()) {
    $archivedTools[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Tools & Equipment - MacJ Pest Control</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/archive-pages.css">
    <style>
        .tool-name {
            font-weight: bold;
        }

        .category-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            background-color: #e9ecef;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="content">
            <!-- Top Navigation -->
            <nav class="top-nav">
                <div class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </div>

            </nav>

            <div class="container-fluid">
                <div class="row mb-4">
                    <div class="col-12">
                        <h1 class="page-title">
                            <i class="fas fa-archive"></i> Archived Tools & Equipment
                        </h1>
                        <div class="breadcrumb">
                            <a href="dashboard.php">Dashboard</a> /
                            <a href="tools_equipment.php">Tools & Equipment</a> /
                            <span>Archived Tools & Equipment</span>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-12">
                        <div class="archive-info">
                            <h4><i class="fas fa-info-circle"></i> Archive Information</h4>
                            <p>Archived tools and equipment are stored here for 30 days before being permanently deleted.</p>
                            <p>You can restore items from the archive during this period.</p>
                            <p class="scheduled-deletion">Items will be automatically deleted after their scheduled deletion date.</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Archived Tools & Equipment</h3>
                                <div class="card-tools">
                                    <a href="tools_equipment.php" class="btn btn-primary">
                                        <i class="fas fa-arrow-left"></i> Back to Tools & Equipment
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (count($archivedTools) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Category</th>
                                                <th>Quantity</th>
                                                <th>Archived On</th>
                                                <th>Scheduled Deletion</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($archivedTools as $tool): ?>
                                            <tr>
                                                <td><?= $tool['id'] ?></td>
                                                <td class="tool-name"><?= htmlspecialchars($tool['name']) ?></td>
                                                <td>
                                                    <span class="category-badge">
                                                        <?= htmlspecialchars($tool['category']) ?>
                                                    </span>
                                                </td>
                                                <td><?= $tool['quantity'] ?></td>
                                                <td><?= date('M d, Y g:i A', strtotime($tool['archived_at'])) ?></td>
                                                <td class="scheduled-deletion">
                                                    <?= date('M d, Y', strtotime($tool['scheduled_deletion_date'])) ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-success restore-btn" data-id="<?= $tool['archive_id'] ?>">
                                                        <i class="fas fa-trash-restore"></i> Restore
                                                    </button>
                                                    <button class="btn btn-sm btn-info view-btn" data-id="<?= $tool['archive_id'] ?>">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="empty-archive">
                                    <i class="fas fa-archive"></i>
                                    <h3>No Archived Tools & Equipment</h3>
                                    <p>There are no tools or equipment in the archive at this time.</p>
                                    <a href="tools_equipment.php" class="btn btn-primary">
                                        <i class="fas fa-arrow-left"></i> Back to Tools & Equipment
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- View Tool Modal -->
    <div class="modal fade" id="viewToolModal" tabindex="-1" role="dialog" aria-labelledby="viewToolModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewToolModalLabel">Tool/Equipment Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>ID:</strong> <span id="viewToolId"></span></p>
                            <p><strong>Name:</strong> <span id="viewToolName"></span></p>
                            <p><strong>Category:</strong> <span id="viewToolCategory"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Quantity:</strong> <span id="viewToolQuantity"></span></p>
                            <p><strong>Archived On:</strong> <span id="viewToolArchivedAt"></span></p>
                            <p><strong>Scheduled Deletion:</strong> <span id="viewToolScheduledDeletion" class="scheduled-deletion"></span></p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6>Description:</h6>
                            <p id="viewToolDescription"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="modalRestoreBtn">
                        <i class="fas fa-trash-restore"></i> Restore
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // View archived tool
        $(document).on('click', '.view-btn', function() {
            const archiveId = $(this).data('id');

            $.ajax({
                url: 'get_archived_tool.php',
                method: 'GET',
                data: { archive_id: archiveId },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        const tool = response.data;

                        $('#viewToolId').text(tool.id);
                        $('#viewToolName').text(tool.name);
                        $('#viewToolCategory').text(tool.category);
                        $('#viewToolQuantity').text(tool.quantity);
                        $('#viewToolDescription').text(tool.description || 'No description');
                        $('#viewToolArchivedAt').text(new Date(tool.archived_at).toLocaleString('en-US'));
                        $('#viewToolScheduledDeletion').text(new Date(tool.scheduled_deletion_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }));

                        // Set the archive ID for the restore button in the modal
                        $('#modalRestoreBtn').data('id', tool.archive_id);

                        $('#viewToolModal').modal('show');
                    } else {
                        alert('Error: ' + (response.error || 'Failed to load tool details'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    alert('Error: Could not load tool details. Please try again.');
                }
            });
        });

        // Restore tool (from table button)
        $(document).on('click', '.restore-btn', function() {
            const archiveId = $(this).data('id');
            restoreTool(archiveId);
        });

        // Restore tool (from modal button)
        $(document).on('click', '#modalRestoreBtn', function() {
            const archiveId = $(this).data('id');
            $('#viewToolModal').modal('hide');
            restoreTool(archiveId);
        });

        // Function to restore a tool
        function restoreTool(archiveId) {
            if(confirm('Are you sure you want to restore this tool/equipment?')) {
                $.ajax({
                    url: 'restore_tool.php',
                    method: 'POST',
                    data: { archive_id: archiveId },
                    dataType: 'json',
                    success: function(response) {
                        if(response.success) {
                            alert('Tool/equipment restored successfully');
                            location.reload();
                        } else {
                            alert('Error: ' + (response.error || 'Failed to restore tool/equipment'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", status, error);
                        alert('Error: Could not restore tool/equipment. Please try again.');
                    }
                });
            }
        }
    });
    </script>

    <script>
        // Initialize mobile menu when the page loads
        $(document).ready(function() {
            // Mobile menu toggle
            $('#menuToggle').on('click', function() {
                $('.sidebar').toggleClass('active');
            });
        });
    </script>
</body>
</html>
