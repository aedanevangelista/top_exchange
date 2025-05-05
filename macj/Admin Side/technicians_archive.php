<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: ../SignIn.php");
    exit;
}
require_once '../db_connect.php';

// Get archived technicians
$stmt = $conn->prepare("SELECT * FROM archived_technicians ORDER BY archived_at DESC");
$stmt->execute();
$result = $stmt->get_result();
$archivedTechnicians = [];
while ($row = $result->fetch_assoc()) {
    $archivedTechnicians[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Technicians - MacJ Pest Control</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/archive-pages.css">
    <style>
        .tech-name {
            font-weight: bold;
        }

        .tech-image {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }

        .modal-tech-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
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
                            <i class="fas fa-archive"></i> Archived Technicians
                        </h1>
                        <div class="breadcrumb">
                            <a href="dashboard.php">Dashboard</a> /
                            <a href="technicians.php">Technicians</a> /
                            <span>Archived Technicians</span>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-12">
                        <div class="archive-info">
                            <h4><i class="fas fa-info-circle"></i> Archive Information</h4>
                            <p>Archived technicians are stored here for 30 days before being permanently deleted.</p>
                            <p>You can restore technicians from the archive during this period.</p>
                            <p class="scheduled-deletion">Items will be automatically deleted after their scheduled deletion date.</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Archived Technicians</h3>
                                <div class="card-tools">
                                    <a href="technicians.php" class="btn btn-primary">
                                        <i class="fas fa-arrow-left"></i> Back to Technicians
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (count($archivedTechnicians) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Image</th>
                                                <th>Name</th>
                                                <th>Username</th>
                                                <th>Contact Number</th>
                                                <th>Archived On</th>
                                                <th>Scheduled Deletion</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($archivedTechnicians as $tech): ?>
                                            <tr>
                                                <td><?= $tech['technician_id'] ?></td>
                                                <td>
                                                    <img src="<?= htmlspecialchars($tech['technician_picture']) ?>"
                                                         alt="<?= htmlspecialchars($tech['tech_fname'] . ' ' . $tech['tech_lname']) ?>"
                                                         class="tech-image">
                                                </td>
                                                <td class="tech-name">
                                                    <?= htmlspecialchars($tech['tech_fname'] . ' ' . $tech['tech_lname']) ?>
                                                </td>
                                                <td><?= htmlspecialchars($tech['username']) ?></td>
                                                <td><?= htmlspecialchars($tech['tech_contact_number']) ?></td>
                                                <td><?= date('M d, Y g:i A', strtotime($tech['archived_at'])) ?></td>
                                                <td class="scheduled-deletion">
                                                    <?= date('M d, Y', strtotime($tech['scheduled_deletion_date'])) ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-success restore-btn" data-id="<?= $tech['archive_id'] ?>">
                                                        <i class="fas fa-trash-restore"></i> Restore
                                                    </button>
                                                    <button class="btn btn-sm btn-info view-btn" data-id="<?= $tech['archive_id'] ?>">
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
                                    <h3>No Archived Technicians</h3>
                                    <p>There are no technicians in the archive at this time.</p>
                                    <a href="technicians.php" class="btn btn-primary">
                                        <i class="fas fa-arrow-left"></i> Back to Technicians
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

    <!-- View Technician Modal -->
    <div class="modal fade" id="viewTechnicianModal" tabindex="-1" role="dialog" aria-labelledby="viewTechnicianModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewTechnicianModalLabel">Technician Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center">
                    <img id="viewTechImage" src="" alt="Technician" class="modal-tech-image">
                    <h4 id="viewTechName" class="mb-4"></h4>

                    <div class="row">
                        <div class="col-md-6 text-left">
                            <p><strong>Technician ID:</strong> <span id="viewTechId"></span></p>
                            <p><strong>Username:</strong> <span id="viewTechUsername"></span></p>
                            <p><strong>Contact Number:</strong> <span id="viewTechContact"></span></p>
                        </div>
                        <div class="col-md-6 text-left">
                            <p><strong>Archived On:</strong> <span id="viewTechArchivedAt"></span></p>
                            <p><strong>Scheduled Deletion:</strong> <span id="viewTechScheduledDeletion" class="scheduled-deletion"></span></p>
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
        // View archived technician
        $(document).on('click', '.view-btn', function() {
            const archiveId = $(this).data('id');

            $.ajax({
                url: 'get_archived_technician.php',
                method: 'GET',
                data: { archive_id: archiveId },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        const tech = response.data;

                        $('#viewTechId').text(tech.technician_id);
                        $('#viewTechName').text(tech.tech_fname + ' ' + tech.tech_lname);
                        $('#viewTechUsername').text(tech.username);
                        $('#viewTechContact').text(tech.tech_contact_number);
                        $('#viewTechImage').attr('src', tech.technician_picture);
                        $('#viewTechArchivedAt').text(new Date(tech.archived_at).toLocaleString('en-US'));
                        $('#viewTechScheduledDeletion').text(new Date(tech.scheduled_deletion_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }));

                        // Set the archive ID for the restore button in the modal
                        $('#modalRestoreBtn').data('id', tech.archive_id);

                        $('#viewTechnicianModal').modal('show');
                    } else {
                        alert('Error: ' + (response.error || 'Failed to load technician details'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    alert('Error: Could not load technician details. Please try again.');
                }
            });
        });

        // Restore technician (from table button)
        $(document).on('click', '.restore-btn', function() {
            const archiveId = $(this).data('id');
            restoreTechnician(archiveId);
        });

        // Restore technician (from modal button)
        $(document).on('click', '#modalRestoreBtn', function() {
            const archiveId = $(this).data('id');
            $('#viewTechnicianModal').modal('hide');
            restoreTechnician(archiveId);
        });

        // Function to restore a technician
        function restoreTechnician(archiveId) {
            if(confirm('Are you sure you want to restore this technician?')) {
                $.ajax({
                    url: 'restore_technician.php',
                    method: 'GET',
                    data: { archive_id: archiveId },
                    dataType: 'json',
                    success: function(response) {
                        if(response.success) {
                            alert('Technician restored successfully');
                            location.reload();
                        } else {
                            alert('Error: ' + (response.error || 'Failed to restore technician'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", status, error);
                        alert('Error: Could not restore technician. Please try again.');
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
