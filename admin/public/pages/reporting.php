<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['admin_user_id']) && !isset($_SESSION['client_user_id']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ***** START: Include Layout Files *****
// Include your standard header file (adjust path if necessary)
// This file usually includes <head>, opening <body>, top navigation etc.
include_once 'header.php';

// Include your standard sidebar file (adjust path if necessary)
include_once 'sidebar.php';
// ***** END: Include Layout Files *****

?>
<!DOCTYPE html> <!-- Header.php might already contain this -->
<html lang="en"> <!-- Header.php might already contain this -->
<head> <!-- Header.php likely contains meta, title, CSS links -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Reports</title> <!-- Title might be set in header.php -->
    <!-- Ensure necessary CSS is linked (either here or in header.php) -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Styles specific to the reporting page content area */

        /* Adjust container if your layout uses specific classes */
        .main-content .report-container { /* Example: Target within a main content area */
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        /* ... (keep all the other specific styles for tabs, tables, etc.) ... */
        .report-options label { display: block; margin-bottom: 5px; }
        .report-options input[type="date"],
        .report-options button { padding: 8px; margin-right: 10px; margin-top: 5px; }
        .report-options button { cursor: pointer; background-color: #007bff; color: white; border: none; border-radius: 4px; }
        .report-options button:hover { background-color: #0056b3; }
        #report-output { margin-top: 20px; border-top: 1px dashed #ccc; padding-top: 20px; }
        #loading-indicator { display: none; margin-top: 15px; font-style: italic; color: #555; }
        .error-message-frontend { color: #dc3545; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-top: 10px; }
        .date-range-container { margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .report-type-selection label { margin-bottom: 10px; }
        .inventory-tabs-container { display: none; margin-top: 10px; margin-bottom: 15px; padding-left: 20px; }
        .inventory-tab { display: inline-block; padding: 8px 15px; border: 1px solid #ccc; border-bottom: none; cursor: pointer; background-color: #f1f1f1; margin-right: -1px; border-radius: 4px 4px 0 0; color: #007bff; }
        .inventory-tab.active { background-color: #fff; border-color: #ccc; border-bottom: 1px solid #fff; font-weight: bold; color: #333; }
        .inventory-tab i { margin-right: 5px; }
        .accounts-table, .summary-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .accounts-table th, .accounts-table td,
        .summary-table th, .summary-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .accounts-table th { background-color: #f2f2f2; }
        .accounts-table td[style*="text-align: right;"],
        .summary-table td[style*="text-align: right;"] { text-align: right; }
        .status-completed { color: green; } .status-pending { color: orange; } .status-cancelled { color: red; }

    </style>
</head>
<body> <!-- Header.php might already contain this -->

<!-- Assumes header.php/sidebar.php setup creates a main content wrapper -->
<div class="main-content"> <!-- Or whatever your main content wrapper class is -->

    <h1>Generate Reports</h1>

    <div class="report-container">
        <h2>Select Report Options</h2>
        <form id="report-form" class="report-options">

             <!-- Date Range Container - Initially hidden, shown by JS -->
             <div class="date-range-container" id="date-range-container" style="display: none;">
                 <div> <label for="start_date">Start Date:</label> <input type="date" id="start_date" name="start_date"> </div>
                 <div> <label for="end_date">End Date:</label> <input type="date" id="end_date" name="end_date"> </div>
             </div>

            <p><strong>Select Report Type:</strong></p>
            <div class="report-type-selection">
                <!-- Standard Report Types -->
                <div> <label> <input type="radio" name="report_type" value="sales_summary" required> Sales Summary </label> </div>
                <div> <label> <input type="radio" name="report_type" value="order_trends" required> Order Listing </label> </div>
                 <!-- Inventory Status -->
                 <div>
                     <label> <input type="radio" name="report_type" value="inventory_status" required> Inventory Status </label>
                     <!-- Inventory Tabs Container -->
                     <div class="inventory-tabs-container" id="inventory-tabs-container">
                         <span class="inventory-tab active" data-source="company"> <i class="fas fa-building"></i> Company Orders </span>
                         <span class="inventory-tab" data-source="walkin"> <i class="fas fa-walking"></i> Walk-in Customers </span>
                         <input type="hidden" id="inventory_source" name="inventory_source" value="company">
                     </div>
                 </div>
            </div>

            <div style="margin-top: 20px;"> <button type="submit">Generate Report</button> </div>
        </form>

        <div id="loading-indicator"> <i class="fas fa-spinner fa-spin"></i> Loading report... </div>
        <div id="report-output"> <p>Select report options and click "Generate Report".</p> </div>
    </div>

</div> <!-- End main-content wrapper -->


    <script>
        // --- Keep the same JavaScript as the previous version ---
        const reportForm = document.getElementById('report-form');
        const reportOutput = document.getElementById('report-output');
        const loadingIndicator = document.getElementById('loading-indicator');
        const inventoryTabsContainer = document.getElementById('inventory-tabs-container');
        const inventoryTabs = document.querySelectorAll('.inventory-tab');
        const hiddenInventorySourceInput = document.getElementById('inventory_source');
        const dateRangeContainer = document.getElementById('date-range-container');
        const reportTypeRadios = document.querySelectorAll('input[name="report_type"]');

        function updateUIForReportType(selectedType) {
            const requiresDates = ['sales_summary', 'order_trends'].includes(selectedType);
            const isInventory = selectedType === 'inventory_status';
            dateRangeContainer.style.display = requiresDates ? 'block' : 'none';
            if (!requiresDates) {
                 document.getElementById('start_date').value = '';
                 document.getElementById('end_date').value = '';
            }
            inventoryTabsContainer.style.display = isInventory ? 'block' : 'none';
        }

        reportTypeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                updateUIForReportType(this.value);
            });
        });

        inventoryTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const inventoryRadio = document.querySelector('input[name="report_type"][value="inventory_status"]');
                if (inventoryRadio && !inventoryRadio.checked) {
                    inventoryRadio.checked = true;
                    updateUIForReportType('inventory_status');
                }
                inventoryTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                const source = this.getAttribute('data-source');
                hiddenInventorySourceInput.value = source;
                fetchReport(); // Fetch on tab click
            });
        });

         function initializeFormState() {
             const selectedReportTypeInput = document.querySelector('input[name="report_type"]:checked');
             if (selectedReportTypeInput) {
                 updateUIForReportType(selectedReportTypeInput.value);
                 if (selectedReportTypeInput.value === 'inventory_status') {
                     const activeTab = document.querySelector('.inventory-tab.active');
                     if (activeTab) hiddenInventorySourceInput.value = activeTab.getAttribute('data-source');
                 }
             } else {
                 dateRangeContainer.style.display = 'none';
                 inventoryTabsContainer.style.display = 'none';
             }
         }
         initializeFormState();

        reportForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const selectedReportTypeInput = document.querySelector('input[name="report_type"]:checked');
            if (selectedReportTypeInput && selectedReportTypeInput.value !== 'inventory_status') {
                 fetchReport();
            } else if (!selectedReportTypeInput) {
                 reportOutput.innerHTML = `<div class="error-message-frontend">Please select a report type.</div>`;
            } else if (selectedReportTypeInput.value === 'inventory_status') {
                fetchReport(); // Fetch based on active tab if submit is clicked
            }
        });

        function fetchReport() {
            const selectedReportTypeInput = document.querySelector('input[name="report_type"]:checked');
            if (!selectedReportTypeInput) { reportOutput.innerHTML = `<div class="error-message-frontend">Please select a report type.</div>`; return; }
            const reportType = selectedReportTypeInput.value;
            let inventorySource = null;
            if (reportType === 'inventory_status') {
                 inventorySource = hiddenInventorySourceInput.value;
                 if (!inventorySource) { console.error("Inventory source hidden input is empty!"); reportOutput.innerHTML = `<div class="error-message-frontend">Internal error: Inventory source not selected.</div>`; return; }
            }
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const formData = new FormData();
            formData.append('report_type', reportType);
            const requiresDates = ['sales_summary', 'order_trends'].includes(reportType);
            if (requiresDates) { if (startDate) formData.append('start_date', startDate); if (endDate) formData.append('end_date', endDate); }
            if (inventorySource) { formData.append('inventory_source', inventorySource); }
            loadingIndicator.style.display = 'block'; reportOutput.innerHTML = ''; console.log("Sending data to backend:", Object.fromEntries(formData));
            fetch('backend/fetch_report.php', { method: 'POST', body: formData })
            .then(response => { if (!response.ok) { return response.text().then(text => { throw new Error(`HTTP error ${response.status}: ${text}`); }); } return response.text(); })
            .then(data => { reportOutput.innerHTML = data; })
            .catch(error => { console.error('Error fetching report:', error); reportOutput.innerHTML = `<div class="error-message-frontend">Error loading report: ${error.message}</div>`; })
            .finally(() => { loadingIndicator.style.display = 'none'; });
        }

    </script>

<?php
// ***** START: Include Footer File *****
// Include your standard footer file (adjust path if necessary)
// This file usually includes closing <body>, <html> tags, and maybe common JS
// include_once 'footer.php';
// ***** END: Include Footer File *****
?>
</body> <!-- Footer.php might already contain this -->
</html> <!-- Footer.php might already contain this -->