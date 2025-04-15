// Add these functions to the existing orders.js file

// Function to check raw material availability
function checkRawMaterialAvailability() {
    if (selectedProducts.length === 0) {
        return Promise.reject('Please add products to your order');
    }

    const orderData = JSON.stringify(selectedProducts);
    
    return new Promise((resolve, reject) => {
        $.ajax({
            url: '/backend/check_raw_materials.php',
            type: 'POST',
            data: { orders: orderData },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    resolve(response);
                } else {
                    reject(response);
                }
            },
            error: function() {
                reject({
                    success: false,
                    message: 'Error checking raw material availability. Please try again.'
                });
            }
        });
    });
}

// Function to display material requirements modal
window.showMaterialRequirements = function() {
    if (selectedProducts.length === 0) {
        alert('Please add products to your order');
        return;
    }

    prepareOrderData();
    checkRawMaterialAvailability()
        .then(response => {
            displayMaterialSummary(response.material_summary, true);
        })
        .catch(response => {
            if (response.material_summary) {
                displayMaterialSummary(response.material_summary, false);
            } else {
                alert(response.message || 'Error checking materials');
            }
        });
};

// Function to display material summary modal
function displayMaterialSummary(materialSummary, isAvailable) {
    // Create a modal to display the material summary
    let modalHTML = `
        <div id="materialSummaryModal" class="overlay" style="display: flex;">
            <div class="overlay-content" style="max-width: 800px; width: 90%;">
                <h2><i class="fas fa-boxes"></i> Raw Material Requirements</h2>
                <div style="margin-bottom: 20px; ${!isAvailable ? 'color: #f44336; font-weight: bold;' : 'color: #4CAF50;'}">
                    ${isAvailable ? 
                        '<i class="fas fa-check-circle"></i> All materials are available in sufficient quantities' : 
                        '<i class="fas fa-exclamation-triangle"></i> Insufficient raw materials - Order cannot proceed'}
                </div>
                <div class="material-summary-container" style="max-height: 60vh; overflow-y: auto;">
                    <table class="inventory-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Required (g)</th>
                                <th>Available (g)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>`;
    
    // Sort materials by status (insufficient first)
    const sortedMaterials = Object.entries(materialSummary).sort((a, b) => {
        const aStatus = a[1].available >= a[1].required;
        const bStatus = b[1].available >= b[1].required;
        if (aStatus === bStatus) return a[0].localeCompare(b[0]); // Alphabetical if same status
        return aStatus ? 1 : -1; // Insufficient first
    });
    
    sortedMaterials.forEach(([materialName, data]) => {
        const isSufficient = data.available >= data.required;
        
        modalHTML += `
            <tr>
                <td>${materialName}</td>
                <td>${data.required.toFixed(2)}</td>
                <td>${data.available.toFixed(2)}</td>
                <td style="color: ${isSufficient ? '#4CAF50' : '#f44336'}">
                    ${isSufficient ? 
                        '<i class="fas fa-check-circle"></i> Sufficient' : 
                        '<i class="fas fa-times-circle"></i> Insufficient'}
                </td>
            </tr>`;
    });
    
    modalHTML += `
                        </tbody>
                    </table>
                </div>
                <div class="form-buttons" style="margin-top: 20px;">
                    <button type="button" class="back-btn" onclick="closeMaterialSummaryModal()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    ${isAvailable ? `
                    <button type="button" class="save-btn" onclick="submitOrderWithConfirmation()">
                        <i class="fas fa-check"></i> Proceed with Order
                    </button>
                    ` : ''}
                </div>
            </div>
        </div>`;
    
    // Remove any existing modal
    $('#materialSummaryModal').remove();
    
    // Add the modal to the page
    $('body').append(modalHTML);
}

// Function to close material summary modal
window.closeMaterialSummaryModal = function() {
    $('#materialSummaryModal').remove();
};

// Function to submit order with confirmation
window.submitOrderWithConfirmation = function() {
    closeMaterialSummaryModal();
    
    // Show a toast notification when saving the order
    const poNumber = $('#po_number').val();
    const username = $('#username').val();
    
    if (poNumber && username) {
        showToast(`The order: ${poNumber} is being processed for ${username}.`, 'info');
    }

    $.ajax({
        url: $('#addOrderForm').attr('action'),
        type: 'POST',
        data: $('#addOrderForm').serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast(`The order: ${poNumber} has been created for ${username} and raw materials were deducted.`, 'success');
                // Wait a moment for the toast to be visible before reloading
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr) {
            let errorMessage = 'Error submitting order. Please try again.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            }
            alert(errorMessage);
        }
    });
};

// Update the form submission handler (replace existing one)
$('#addOrderForm').on('submit', function(e) {
    e.preventDefault();
    
    if (selectedProducts.length === 0) {
        alert('Please add products to your order');
        return;
    }

    prepareOrderData();
    
    // Validate delivery address
    const deliveryAddress = $('#delivery_address').val();
    if (!deliveryAddress || deliveryAddress.trim() === '') {
        alert('Please provide a delivery address');
        return;
    }
    
    // Always show material requirements before proceeding
    showMaterialRequirements();
});