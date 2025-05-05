/**
 * JavaScript for the Inspection Report page
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the report modal
    initReportModal();

    // Initialize sorting functionality
    initSorting();
});

/**
 * Initialize the report modal functionality
 */
function initReportModal() {
    const reportModal = document.getElementById('reportModal');

    if (reportModal) {
        // When the modal is about to be shown
        reportModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const appointmentId = button.getAttribute('data-appointment-id');

            // Fetch appointment details
            fetchAppointmentDetails(appointmentId);
        });

        // When the modal is fully shown
        reportModal.addEventListener('shown.bs.modal', function(event) {
            console.log('Modal is now fully visible');
            // The map initialization is now handled in the displayAppointmentDetails function
        });
    }
}

/**
 * Fetch appointment details via AJAX
 * @param {number} appointmentId - The ID of the appointment
 */
function fetchAppointmentDetails(appointmentId) {
    const modalContent = document.getElementById('reportModalContent');

    // Show loading spinner
    modalContent.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading appointment details...</p>
        </div>
    `;

    // Get the current sort parameter to preserve it
    const sortParam = getCurrentSortParam();

    // Fetch appointment details
    fetch(`get_appointment_details.php?appointment_id=${appointmentId}&sort=${sortParam}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayAppointmentDetails(data.appointment);
            } else {
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Error: ${data.message || 'Failed to load appointment details'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Error: Failed to load appointment details
                </div>
            `;
        });
}

/**
 * Display appointment details in the modal
 * @param {Object} appointment - The appointment data
 */
function displayAppointmentDetails(appointment) {
    const modalContent = document.getElementById('reportModalContent');

    // Set appointment ID as data attribute on modal content
    modalContent.setAttribute('data-appointment-id', appointment.appointment_id);

    // Format date and time
    const formattedDate = new Date(appointment.preferred_date).toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });

    const formattedTime = new Date('2000-01-01T' + appointment.preferred_time).toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
    });

    // Determine status
    let statusBadge = '';
    if (appointment.report_id) {
        statusBadge = '<span class="status-badge status-completed">Completed</span>';
    } else if (appointment.technician_id) {
        statusBadge = '<span class="status-badge status-assigned">Scheduled</span>';
    } else {
        statusBadge = '<span class="status-badge status-pending">Pending</span>';
    }

    // Build HTML content
    let html = `
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>${formattedDate} at ${formattedTime}</h4>
                    ${statusBadge}
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Appointment Details</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Location:</strong> ${appointment.location_address.replace(/\s*\[[-\d.,]+\]$/, '')}</p>
                                <p><strong>Type of Place:</strong> ${appointment.kind_of_place}</p>
                                <p><strong>Pest Problems:</strong> ${appointment.pest_problems || 'None specified'}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Date:</strong> ${formattedDate}</p>
                                <p><strong>Time:</strong> ${formattedTime}</p>
                            </div>
                        </div>
                        <p><strong>Notes:</strong> ${appointment.notes || 'No additional notes'}</p>

                        <!-- Map container -->
                        <div class="mt-3">
                            <h6>Location Map:</h6>
                            <div class="location-map-container">
                                <div id="modal-map-${appointment.appointment_id}" class="map" style="width: 100%; height: 200px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
    `;

    // Add technician information if assigned
    if (appointment.technician_id) {
        const technicianPicture = appointment.technician_picture
            ? '../Admin Side/' + appointment.technician_picture
            : '../Admin Side/uploads/technicians/default.png';

        html += `
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Scheduled Technician</h5>
                    <div class="technician-modal-header">
                        <img src="${technicianPicture}" alt="Technician" class="technician-modal-avatar clickable-avatar"
                             onclick="openImageViewer('${technicianPicture}')" title="Click to view larger image">
                        <div>
                            <h5>${appointment.technician_fname && appointment.technician_lname ? `${appointment.technician_fname} ${appointment.technician_lname}` : appointment.technician_name}</h5>
                            <p class="mb-0"><i class="fas fa-phone"></i> ${appointment.technician_contact || 'No contact information'}</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // Add report information if completed
    if (appointment.report_id) {
        const reportDate = new Date(appointment.report_date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        html += `
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Inspection Report</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Completion Time:</strong> ${appointment.end_time || 'Not specified'}</p>
                            <p><strong>Area Treated:</strong> ${appointment.area || 'Not specified'} m²</p>
                            <p><strong>Pest Types:</strong> ${appointment.pest_types || 'Not specified'}</p>
                            <p><strong>Problem Area:</strong> ${appointment.problem_area || 'Not specified'}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Report Date:</strong> ${reportDate}</p>
                        </div>
                    </div>
                    <p><strong>Technician Notes:</strong></p>
                    <div class="border p-3 rounded mb-3">
                        ${appointment.report_notes || 'No additional notes from technician'}
                    </div>
                    <p><strong>Recommendation:</strong></p>
                    <div class="border p-3 rounded mb-3">
                        ${appointment.recommendation || 'No recommendations provided'}
                    </div>
        `;

        // Add attachments if any
        if (appointment.attachments) {
            const attachments = appointment.attachments.split(',');

            html += `
                <h6>Attachments:</h6>
                <div class="report-attachments">
            `;

            attachments.forEach(attachment => {
                html += `
                    <div class="report-attachment-container">
                        <img src="../uploads/${attachment}" alt="Attachment" class="report-attachment"
                             onclick="openImageViewer('../uploads/${attachment}')">
                        <div class="attachment-overlay">
                            <i class="fas fa-search-plus"></i>
                        </div>
                    </div>
                `;
            });

            html += `</div>`;
        }

        html += `</div></div>`;

        // Add feedback section
        html += `
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Technician Feedback</h5>
        `;

        if (appointment.feedback_id || appointment.feedback_rating) {
            // Show existing feedback with verification details
            const feedbackDate = appointment.feedback_date ? new Date(appointment.feedback_date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }) : 'Unknown';

            html += `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Thank you for your feedback and verification!
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Verification Details</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Technician Arrived
                                <span class="badge ${appointment.technician_arrived == 1 ? 'bg-success' : 'bg-danger'} rounded-pill">
                                    ${appointment.technician_arrived == 1 ? 'Yes' : 'No'}
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Job Completed
                                <span class="badge ${appointment.job_completed == 1 ? 'bg-success' : 'bg-danger'} rounded-pill">
                                    ${appointment.job_completed == 1 ? 'Yes' : 'No'}
                                </span>
                            </li>
                        </ul>
                        ${appointment.verification_notes ? `
                        <div class="mt-3">
                            <strong>Verification Notes:</strong>
                            <p class="mb-0">${appointment.verification_notes}</p>
                        </div>
                        ` : ''}
                    </div>
                </div>

                <div class="feedback-display">
                    <div class="rating-stars mb-2">
            `;

            // Display stars
            for (let i = 1; i <= 5; i++) {
                html += `<i class="fas fa-star ${i <= (appointment.rating || appointment.feedback_rating) ? 'text-warning' : 'text-secondary'}"></i> `;
            }

            html += `
                    </div>
                    <div class="border p-3 rounded mb-2">
                        ${appointment.feedback_comments || 'No additional comments provided.'}
                    </div>
                    <small class="text-muted">Submitted on ${feedbackDate}</small>
                </div>
            `;
        } else {
            // Show feedback form with verification questions
            html += `
                <form method="POST" id="feedbackForm">
                    <input type="hidden" name="report_id" value="${appointment.report_id}">

                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Technician Verification Required</strong>
                            <p class="mb-0">Before proceeding to create a quotation, please verify the following details about your inspection.</p>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Verification Questions</h5>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="technicianArrived" name="technician_arrived" value="1" required>
                                <label class="form-check-label" for="technicianArrived">Did the technician arrive for the inspection?</label>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="jobCompleted" name="job_completed" value="1" required>
                                <label class="form-check-label" for="jobCompleted">Did the technician complete the inspection job?</label>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Additional Verification Notes (optional):</label>
                                <textarea name="verification_notes" class="form-control" rows="2" placeholder="Any additional notes about the technician's work..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Rating:</label>
                        <div class="star-rating">
            `;

            // Add star rating inputs
            for (let i = 5; i >= 1; i--) {
                html += `
                    <input type="radio" id="star${i}" name="rating" value="${i}" required>
                    <label for="star${i}" class="fas fa-star"></label>
                `;
            }

            html += `
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Comments:</label>
                        <textarea name="comments" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="submit" name="submit_feedback" class="btn btn-primary">Submit Feedback & Verification</button>
                </form>
            `;
        }

        html += `</div></div>`;
    }

    html += `</div></div>`;

    // Update modal content
    modalContent.innerHTML = html;

    // Initialize the map after the modal content is updated
    // Use a longer delay to ensure the modal is fully visible
    setTimeout(function() {
        const mapElement = document.getElementById(`modal-map-${appointment.appointment_id}`);
        if (mapElement) {
            console.log('Initializing map in modal:', `modal-map-${appointment.appointment_id}`);
            initAppointmentMap(`modal-map-${appointment.appointment_id}`,
                appointment.location_address.replace(/\s*\[[-\d.,]+\]$/, ''));
        }
    }, 1000);

    // Add event listener for feedback form submission
    const feedbackForm = document.getElementById('feedbackForm');
    if (feedbackForm) {
        feedbackForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitFeedback(this);
        });
    }
}

/**
 * Display the technician scheduled modal
 * @param {Object} appointment - The appointment data
 */
function showTechnicianAssignedModal(appointment) {
    if (!appointment || !appointment.technician_id) return;

    const technicianInfoContainer = document.getElementById('assignedTechnicianInfo');
    const technicianPicture = appointment.technician_picture
        ? '../Admin Side/' + appointment.technician_picture
        : '../Admin Side/uploads/technicians/default.png';

    // Format date and time
    const formattedDate = new Date(appointment.preferred_date).toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });

    const formattedTime = new Date('2000-01-01T' + appointment.preferred_time).toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
    });

    // Build HTML content
    const html = `
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Appointment Information</h5>
                <p><strong>Date:</strong> ${formattedDate}</p>
                <p><strong>Time:</strong> ${formattedTime}</p>
                <p><strong>Location:</strong> ${appointment.location_address.replace(/\s*\[[-\d.,]+\]$/, '')}</p>
                <p><strong>Type of Place:</strong> ${appointment.kind_of_place}</p>
                <p><strong>Pest Problems:</strong> ${appointment.pest_problems || 'None specified'}</p>

                <!-- Map container -->
                <div class="location-map-container mt-2">
                    <div id="assigned-map-${appointment.appointment_id}" class="map" style="width: 100%; height: 200px;"></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Technician Information</h5>
                <div class="technician-modal-header">
                    <img src="${technicianPicture}" alt="Technician" class="technician-modal-avatar clickable-avatar"
                         onclick="openImageViewer('${technicianPicture}')" title="Click to view larger image">
                    <div>
                        <h5>${appointment.technician_fname && appointment.technician_lname ? `${appointment.technician_fname} ${appointment.technician_lname}` : appointment.technician_name}</h5>
                        <p class="mb-0"><i class="fas fa-phone"></i> ${appointment.technician_contact || 'No contact information'}</p>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Update modal content
    technicianInfoContainer.innerHTML = html;

    // Initialize the map after the modal content is updated
    // Use a longer delay to ensure the modal is fully visible
    setTimeout(function() {
        const mapElement = document.getElementById(`assigned-map-${appointment.appointment_id}`);
        if (mapElement) {
            console.log('Initializing map in technician modal:', `assigned-map-${appointment.appointment_id}`);
            initAppointmentMap(`assigned-map-${appointment.appointment_id}`,
                appointment.location_address.replace(/\s*\[[-\d.,]+\]$/, ''));
        }
    }, 1000);

    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('technicianAssignedModal'));
    modal.show();
}

/**
 * Submit feedback form via AJAX
 * @param {HTMLFormElement} form - The feedback form element
 */
function submitFeedback(form) {
    const formData = new FormData(form);
    const submitButton = form.querySelector('button[type="submit"]');
    const originalButtonText = submitButton.innerHTML;

    // Get the current sort parameter to preserve it
    const sortParam = getCurrentSortParam();
    formData.append('sort', sortParam);

    // Disable button and show loading state
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

    fetch('submit_feedback.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message and refresh the modal
            let appointmentId;

            // Try to get appointment ID from response first
            if (data.appointment_id) {
                appointmentId = data.appointment_id;
            } else {
                // Fallback to getting it from the modal content
                const modalContent = document.getElementById('reportModalContent');
                appointmentId = modalContent.getAttribute('data-appointment-id');
            }

            if (appointmentId) {
                fetchAppointmentDetails(appointmentId);
            } else {
                // If we can't get the appointment ID, just show a success message
                alert('Feedback submitted successfully!');
                // Reload the page to refresh the data with the current sort parameter
                const url = new URL(window.location.href);
                url.searchParams.set('sort', sortParam);
                window.location.href = url.toString();
            }
        } else {
            // Show error message
            alert('Error: ' + (data.message || 'Failed to submit feedback'));
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error: Failed to submit feedback');
        submitButton.disabled = false;
        submitButton.innerHTML = originalButtonText;
    });
}

/**
 * Initialize sorting functionality
 */
function initSorting() {
    // Get all sort dropdown items
    const sortItems = document.querySelectorAll('.sort-filter .dropdown-item');

    // Add click event listener to each item
    sortItems.forEach(item => {
        item.addEventListener('click', function(e) {
            // Prevent default link behavior
            e.preventDefault();

            // Get the href attribute which contains the sort parameter
            const href = this.getAttribute('href');

            // Extract the sort parameter
            const sortParam = new URLSearchParams(href.substring(href.indexOf('?'))).get('sort');

            // Get the current URL and update the sort parameter
            const url = new URL(window.location.href);

            // Clear any existing sort parameter
            url.searchParams.delete('sort');

            // Add the new sort parameter
            url.searchParams.set('sort', sortParam);

            // Preserve other parameters like appointment_id or newly_assigned
            if (url.searchParams.has('appointment_id')) {
                const appointmentId = url.searchParams.get('appointment_id');
                url.searchParams.set('appointment_id', appointmentId);
            }

            if (url.searchParams.has('newly_assigned')) {
                const newlyAssigned = url.searchParams.get('newly_assigned');
                url.searchParams.set('newly_assigned', newlyAssigned);
            }

            // Log the URL for debugging
            console.log('Navigating to URL with sort parameter:', url.toString());

            // Navigate to the new URL
            window.location.href = url.toString();
        });
    });

    // Update the dropdown button text to show current sort
    updateSortDropdownText();
}

/**
 * Update the sort dropdown button text to reflect the current sort
 */
function updateSortDropdownText() {
    const sortParam = getCurrentSortParam();
    const sortDropdown = document.getElementById('sortDropdown');

    if (!sortDropdown) return;

    let sortText = 'Sort By';

    // Set the appropriate text based on the current sort
    switch (sortParam) {
        case 'date_desc':
            sortText = 'Newest First';
            break;
        case 'date_asc':
            sortText = 'Oldest First';
            break;
        case 'status_desc':
            sortText = 'Completed First';
            break;
        case 'status_asc':
            sortText = 'Pending First';
            break;
        case 'tech_asc':
            sortText = 'Technician (A-Z)';
            break;
        case 'tech_desc':
            sortText = 'Technician (Z-A)';
            break;
    }

    // Update the dropdown button text
    sortDropdown.innerHTML = `<i class="fas fa-sort"></i> ${sortText}`;
}

/**
 * Helper function to get the current sort parameter from URL
 * @returns {string} The current sort parameter or 'date_desc' as default
 */
function getCurrentSortParam() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('sort') || 'date_desc';
}

/**
 * Helper function to preserve sort parameter in URLs
 * @param {string} url - The URL to add the sort parameter to
 * @returns {string} The URL with the sort parameter added
 */
function addSortParamToUrl(url) {
    const sortParam = getCurrentSortParam();

    // Check if the URL already has parameters
    if (url.includes('?')) {
        return `${url}&sort=${sortParam}`;
    } else {
        return `${url}?sort=${sortParam}`;
    }
}

/**
 * Open the image viewer modal with the specified image
 * @param {string} imageSrc - The source URL of the image to display
 */
function openImageViewer(imageSrc) {
    const fullSizeImage = document.getElementById('fullSizeImage');
    const downloadLink = document.getElementById('downloadImageLink');
    const modalTitle = document.querySelector('#imageViewerModal .modal-title');

    if (fullSizeImage && downloadLink) {
        // Set the image source
        fullSizeImage.src = imageSrc;

        // Set the download link
        downloadLink.href = imageSrc;

        // Extract filename from path for the download attribute
        const filename = imageSrc.substring(imageSrc.lastIndexOf('/') + 1);
        downloadLink.setAttribute('download', filename);

        // Determine if this is a profile picture or an attachment
        const isProfilePic = imageSrc.includes('technicians');

        // Update modal title based on image type
        if (isProfilePic) {
            modalTitle.innerHTML = '<i class="fas fa-user-circle me-2"></i>Technician Profile Picture';

            // Add profile picture specific styling
            fullSizeImage.classList.add('profile-picture-view');
        } else {
            modalTitle.innerHTML = '<i class="fas fa-image me-2"></i>Inspection Image';
            fullSizeImage.classList.remove('profile-picture-view');
        }

        // Show the modal
        const imageViewerModal = new bootstrap.Modal(document.getElementById('imageViewerModal'));
        imageViewerModal.show();

        // Handle image load event to adjust modal size
        fullSizeImage.onload = function() {
            // Force modal to recalculate its position
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
            }, 200);
        };
    }
}