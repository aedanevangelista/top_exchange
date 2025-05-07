/**
 * JavaScript for handling inspection report details
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the inspection report modal
    initInspectionModal();
});

/**
 * Initialize the inspection report modal functionality
 */
function initInspectionModal() {
    const inspectionModal = document.getElementById('inspectionModal');

    if (inspectionModal) {
        // When the modal is about to be shown
        inspectionModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const reportId = button.getAttribute('data-report-id');

            // Fetch inspection report details
            fetchInspectionDetails(reportId);
        });

        // When the modal is fully shown
        inspectionModal.addEventListener('shown.bs.modal', function(event) {
            console.log('Inspection modal is now fully visible');
        });
    }
}

/**
 * Fetch inspection report details via AJAX
 * @param {number} reportId - The ID of the inspection report
 */
function fetchInspectionDetails(reportId) {
    const modalContent = document.getElementById('inspectionModalContent');

    // Show loading spinner
    modalContent.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading inspection report details...</p>
        </div>
    `;

    // Fetch inspection report details using jQuery AJAX
    $.ajax({
        url: 'ajax/get_inspection_details.php',
        type: 'POST',
        data: { report_id: reportId },
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                displayInspectionDetails(data.report);
            } else {
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Error: ${data.message || 'Failed to load inspection report details'}
                    </div>
                `;
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', error);
            modalContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Error: Failed to load inspection report details
                </div>
            `;
        }
    });
}

/**
 * Display inspection report details in the modal
 * @param {Object} report - The inspection report data
 */
function displayInspectionDetails(report) {
    const modalContent = document.getElementById('inspectionModalContent');

    // Format date and time
    const reportDate = new Date(report.report_date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });

    // Build HTML content
    let html = `
        <div class="row">
            <div class="col-md-12">
                <h4 class="mb-3">Inspection Report</h4>

                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Report Details</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Report Date:</strong> ${reportDate}</p>
                                <p><strong>Completion Time:</strong> ${report.end_time || 'Not specified'}</p>
                                <p><strong>Area Treated:</strong> ${report.area || 'Not specified'} m²</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Pest Types:</strong> ${report.pest_types || 'Not specified'}</p>
                                <p><strong>Problem Area:</strong> ${report.problem_area || 'Not specified'}</p>
                            </div>
                        </div>
                        <p><strong>Technician Notes:</strong></p>
                        <div class="border p-3 rounded mb-3">
                            ${report.report_notes || 'No additional notes from technician'}
                        </div>
    `;

    // Add attachments if any
    if (report.attachments) {
        const attachments = report.attachments.split(',');

        html += `
            <h6>Attachments:</h6>
            <div class="report-attachments">
        `;

        attachments.forEach(attachment => {
            html += `
                <a href="../uploads/${attachment}" target="_blank">
                    <img src="../uploads/${attachment}" alt="Attachment" class="report-attachment">
                </a>
            `;
        });

        html += `</div>`;
    }

    html += `</div></div>`;

    // Add technician information if assigned
    if (report.technician_id) {
        const technicianPicture = report.technician_picture
            ? report.technician_picture
            : '../uploads/default-avatar.png';

        html += `
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Assigned Technician</h5>
                    <div class="technician-modal-header">
                        <img src="${technicianPicture}" alt="Technician" class="technician-modal-avatar">
                        <div>
                            <h5>${report.technician_fname && report.technician_lname ? `${report.technician_fname} ${report.technician_lname}` : report.technician_name}</h5>
                            <p class="mb-0"><i class="fas fa-phone"></i> ${report.technician_contact || 'No contact information'}</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // Add verification information if available
    if (report.feedback_id) {
        const feedbackDate = report.feedback_date ? new Date(report.feedback_date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        }) : 'Unknown';

        html += `
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Client Verification on Technician Job</h5>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            The technician arrived
                            <span class="badge ${report.technician_arrived == 1 ? 'bg-success' : 'bg-danger'} rounded-pill">
                                ${report.technician_arrived == 1 ? 'Yes' : 'No'}
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            The Job Completed
                            <span class="badge ${report.job_completed == 1 ? 'bg-success' : 'bg-danger'} rounded-pill">
                                ${report.job_completed == 1 ? 'Yes' : 'No'}
                            </span>
                        </li>
                    </ul>
                    ${report.verification_notes ? `
                    <div class="mt-3">
                        <strong>Verification Notes:</strong>
                        <p class="mb-0">${report.verification_notes}</p>
                    </div>
                    ` : ''}
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Feedback</h5>
                    <div class="rating-stars mb-2">
        `;

        // Display stars
        for (let i = 1; i <= 5; i++) {
            html += `<i class="fas fa-star ${i <= (report.rating) ? 'text-warning' : 'text-secondary'}"></i> `;
        }

        html += `
                    </div>
                    <div class="border p-3 rounded mb-2">
                        ${report.feedback_comments || 'No additional comments provided.'}
                    </div>
                    <small class="text-muted">Submitted on ${feedbackDate}</small>
                </div>
            </div>
        `;
    }

    html += `</div></div>`;

    // Update modal content
    modalContent.innerHTML = html;
}
