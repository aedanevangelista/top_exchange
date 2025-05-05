<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: ../SignIn.php");
    exit;
}
require_once '../db_config.php';

// Get archived chemicals
$stmt = $pdo->query("SELECT * FROM archived_chemical_inventory ORDER BY archived_at DESC");
$archivedChemicals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Chemicals - MacJ Pest Control</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/archive-pages.css">
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
                            <i class="fas fa-archive"></i> Archived Chemicals
                        </h1>
                        <div class="breadcrumb">
                            <a href="dashboard.php">Dashboard</a> /
                            <a href="chemical_inventory.php">Chemical Inventory</a> /
                            <span>Archived Chemicals</span>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-12">
                        <div class="archive-info">
                            <h4><i class="fas fa-info-circle"></i> Archive Information</h4>
                            <p>Archived chemicals are stored here for 30 days before being permanently deleted.</p>
                            <p>You can restore chemicals from the archive during this period.</p>
                            <p class="scheduled-deletion">Items will be automatically deleted after their scheduled deletion date.</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Archived Chemicals</h3>
                                <div class="card-tools">
                                    <a href="chemical_inventory.php" class="btn btn-primary">
                                        <i class="fas fa-arrow-left"></i> Back to Inventory
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (count($archivedChemicals) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Chemical Name</th>
                                                <th>Type</th>
                                                <th>Quantity</th>
                                                <th>Unit</th>
                                                <th>Archived On</th>
                                                <th>Scheduled Deletion</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($archivedChemicals as $chemical): ?>
                                            <tr>
                                                <td><?= $chemical['id'] ?></td>
                                                <td><?= htmlspecialchars($chemical['chemical_name']) ?></td>
                                                <td><?= htmlspecialchars($chemical['type']) ?></td>
                                                <td><?= $chemical['quantity'] ?></td>
                                                <td><?= $chemical['unit'] ?></td>
                                                <td><?= date('M d, Y g:i A', strtotime($chemical['archived_at'])) ?></td>
                                                <td class="scheduled-deletion">
                                                    <?= date('M d, Y', strtotime($chemical['scheduled_deletion_date'])) ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-success restore-btn" data-id="<?= $chemical['archive_id'] ?>">
                                                        <i class="fas fa-trash-restore"></i> Restore
                                                    </button>
                                                    <button class="btn btn-sm btn-info view-btn" data-id="<?= $chemical['archive_id'] ?>">
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
                                    <h3>No Archived Chemicals</h3>
                                    <p>There are no chemicals in the archive at this time.</p>
                                    <a href="chemical_inventory.php" class="btn btn-primary">
                                        <i class="fas fa-arrow-left"></i> Back to Inventory
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

    <!-- View Chemical Modal -->
    <div class="modal fade" id="viewChemicalModal" tabindex="-1" role="dialog" aria-labelledby="viewChemicalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewChemicalModalLabel">Chemical Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Chemical Name:</strong> <span id="viewChemicalName"></span></p>
                            <p><strong>Type:</strong> <span id="viewType"></span></p>
                            <p><strong>Target Pest:</strong> <span id="viewTargetPest"></span></p>
                            <p><strong>Quantity:</strong> <span id="viewQuantity"></span></p>
                            <p><strong>Unit:</strong> <span id="viewUnit"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Manufacturer:</strong> <span id="viewManufacturer"></span></p>
                            <p><strong>Supplier:</strong> <span id="viewSupplier"></span></p>
                            <p><strong>Expiration Date:</strong> <span id="viewExpirationDate"></span></p>
                            <p><strong>Archived On:</strong> <span id="viewArchivedAt"></span></p>
                            <p><strong>Scheduled Deletion:</strong> <span id="viewScheduledDeletion" class="scheduled-deletion"></span></p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6>Description:</h6>
                            <p id="viewDescription"></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <h6>Safety Information:</h6>
                            <p id="viewSafetyInfo"></p>
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
        // View archived chemical
        $(document).on('click', '.view-btn', function() {
            const archiveId = $(this).data('id');

            $.ajax({
                url: 'get_archived_chemical.php',
                method: 'GET',
                data: { archive_id: archiveId },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        const chemical = response.data;

                        $('#viewChemicalName').text(chemical.chemical_name);
                        $('#viewType').text(chemical.type);
                        $('#viewTargetPest').text(chemical.target_pest || 'Not specified');
                        $('#viewQuantity').text(chemical.quantity);
                        $('#viewUnit').text(chemical.unit);
                        $('#viewManufacturer').text(chemical.manufacturer || 'Not specified');
                        $('#viewSupplier').text(chemical.supplier || 'Not specified');
                        $('#viewExpirationDate').text(new Date(chemical.expiration_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }));
                        $('#viewDescription').text(chemical.description || 'No description');
                        $('#viewSafetyInfo').text(chemical.safety_info || 'No safety information');
                        $('#viewArchivedAt').text(new Date(chemical.archived_at).toLocaleString('en-US'));
                        $('#viewScheduledDeletion').text(new Date(chemical.scheduled_deletion_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }));

                        // Set the archive ID for the restore button in the modal
                        $('#modalRestoreBtn').data('id', chemical.archive_id);

                        $('#viewChemicalModal').modal('show');
                    } else {
                        alert('Error: ' + (response.error || 'Failed to load chemical details'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    alert('Error: Could not load chemical details. Please try again.');
                }
            });
        });

        // Restore chemical (from table button)
        $(document).on('click', '.restore-btn', function() {
            const archiveId = $(this).data('id');
            restoreChemical(archiveId);
        });

        // Restore chemical (from modal button)
        $(document).on('click', '#modalRestoreBtn', function() {
            const archiveId = $(this).data('id');
            $('#viewChemicalModal').modal('hide');
            restoreChemical(archiveId);
        });

        // Function to restore a chemical
        function restoreChemical(archiveId) {
            if(confirm('Are you sure you want to restore this chemical?')) {
                $.ajax({
                    url: 'restore_chemical.php',
                    method: 'POST',
                    data: { archive_id: archiveId },
                    dataType: 'json',
                    success: function(response) {
                        if(response.success) {
                            alert('Chemical restored successfully');
                            location.reload();
                        } else {
                            alert('Error: ' + (response.error || 'Failed to restore chemical'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", status, error);
                        alert('Error: Could not restore chemical. Please try again.');
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
