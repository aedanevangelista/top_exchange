<?php
// Start the session and prevent caching
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: /LandingPage/login.php");
    exit();
}

// Database connection
$servername = "127.0.0.1:3306";
$username = "u701062148_top_exchange";
$password = "Aedanpogi123";
$dbname = "u701062148_top_exchange";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get client information
$username = $_SESSION['username'];

// Initialize variables
$payments = [];
$totalPaid = 0;
$paymentStats = [
    'all' => 0,
    'internal' => 0,
    'external' => 0
];

// Get filter parameters
$typeFilter = $_GET['type'] ?? 'all';
$yearFilter = $_GET['year'] ?? date('Y');
$monthFilter = $_GET['month'] ?? 'all';

// Base query
$query = "SELECT * FROM payment_history WHERE username = ?";

// Add type filter
if ($typeFilter !== 'all') {
    $query .= " AND payment_type = ?";
}

// Add year filter
if ($yearFilter !== 'all') {
    $query .= " AND year = ?";
}

// Add month filter
if ($monthFilter !== 'all') {
    $query .= " AND month = ?";
}

$query .= " ORDER BY year DESC, month DESC, created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);

// Bind parameters based on filters
$paramTypes = "s";
$params = [$username];

if ($typeFilter !== 'all') {
    $paramTypes .= "s";
    $params[] = ucfirst($typeFilter);
}

if ($yearFilter !== 'all') {
    $paramTypes .= "i";
    $params[] = $yearFilter;
}

if ($monthFilter !== 'all') {
    $paramTypes .= "i";
    $params[] = $monthFilter;
}

$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Process payments
while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
    $totalPaid += $row['amount'];

    // Update stats
    $paymentStats['all']++;
    $paymentStats[strtolower($row['payment_type'] ?? 'internal')]++;
}

// Get available years for filter
$yearsQuery = "SELECT DISTINCT year FROM payment_history WHERE username = ? ORDER BY year DESC";
$yearsStmt = $conn->prepare($yearsQuery);
$yearsStmt->bind_param("s", $username);
$yearsStmt->execute();
$yearsResult = $yearsStmt->get_result();
$availableYears = [];
while ($yearRow = $yearsResult->fetch_assoc()) {
    $availableYears[] = $yearRow['year'];
}

// Close the database connection
$conn->close();

// Month names for display
$monthNames = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - Top Exchange Food Corp</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #9a7432;
            --primary-hover: #b08a3e;
            --secondary-color: #333;
            --light-color: #f8f9fa;
            --dark-color: #222;
            --accent-color: #dc3545;
            --box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
            --border-radius: 8px;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Montserrat', sans-serif;
            color: #495057;
            padding-top: 0;
        }

        .page-header {
            background: linear-gradient(135deg, #9a7432 0%, #c9a158 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: var(--box-shadow);
        }

        .page-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
            transition: var(--transition);
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }

        .card-header {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            padding: 1.25rem 1.75rem;
            border-bottom: none;
        }

        .stat-card {
            text-align: center;
            padding: 1.75rem 1rem;
            border-radius: var(--border-radius);
            background-color: white;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            margin-bottom: 1.5rem;
            border: none;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            background-color: #f8f9fa;
        }

        .stat-icon {
            font-size: 2.25rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }

        .filter-form {
            background-color: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 1.5rem;
        }

        .table th {
            font-weight: 600;
            color: var(--secondary-color);
        }

        .badge {
            padding: 0.4em 0.7em;
            font-size: 0.75em;
            font-weight: 600;
            border-radius: 0.3rem;
        }

        .badge-internal {
            background-color: #d1e7ff;
            color: #084298;
        }

        .badge-external {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
        }

        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .empty-state h5 {
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #777;
            margin-bottom: 1.5rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .modal-header {
            background-color: var(--primary-color);
            color: white;
        }

        .modal-title {
            font-weight: 600;
        }

        .proof-img {
            max-width: 100%;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Include your header -->
    <?php include 'header.php'; ?>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1><i class="fas fa-credit-card me-2"></i> Payment History</h1>
                <p class="mb-0">View and manage your payment records</p>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="text-white-50 mb-1">Total Amount Paid</div>
                <h2 class="mb-0">₱<?php echo number_format($totalPaid, 2); ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">

    <!-- Filter Form -->
    <div class="filter-form">
        <form method="GET" action="payments.php" class="row g-3">
            <div class="col-md-4">
                <label for="year" class="form-label">Year</label>
                <select class="form-select" id="year" name="year">
                    <option value="all" <?php echo $yearFilter === 'all' ? 'selected' : ''; ?>>All Years</option>
                    <?php foreach ($availableYears as $year): ?>
                        <option value="<?php echo $year; ?>" <?php echo $yearFilter == $year ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="month" class="form-label">Month</label>
                <select class="form-select" id="month" name="month">
                    <option value="all" <?php echo $monthFilter === 'all' ? 'selected' : ''; ?>>All Months</option>
                    <?php foreach ($monthNames as $num => $name): ?>
                        <option value="<?php echo $num; ?>" <?php echo $monthFilter == $num ? 'selected' : ''; ?>>
                            <?php echo $name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="type" class="form-label">Payment Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="all" <?php echo $typeFilter === 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="internal" <?php echo $typeFilter === 'internal' ? 'selected' : ''; ?>>Internal</option>
                    <option value="external" <?php echo $typeFilter === 'external' ? 'selected' : ''; ?>>External</option>
                </select>
            </div>
            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-1"></i> Apply Filters
                </button>
                <a href="payments.php" class="btn btn-outline-secondary ms-2">
                    <i class="fas fa-undo me-1"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value">₱<?php echo number_format($totalPaid, 2); ?></div>
                <div class="stat-label">Total Payments</div>
            </div>
        </div>
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-value"><?php echo $paymentStats['internal']; ?></div>
                <div class="stat-label">Internal Payments</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-globe"></i>
                </div>
                <div class="stat-value"><?php echo $paymentStats['external']; ?></div>
                <div class="stat-label">External Payments</div>
            </div>
        </div>
    </div>

    <!-- Payments List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-history me-2"></i> Payment History
                <?php if ($typeFilter !== 'all' || $yearFilter !== 'all' || $monthFilter !== 'all'): ?>
                    <small class="ms-2 text-white">(Filtered results)</small>
                <?php endif; ?>
            </div>
            <div class="text-white">
                <?php echo count($payments); ?> payment(s) found
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($payments)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Period</th>
                                <th>Amount</th>
                                <th>Type</th>
                                <th>Notes</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($payment['created_at'])); ?></td>
                                    <td><?php echo $monthNames[$payment['month']] . ' ' . $payment['year']; ?></td>
                                    <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php echo strtolower($payment['payment_type']) === 'internal' ? 'badge-internal' : 'badge-external'; ?>">
                                            <?php echo htmlspecialchars($payment['payment_type'] ?? 'Unknown'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo !empty($payment['notes']) ? htmlspecialchars($payment['notes']) : '<span class="text-muted">No notes</span>'; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($payment['proof_image'])): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary view-proof"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#proofModal"
                                                    data-proof-src="uploads/payment_proofs/<?php echo htmlspecialchars($payment['proof_image']); ?>"
                                                    data-payment-date="<?php echo date('M d, Y', strtotime($payment['created_at'])); ?>"
                                                    data-payment-amount="₱<?php echo number_format($payment['amount'], 2); ?>">
                                                <i class="fas fa-image me-1"></i> View Proof
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted"><i class="fas fa-image me-1"></i> No proof</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-credit-card"></i>
                    <h5>No payment history found</h5>
                    <p class="text-muted">No payments match your current filters.</p>
                    <a href="payments.php" class="btn btn-primary">
                        <i class="fas fa-undo me-1"></i> Reset Filters
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Payment Proof Modal -->
<div class="modal fade" id="proofModal" tabindex="-1" aria-labelledby="proofModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="proofModalLabel">Payment Proof</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div class="mb-3">
                    <strong>Date:</strong> <span id="modalPaymentDate"></span> |
                    <strong>Amount:</strong> <span id="modalPaymentAmount"></span>
                </div>
                <img id="modalProofImage" src="" alt="Payment Proof" class="proof-img img-fluid">
            </div>
            <div class="modal-footer">
                <a id="downloadProofLink" href="#" class="btn btn-success" download>
                    <i class="fas fa-download me-1"></i> Download
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script>
    // Initialize proof modal
    document.addEventListener('DOMContentLoaded', function() {
        var proofModal = document.getElementById('proofModal');
        if (proofModal) {
            proofModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var proofSrc = button.getAttribute('data-proof-src');
                var paymentDate = button.getAttribute('data-payment-date');
                var paymentAmount = button.getAttribute('data-payment-amount');

                var modalImage = proofModal.querySelector('#modalProofImage');
                var downloadLink = proofModal.querySelector('#downloadProofLink');
                var dateSpan = proofModal.querySelector('#modalPaymentDate');
                var amountSpan = proofModal.querySelector('#modalPaymentAmount');

                modalImage.src = proofSrc;
                downloadLink.href = proofSrc;
                downloadLink.setAttribute('download', proofSrc.split('/').pop());
                dateSpan.textContent = paymentDate;
                amountSpan.textContent = paymentAmount;
            });
        }
    });
</script>

<?php include 'footer.php'; ?>
</body>
</html>
