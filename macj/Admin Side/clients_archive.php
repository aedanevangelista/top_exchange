<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: ../SignIn.php");
    exit;
}
require_once '../db_connect.php';

// Get archived clients
$stmt = $conn->prepare("SELECT * FROM archived_clients ORDER BY archived_at DESC");
$stmt->execute();
$result = $stmt->get_result();
$archivedClients = [];
while ($row = $result->fetch_assoc()) {
    $archivedClients[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Clients - MacJ Pest Control</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/archive-pages.css">
    <style>
        .client-name {
            font-weight: bold;
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
                            <i class="fas fa-archive"></i> Archived Clients
                        </h1>
                        <div class="breadcrumb">
                            <a href="dashboard.php">Dashboard</a> /
                            <a href="clients.php">Clients</a> /
                            <span>Archived Clients</span>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-12">
                        <div class="archive-info">
                            <h4><i class="fas fa-info-circle"></i> Archive Information</h4>
                            <p>Archived clients are stored here for 30 days before being permanently deleted.</p>
                            <p>You can restore clients from the archive during this period.</p>
                            <p class="scheduled-deletion">Items will be automatically deleted after their scheduled deletion date.</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h3 class="panel-title">Archived Clients</h3>
                                <div class="panel-tools">
                                    <a href="clients.php" class="btn btn-primary">
                                        <i class="fas fa-arrow-left"></i> Back to Clients
                                    </a>
                                </div>
                            </div>
                            <div class="panel-body">
                                <?php if (count($archivedClients) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Contact Number</th>
                                                <th>Archived On</th>
                                                <th>Scheduled Deletion</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($archivedClients as $client): ?>
                                            <tr>
                                                <td><?= $client['client_id'] ?></td>
                                                <td class="client-name">
                                                    <?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?>
                                                </td>
                                                <td><?= htmlspecialchars($client['email']) ?></td>
                                                <td><?= htmlspecialchars($client['contact_number']) ?></td>
                                                <td><?= date('M d, Y g:i A', strtotime($client['archived_at'])) ?></td>
                                                <td class="scheduled-deletion">
                                                    <?= date('M d, Y', strtotime($client['scheduled_deletion_date'])) ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-success restore-btn" data-id="<?= $client['archive_id'] ?>">
                                                        <i class="fas fa-trash-restore"></i> Restore
                                                    </button>
                                                    <button class="btn btn-sm btn-info view-btn" data-id="<?= $client['archive_id'] ?>">
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
                                    <h3>No Archived Clients</h3>
                                    <p>There are no clients in the archive at this time.</p>
                                    <a href="clients.php" class="btn btn-primary">
                                        <i class="fas fa-arrow-left"></i> Back to Clients
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

    <!-- View Client Modal -->
    <div class="modal fade" id="viewClientModal" tabindex="-1" role="dialog" aria-labelledby="viewClientModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="viewClientModalLabel">Client Details</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Client ID:</strong> <span id="viewClientId"></span></p>
                            <p><strong>Name:</strong> <span id="viewClientName"></span></p>
                            <p><strong>Email:</strong> <span id="viewClientEmail"></span></p>
                            <p><strong>Contact Number:</strong> <span id="viewClientContact"></span></p>
                            <p><strong>Type of Place:</strong> <span id="viewClientPlaceType"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Registered On:</strong> <span id="viewClientRegistered"></span></p>
                            <p><strong>Archived On:</strong> <span id="viewClientArchivedAt"></span></p>
                            <p><strong>Scheduled Deletion:</strong> <span id="viewClientScheduledDeletion" class="scheduled-deletion"></span></p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h5>Location Address:</h5>
                            <p id="viewClientAddress"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="modalRestoreBtn">
                        <i class="fas fa-trash-restore"></i> Restore
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script>
    $(document).ready(function() {
        // View archived client
        $(document).on('click', '.view-btn', function() {
            const archiveId = $(this).data('id');

            $.ajax({
                url: 'get_archived_client.php',
                method: 'GET',
                data: { archive_id: archiveId },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        const client = response.data;

                        $('#viewClientId').text(client.client_id);
                        $('#viewClientName').text(client.first_name + ' ' + client.last_name);
                        $('#viewClientEmail').text(client.email);
                        $('#viewClientContact').text(client.contact_number);
                        $('#viewClientPlaceType').text(client.type_of_place || 'Not specified');
                        $('#viewClientAddress').text(client.location_address || 'Not specified');
                        $('#viewClientRegistered').text(new Date(client.registered_at).toLocaleString('en-US'));
                        $('#viewClientArchivedAt').text(new Date(client.archived_at).toLocaleString('en-US'));
                        $('#viewClientScheduledDeletion').text(new Date(client.scheduled_deletion_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }));

                        // Set the archive ID for the restore button in the modal
                        $('#modalRestoreBtn').data('id', client.archive_id);

                        $('#viewClientModal').modal('show');
                    } else {
                        alert('Error: ' + (response.error || 'Failed to load client details'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    alert('Error: Could not load client details. Please try again.');
                }
            });
        });

        // Restore client (from table button)
        $(document).on('click', '.restore-btn', function() {
            const archiveId = $(this).data('id');
            restoreClient(archiveId);
        });

        // Restore client (from modal button)
        $(document).on('click', '#modalRestoreBtn', function() {
            const archiveId = $(this).data('id');
            $('#viewClientModal').modal('hide');
            restoreClient(archiveId);
        });

        // Function to restore a client
        function restoreClient(archiveId) {
            if(confirm('Are you sure you want to restore this client?')) {
                $.ajax({
                    url: 'restore_client.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ archive_id: archiveId }),
                    dataType: 'json',
                    success: function(response) {
                        if(response.success) {
                            alert('Client restored successfully');
                            location.reload();
                        } else {
                            alert('Error: ' + (response.error || 'Failed to restore client'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", status, error);
                        alert('Error: Could not restore client. Please try again.');
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
