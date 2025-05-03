<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['admin_user_id']) && !isset($_SESSION['client_user_id']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Reports</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Basic styling */
        body { font-family: sans-serif; margin: 20px; }
        .report-container { margin-top: 20px; padding: 15px; border: 1px solid #ccc; border-radius: 5px; background-color: #f9f9f9; }
        .report-options label { display: block; margin-bottom: 5px; } /* Reduced margin */
        .report-options input[type="date"],
        .report-options button { padding: 8px; margin-right: 10px; margin-top: 5px; }
        .report-options button { cursor: pointer; background-color: #007bff; color: white; border: none; border-radius: 4px; }
        .report-options button:hover { background-color: #0056b3; }
        #report-output { margin-top: 20px; border-top: 1px dashed #ccc; padding-top: 20px; }
        #loading-indicator { display: none; margin-top: 15px; font-style: italic; color: #555; }
        .error-message-frontend { color: #dc3545; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-top: 10px; }

        /* Date container styling */
        .date-range-container {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        /* Report Type Selection Styling */
        .report-type-selection label {
             margin-bottom: 10px; /* Space between report types */
        }

        /* Inventory Tabs Styling */
        .inventory-tabs-container {
            display: none; /* Hidden by default */
            margin-top: 10px;
            margin-bottom: 15px;
            padding-left: 20px; /* Indent tabs slightly */
        }
        .inventory-tab {
            display: inline-block;
            padding: 8px 15px;
            border: 1px solid #ccc;
            border-bottom: none;
            cursor: pointer;
            background-color: #f1f1f1;
            margin-right: -1px; /* Overlap borders */
            border-radius: 4px 4px 0 0;
            color: #007bff; /* Link-like color */
        }
        .inventory-tab.active {
            background-color: #fff;
            border-color: #ccc;
            border-bottom: 1px solid #fff; /* Cover bottom border */
            font-weight: bold;
             color: #333; /* Darker color for active */
        }
        .inventory-tab i { /* Style for icons */
            margin-right: 5px;
        }


        /* Table Styling */
        .accounts-table, .summary-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .accounts-table th, .accounts-table td,
        .summary-table th, .summary-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .accounts-table th { background-color: #f2f2f2; }
        .accounts-table td[style*="text-align: right;"],
        .summary-table td[style*="text-align: right;"] { text-align: right; }
        .status-completed { color: green; } .status-pending { color: orange; } .status-cancelled { color: red; }

    </style>
</head>
<body>

    <h1>Generate Reports</h1>

    <div class="report-container">
        <h2>Select Report Options</h2>
        <form id="report-form" class="report-options">

             <!-- Date Range Container - Initially hidden, shown by JS -->
             <div class="date-range-container" id="date-range-container" style="display: none;">
                <div>
                    <label for="start_date">Start Date:</label>
                    <input type="date" id="start_date" name="start_date">
                </div>
                <div>
                    <label for="end_date">End Date:</label>
                    <input type="date" id="end_date" name="end_date">
                </div>
             </div>

            <p><strong>Select Report Type:</strong></p>
            <div class="report-type-selection">
                <!-- Standard Report Types (Radio Buttons) -->
                <div>
                    <label>
                        <input type="radio" name="report_type" value="sales_summary" required> Sales Summary
                    </label>
                </div>
                 <div>
                     <label>
                         <input type="radio" name="report_type" value="order_trends" required> Order Listing
                     </label>
                 </div>

                 <!-- Inventory Status (Placeholder Radio + Tabs) -->
                 <div>
                     <label>
                         <!-- This radio acts as the main trigger for inventory -->
                         <input type="radio" name="report_type" value="inventory_status" required> Inventory Status
                     </label>
                     <!-- Inventory Tabs Container -->
                     <div class="inventory-tabs-container" id="inventory-tabs-container">
                         <span class="inventory-tab active" data-source="company">
                             <i class="fas fa-building"></i> Company Orders
                         </span>
                         <span class="inventory-tab" data-source="walkin">
                             <i class="fas fa-walking"></i> Walk-in Customers
                         </span>
                         <!-- Hidden input to store the active inventory source -->
                         <input type="hidden" id="inventory_source" name="inventory_source" value="company">
                     </div>
                 </div>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit">Generate Report</button>
            </div>
        </form>

        <div id="loading-indicator">
            <i class="fas fa-spinner fa-spin"></i> Loading report...
        </div>

        <div id="report-output">
            <p>Select report options and click "Generate Report".</p>
        </div>
    </div>

    <script>
        const reportForm = document.getElementById('report-form');
        const reportOutput = document.getElementById('report-output');
        const loadingIndicator = document.getElementById('loading-indicator');
        const inventoryTabsContainer = document.getElementById('inventory-tabs-container');
        const inventoryTabs = document.querySelectorAll('.inventory-tab');
        const hiddenInventorySourceInput = document.getElementById('inventory_source');
        const dateRangeContainer = document.getElementById('date-range-container');
        const reportTypeRadios = document.querySelectorAll('input[name="report_type"]');

        // --- Function to Update UI Based on Report Type ---
        function updateUIForReportType(selectedType) {
            const requiresDates = ['sales_summary', 'order_trends'].includes(selectedType);
            const isInventory = selectedType === 'inventory_status';

            // Show/Hide Dates
            dateRangeContainer.style.display = requiresDates ? 'block' : 'none';
            if (!requiresDates) {
                 document.getElementById('start_date').value = '';
                 document.getElementById('end_date').value = '';
            }

            // Show/Hide Inventory Tabs
            inventoryTabsContainer.style.display = isInventory ? 'block' : 'none';

             // If switching *away* from inventory, clear the hidden source value? (optional)
             // if (!isInventory) {
             //     hiddenInventorySourceInput.value = '';
             // }
        }

        // --- Event Listener for Report Type Radio Buttons ---
        reportTypeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                updateUIForReportType(this.value);
                // If Inventory Status is selected, trigger the default tab's report automatically? Or wait for submit?
                // For now, we wait for submit or tab click.
            });
        });

        // --- Event Listener for Inventory Tabs ---
        inventoryTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                // Set the main report type radio to 'inventory_status'
                const inventoryRadio = document.querySelector('input[name="report_type"][value="inventory_status"]');
                if (inventoryRadio && !inventoryRadio.checked) {
                    inventoryRadio.checked = true;
                    // Manually trigger UI update if radio wasn't checked
                    updateUIForReportType('inventory_status');
                }

                // Update active tab style
                inventoryTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                // Update hidden input value
                const source = this.getAttribute('data-source');
                hiddenInventorySourceInput.value = source;

                // Automatically fetch report when tab is clicked
                fetchReport();
            });
        });

         // --- Initial Check on Page Load ---
         function initializeFormState() {
             const selectedReportTypeInput = document.querySelector('input[name="report_type"]:checked');
             if (selectedReportTypeInput) {
                 updateUIForReportType(selectedReportTypeInput.value);
                 // Set initial hidden value if inventory is pre-selected
                 if (selectedReportTypeInput.value === 'inventory_status') {
                     const activeTab = document.querySelector('.inventory-tab.active');
                     if (activeTab) {
                        hiddenInventorySourceInput.value = activeTab.getAttribute('data-source');
                     }
                 }
             } else {
                 // Default state if nothing checked
                 dateRangeContainer.style.display = 'none';
                 inventoryTabsContainer.style.display = 'none';
             }
         }
         initializeFormState(); // Run on page load


        // --- Form Submission Handler (for non-tab reports) ---
        reportForm.addEventListener('submit', function(event) {
            event.preventDefault();
            // Fetch report only if it's NOT inventory (inventory fetches on tab click)
            const selectedReportTypeInput = document.querySelector('input[name="report_type"]:checked');
            if (selectedReportTypeInput && selectedReportTypeInput.value !== 'inventory_status') {
                 fetchReport();
            } else if (!selectedReportTypeInput) {
                 reportOutput.innerHTML = `<div class="error-message-frontend">Please select a report type.</div>`;
            } else if (selectedReportTypeInput.value === 'inventory_status') {
                // If inventory is selected but submit is clicked, fetch based on the active tab
                fetchReport();
            }
        });

        // --- Function to Fetch and Display Report ---
        function fetchReport() {
            const selectedReportTypeInput = document.querySelector('input[name="report_type"]:checked');
            if (!selectedReportTypeInput) {
                reportOutput.innerHTML = `<div class="error-message-frontend">Please select a report type.</div>`;
                return;
            }
            const reportType = selectedReportTypeInput.value;

            let inventorySource = null;
            if (reportType === 'inventory_status') {
                 inventorySource = hiddenInventorySourceInput.value; // Get from hidden input
                 if (!inventorySource) {
                     // Should not happen if initialized correctly, but check anyway
                     console.error("Inventory source hidden input is empty!");
                     reportOutput.innerHTML = `<div class="error-message-frontend">Internal error: Inventory source not selected.</div>`;
                     return;
                 }
            }

            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;

            const formData = new FormData();
            formData.append('report_type', reportType);

            const requiresDates = ['sales_summary', 'order_trends'].includes(reportType);
            if (requiresDates) {
                 if (startDate) formData.append('start_date', startDate);
                 if (endDate) formData.append('end_date', endDate);
            }

            if (inventorySource) {
                formData.append('inventory_source', inventorySource);
            }

            loadingIndicator.style.display = 'block';
            reportOutput.innerHTML = '';
            console.log("Sending data to backend:", Object.fromEntries(formData));

            fetch('backend/fetch_report.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => { throw new Error(`HTTP error ${response.status}: ${text}`); });
                }
                return response.text();
            })
            .then(data => {
                reportOutput.innerHTML = data;
            })
            .catch(error => {
                console.error('Error fetching report:', error);
                reportOutput.innerHTML = `<div class="error-message-frontend">Error loading report: ${error.message}</div>`;
            })
            .finally(() => {
                loadingIndicator.style.display = 'none';
            });
        }

    </script>

</body>
</html>