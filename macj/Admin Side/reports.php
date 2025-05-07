<?php
session_start();
if ($_SESSION['role'] !== 'office_staff') {
    header("Location: SignIn.php");
    exit;
}
require_once '../db_config.php';
require_once '../notification_functions.php';

// Get current year and month
$current_year = date('Y');
$current_month = date('m');

// Query for assessment reports in a year
$assessment_query = "SELECT
                        MONTH(created_at) as month,
                        COUNT(*) as count
                    FROM assessment_report
                    WHERE YEAR(created_at) = ?
                    GROUP BY MONTH(created_at)
                    ORDER BY MONTH(created_at)";
$stmt = $pdo->prepare($assessment_query);
$stmt->execute([$current_year]);
$assessment_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format assessment data for chart
$assessment_months = [];
$assessment_counts = [];
foreach ($assessment_data as $data) {
    $month_name = date('F', mktime(0, 0, 0, $data['month'], 1));
    $assessment_months[] = $month_name;
    $assessment_counts[] = $data['count'];
}

// Fill in missing months with zero values
$all_months = [];
$all_assessment_counts = array_fill(0, 12, 0);
for ($i = 1; $i <= 12; $i++) {
    $month_name = date('F', mktime(0, 0, 0, $i, 1));
    $all_months[] = $month_name;

    // Find if we have data for this month
    foreach ($assessment_data as $data) {
        if ($data['month'] == $i) {
            $all_assessment_counts[$i-1] = $data['count'];
            break;
        }
    }
}

// Query for job orders in a month
$job_order_query = "SELECT
                        DAY(preferred_date) as day,
                        COUNT(*) as count
                    FROM job_order
                    WHERE MONTH(preferred_date) = ? AND YEAR(preferred_date) = ?
                    GROUP BY DAY(preferred_date)
                    ORDER BY DAY(preferred_date)";
$stmt = $pdo->prepare($job_order_query);
$stmt->execute([$current_month, $current_year]);
$job_order_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format job order data for chart
$job_order_days = [];
$job_order_counts = [];
foreach ($job_order_data as $data) {
    $job_order_days[] = $data['day'];
    $job_order_counts[] = $data['count'];
}

// Query for user distribution
$user_query = "SELECT
                (SELECT COUNT(*) FROM clients) as client_count,
                (SELECT COUNT(*) FROM office_staff) as admin_count,
                (SELECT COUNT(*) FROM technicians) as technician_count";
$stmt = $pdo->prepare($user_query);
$stmt->execute();
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Query for contracts accepted by month
$contract_query = "SELECT
                    MONTH(client_approval_date) as month,
                    COUNT(*) as count
                FROM job_order
                WHERE YEAR(client_approval_date) = ?
                AND client_approval_status = 'approved'
                GROUP BY MONTH(client_approval_date)
                ORDER BY MONTH(client_approval_date)";
$stmt = $pdo->prepare($contract_query);
$stmt->execute([$current_year]);
$contract_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format contract data for chart
$contract_months = [];
$contract_counts = [];
foreach ($contract_data as $data) {
    $month_name = date('F', mktime(0, 0, 0, $data['month'], 1));
    $contract_months[] = $month_name;
    $contract_counts[] = $data['count'];
}

// Fill in missing months with zero values
$all_contract_counts = array_fill(0, 12, 0);
for ($i = 1; $i <= 12; $i++) {
    // Find if we have data for this month
    foreach ($contract_data as $data) {
        if ($data['month'] == $i) {
            $all_contract_counts[$i-1] = $data['count'];
            break;
        }
    }
}

// Query for chemical inventory quantities
$chemical_query = "SELECT
                    chemical_name,
                    quantity,
                    unit
                FROM chemical_inventory
                WHERE quantity > 0
                ORDER BY quantity DESC
                LIMIT 10";

// Initialize arrays for the chart
$chemical_names = [];
$chemical_quantities = [];
$chemical_units = [];
$chemical_colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#C9CBCF', '#7BC8A4', '#E7E9ED', '#8549BA'];

try {
    $stmt = $pdo->prepare($chemical_query);
    $stmt->execute();
    $chemical_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug information
    $debug_info = [
        'chemical_data' => $chemical_data
    ];

    // Process the data for the chart
    foreach ($chemical_data as $index => $row) {
        $chemical_names[] = $row['chemical_name'];
        $chemical_quantities[] = floatval($row['quantity']);
        $chemical_units[] = $row['unit'];
    }

    // Add debug info
    $debug_info['chemical_names'] = $chemical_names;
    $debug_info['chemical_quantities'] = $chemical_quantities;
    $debug_info['chemical_units'] = $chemical_units;

} catch (Exception $e) {
    // Log the error
    error_log("Error fetching chemical inventory data: " . $e->getMessage());

    // Add error to debug info
    $debug_info['error'] = $e->getMessage();
}

// Set up revenue data
// Monthly estimated revenue: 1,200,000 pesos (fixed amount for each month)
$monthly_revenue_target = 1200000; // 1,200,000 pesos per month as requested
$monthly_revenue_target_formatted = number_format($monthly_revenue_target, 0, '.', ',');

// Query for actual monthly revenue data from job_order table
$monthly_revenue_query = "SELECT
                        MONTH(preferred_date) as month,
                        SUM(cost) as total_revenue
                      FROM job_order
                      WHERE YEAR(preferred_date) = ?
                      AND cost IS NOT NULL
                      GROUP BY MONTH(preferred_date)
                      ORDER BY month";
$stmt = $pdo->prepare($monthly_revenue_query);
$stmt->execute([$current_year]);
$monthly_revenue_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize monthly actual revenue array
$monthly_actual_revenue = array_fill(0, 12, 0);

// Fill in actual revenue data
foreach ($monthly_revenue_data as $data) {
    $month_index = $data['month'] - 1;
    $monthly_actual_revenue[$month_index] = (float)$data['total_revenue'];
}

// Calculate total actual revenue for the current year
$total_actual_revenue_current_year = array_sum($monthly_actual_revenue);
$total_actual_revenue_formatted = number_format($total_actual_revenue_current_year, 0, '.', ',');



// If no actual revenue data from the database, use the default value
if (array_sum($monthly_actual_revenue) == 0) {
    // Check if there's any data in the job_order table with cost
    $check_data_query = "SELECT COUNT(*) as count FROM job_order WHERE cost IS NOT NULL";
    $stmt = $pdo->prepare($check_data_query);
    $stmt->execute();
    $has_data = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

    if (!$has_data) {
        // If no data exists, use the default value of 1000 pesos for each month
        for ($i = 0; $i < 12; $i++) {
            $monthly_actual_revenue[$i] = 1000; // 1,000 pesos as default
        }

        // Update total actual revenue
        $total_actual_revenue_current_year = array_sum($monthly_actual_revenue);
        $total_actual_revenue_formatted = number_format($total_actual_revenue_current_year, 0, '.', ',');
    }
}

// Set fixed monthly estimated revenue of 1,200,000 pesos for each month
$current_month = (int)date('n'); // Current month as a number (1-12)
$monthly_revenue_target_data = [];
$filtered_months = []; // Will store only past and current months

// Only include data for past and current months
for ($i = 0; $i < $current_month; $i++) {
    $monthly_revenue_target_data[$i] = $monthly_revenue_target; // Fixed 1,200,000 pesos for each month
    $filtered_months[] = $all_months[$i]; // Store the month name
}

// Filter the actual revenue data to only include past and current months
$filtered_monthly_actual_revenue = array_slice($monthly_actual_revenue, 0, $current_month);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - MacJ Pest Control</title>
    <link rel="stylesheet" href="css/reports-page.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Chemical notification styles */
        .notification-icon-wrapper {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: rgba(59, 130, 246, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            flex-shrink: 0;
        }

        .notification-icon-wrapper.chemical-expiring {
            background-color: #ffe6e6;
        }

        .notification-icon-wrapper.chemical-expiring i {
            color: #cc0000;
        }

        .notification-item {
            display: flex;
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }

        .notification-item:hover {
            background-color: #f9f9f9;
        }

        .notification-item.unread {
            background-color: #f0f7ff;
        }

        .notification-item.unread:hover {
            background-color: #e6f0ff;
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

        /* Chart styles */
        .chart-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 20px;
            height: 300px;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .chart-header h2 {
            margin: 0;
            font-size: 18px;
            color: var(--primary-color);
        }

        .chart-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .chart-col {
            flex: 1;
        }

        .progress-container {
            margin-bottom: 15px;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .progress-bar {
            height: 20px;
            background-color: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 10px;
        }

        /* Mobile menu toggle button */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1000;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            width: 40px;
            height: 40px;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        /* Media query for mobile responsiveness */
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .chart-row {
                flex-direction: column;
            }
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
                try {
                    $checkColumnStmt = $pdo->prepare("SHOW COLUMNS FROM office_staff LIKE 'profile_picture'");
                    $checkColumnStmt->execute();
                    if ($checkColumnStmt->rowCount() > 0) {
                        $stmt = $pdo->prepare("SELECT profile_picture FROM office_staff WHERE staff_id = ?");
                        $stmt->execute([$staff_id]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($row) {
                            $profile_picture = $row['profile_picture'];
                        }
                    }
                } catch (PDOException $e) {
                    // Log error but continue
                    error_log("Error fetching profile picture: " . $e->getMessage());
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
                    <li><a href="tools_equipment.php"><i class="fas fa-tools"></i> Tools and Equipment</a></li>
                    <li><a href="technicians.php"><i class="fas fa-user-md"></i> Technicians</a></li>
                    <li><a href="clients.php"><i class="fas fa-users"></i> Clients</a></li>
                    <li class="active"><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="../SignOut.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Mobile menu toggle -->
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Main Content -->
        <main class="main-content">
            <div class="reports-content">
                <div class="reports-header">
                    <h1><i class="fas fa-chart-bar"></i> Reports</h1>
                </div>

                <!-- First Row: Assessment Reports and Job Orders -->
                <div class="chart-row">
                    <!-- Assessment Reports in a Year -->
                    <div class="chart-col">
                        <div class="chart-container">
                            <div class="chart-header">
                                <h2>Assessment Reports in <?php echo $current_year; ?></h2>
                            </div>
                            <div class="chart-content">
                                <canvas id="assessmentChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Job Orders in a Month -->
                    <div class="chart-col">
                        <div class="chart-container">
                            <div class="chart-header">
                                <h2>Job Orders in <?php echo date('F Y'); ?></h2>
                            </div>
                            <div class="chart-content">
                                <canvas id="jobOrderChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Second Row: User Distribution and Contracts Accepted -->
                <div class="chart-row">
                    <!-- User Distribution -->
                    <div class="chart-col">
                        <div class="chart-container">
                            <div class="chart-header">
                                <h2>User Distribution</h2>
                            </div>
                            <div class="chart-content">
                                <canvas id="userChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Contracts Accepted by Month -->
                    <div class="chart-col">
                        <div class="chart-container">
                            <div class="chart-header">
                                <h2>Contracts Accepted in <?php echo $current_year; ?></h2>
                            </div>
                            <div class="chart-content">
                                <canvas id="contractChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Third Row: Chemical Inventory Quantities -->
                <div class="chart-row">
                    <!-- Chemical Inventory Quantities -->
                    <div class="chart-col">
                        <div class="chart-container">
                            <div class="chart-header">
                                <h2>Chemical Inventory Quantities</h2>
                            </div>
                            <div class="chart-content">
                                <?php if (empty($chemical_names)): ?>
                                <div class="no-data-message">
                                    <i class="fas fa-info-circle"></i>
                                    <p>No chemical inventory data available</p>
                                </div>
                                <?php else: ?>
                                <canvas id="chemicalInventoryChart"></canvas>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Revenue Report -->
                    <div class="chart-col">
                        <div class="chart-container">
                            <div class="chart-header">
                                <h2>Estimated Revenue vs. Actual Revenue (Jan-<?php echo date('M'); ?>)</h2>
                                <small style="color: #666; font-size: 0.8rem; font-weight: normal; display: block;">
                                    Estimated monthly revenue: ₱<?php echo $monthly_revenue_target_formatted; ?> |
                                    <?php
                                    // Calculate average monthly revenue from actual data
                                    $avg_monthly_revenue = ($current_month > 0) ? ($total_actual_revenue_current_year / $current_month) : 0;
                                    ?>
                                    Actual monthly revenue: ₱<?php echo number_format($avg_monthly_revenue, 0, '.', ','); ?>
                                </small>
                            </div>
                            <div class="chart-content">
                                <canvas id="monthlyRevenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>



                <?php if (isset($_GET['debug']) && $_GET['debug'] === 'true'): ?>
                <!-- Debug Information (only visible when ?debug=true is in the URL) -->
                <div class="debug-section">
                    <h3>Debug Information</h3>
                    <div class="debug-content">
                        <h4>Chemical Recommendations Data</h4>
                        <pre><?php echo json_encode($debug_info, JSON_PRETTY_PRINT); ?></pre>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Assessment Reports Chart
            const assessmentCtx = document.getElementById('assessmentChart').getContext('2d');
            new Chart(assessmentCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($all_months); ?>,
                    datasets: [{
                        label: 'Assessment Reports',
                        data: <?php echo json_encode($all_assessment_counts); ?>,
                        backgroundColor: '#3B82F6',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });

            // Job Orders Chart
            const jobOrderCtx = document.getElementById('jobOrderChart').getContext('2d');
            new Chart(jobOrderCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($job_order_days); ?>,
                    datasets: [{
                        label: 'Job Orders',
                        data: <?php echo json_encode($job_order_counts); ?>,
                        backgroundColor: '#10B981',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });

            // User Distribution Chart
            const userCtx = document.getElementById('userChart').getContext('2d');
            new Chart(userCtx, {
                type: 'pie',
                data: {
                    labels: ['Clients', 'Admin', 'Technicians'],
                    datasets: [{
                        data: [
                            <?php echo $user_data['client_count']; ?>,
                            <?php echo $user_data['admin_count']; ?>,
                            <?php echo $user_data['technician_count']; ?>
                        ],
                        backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56'],
                        hoverOffset: 4,
                        borderWidth: 1,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 15,
                                padding: 15,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    layout: {
                        padding: 10
                    }
                }
            });

            // Contracts Accepted Chart
            const contractCtx = document.getElementById('contractChart').getContext('2d');
            new Chart(contractCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($all_months); ?>,
                    datasets: [{
                        label: 'Contracts Accepted',
                        data: <?php echo json_encode($all_contract_counts); ?>,
                        backgroundColor: '#F59E0B',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });

            // Monthly Revenue Chart
            const monthlyRevenueCtx = document.getElementById('monthlyRevenueChart').getContext('2d');
            new Chart(monthlyRevenueCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($filtered_months); ?>, // Only past and current months
                    datasets: [
                        {
                            label: 'Estimated Revenue',
                            data: <?php echo json_encode($monthly_revenue_target_data); ?>, // Fixed 1,200,000 pesos for each month
                            backgroundColor: '#10B981', // Green color
                            borderRadius: 4
                        },
                        {
                            label: 'Actual Revenue',
                            data: <?php echo json_encode($filtered_monthly_actual_revenue); ?>, // Actual revenue data from database
                            backgroundColor: '#EF4444', // Red color
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    // Format large numbers with M for millions
                                    if (value >= 1000000) {
                                        return '₱' + (value / 1000000).toFixed(1) + 'M';
                                    } else if (value >= 1000) {
                                        return '₱' + (value / 1000).toFixed(0) + 'K';
                                    } else {
                                        return '₱' + value;
                                    }
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    // Format with full number in tooltip
                                    label += '₱' + context.parsed.y.toLocaleString();
                                    return label;
                                }
                            }
                        },
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    }
                }
            });



            // Chemical Inventory Chart
            const chemicalCtx = document.getElementById('chemicalInventoryChart');
            if (chemicalCtx) {
                new Chart(chemicalCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($chemical_names); ?>,
                        datasets: [{
                            label: 'Quantity',
                            data: <?php echo json_encode($chemical_quantities); ?>,
                            backgroundColor: <?php echo json_encode($chemical_colors); ?>,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',  // This makes it a horizontal bar chart
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 1
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const index = context.dataIndex;
                                        const unit = <?php echo json_encode($chemical_units); ?>[index];
                                        return `${context.parsed.x} ${unit}`;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>

    <!-- Standalone Notification System for Reports Page -->
    <script src="js/reports-notifications.js"></script>
    <script>
        $(document).ready(function() {
            // Mobile menu toggle
            $('#menuToggle').on('click', function() {
                $('.sidebar').toggleClass('active');
            });
        });
    </script>
</body>
</html>
