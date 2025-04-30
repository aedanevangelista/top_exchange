// Extended function to open status modal with raw materials check
window.openStatusModal = function(poNumber, username, ordersJson) {
    $('#statusMessage').text('Change order status for ' + poNumber);
    $('#statusModal').data('po_number', poNumber).show();
    
    // Clear previous data and show loading state
    $('#rawMaterialsContainer').html('<h3>Loading inventory data...</h3>');
    
    // Parse the orders JSON and check materials
    try {
        $.ajax({
            url: '/admin/backend/check_raw_materials.php',
            type: 'POST',
            data: { 
                orders: ordersJson,
                po_number: poNumber
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Display finished products status first
                    if (response.finishedProducts) {
                        displayFinishedProducts(response.finishedProducts);
                    }
                    
                    // If manufacturing is needed, display raw materials
                    if (response.needsManufacturing && response.materials) {
                        displayRawMaterials(response.materials);
                    } else {
                        // Hide the raw materials section if no manufacturing needed
                        $('#rawMaterialsContainer').append('<p class="status-sufficient" style="padding: 10px;">All products are in stock - no manufacturing needed</p>');
                    }
                    
                    // Enable or disable the Active button based on overall status
                    updateOrderActionStatus(response);
                } else {
                    $('#rawMaterialsContainer').html(`
                        <h3>Error Checking Inventory</h3>
                        <p class="status-insufficient" style="padding: 10px;">Error: ${response.message || 'Unknown error'}</p>
                        <p>Order status can still be changed.</p>
                    `);
                    $('#activeStatusBtn').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                $('#rawMaterialsContainer').html(`
                    <h3>Server Error</h3>
                    <p class="status-insufficient" style="padding: 10px;">Error checking inventory: ${error}</p>
                    <p>Order status can still be changed.</p>
                `);
                $('#activeStatusBtn').prop('disabled', false);
                console.error("AJAX Error:", status, error);
            }
        });
    } catch (e) {
        $('#rawMaterialsContainer').html(`
            <h3>Process Error</h3>
            <p class="status-insufficient" style="padding: 10px;">Error processing order data: ${e.message}</p>
            <p>Order status can still be changed.</p>
        `);
        $('#activeStatusBtn').prop('disabled', false);
        console.error("Error:", e);
    }
};

// Helper function to format weight values
function formatWeight(weightInGrams) {
    if (weightInGrams >= 1000) {
        return (weightInGrams / 1000).toFixed(2) + ' kg';
    } else {
        return weightInGrams.toFixed(2) + ' g';
    }
}

// Function to display finished products status
function displayFinishedProducts(productsData) {
    const productsTable = $('<table class="materials-table"></table>');
    const thead = $(`
        <thead>
            <tr>
                <th>Product</th>
                <th>In Stock</th>
                <th>Required</th>
                <th>Status</th>
            </tr>
        </thead>
    `);
    const tbody = $('<tbody></tbody>');
    
    let allSufficient = true;
    let anyCanManufacture = true;
    
    Object.keys(productsData).forEach(product => {
        const data = productsData[product];
        const available = parseInt(data.available);
        const required = parseInt(data.required);
        const isSufficient = data.sufficient;
        
        if (!isSufficient) {
            allSufficient = false;
            
            // Check if product can be manufactured
            if (data.canManufacture === false) {
                anyCanManufacture = false;
            }
        }
        
        const row = $(`
            <tr>
                <td>${product}</td>
                <td>${available}</td>
                <td>${required}</td>
                <td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">
                    ${isSufficient ? 'In Stock' : 'Need to manufacture ' + data.shortfall + ' more'}
                </td>
            </tr>
        `);
        tbody.append(row);
    });
    
    productsTable.append(thead).append(tbody);
    
    // Update the HTML container
    $('#rawMaterialsContainer').html('<h3>Finished Products Status</h3>');
    $('#rawMaterialsContainer').append(productsTable);
    
    // If not all products are in stock but can be manufactured
    if (!allSufficient && anyCanManufacture) {
        $('#rawMaterialsContainer').append('<h3>Raw Materials Required for Manufacturing</h3><div class="materials-table-container"><table class="materials-table"><thead><tr><th>Material</th><th>Available</th><th>Required</th><th>Status</th></tr></thead><tbody id="rawMaterialsBody"></tbody></table></div><div id="materialsStatus" class="materials-status"></div>');
    }
}

// Function to display raw materials data
function displayRawMaterials(materialsData) {
    const rawMaterialsBody = $('#rawMaterialsBody');
    rawMaterialsBody.empty();
    
    // If no materials data
    if (!materialsData || Object.keys(materialsData).length === 0) {
        rawMaterialsBody.html('<tr><td colspan="4" style="text-align:center;">No raw materials data available</td></tr>');
        $('#materialsStatus').text('No raw materials information found. Order status can be changed.');
        $('#materialsStatus').addClass('status-sufficient').removeClass('status-insufficient');
        return true;
    }
    
    // Process materials data
    let allSufficient = true;
    let insufficientMaterials = [];
    
    // Add each material to the table
    Object.keys(materialsData).forEach(material => {
        const data = materialsData[material];
        const available = parseFloat(data.available);
        const required = parseFloat(data.required);
        const isSufficient = data.sufficient;
        
        if (!isSufficient) {
            allSufficient = false;
            insufficientMaterials.push(material);
        }
        
        const row = $(`
            <tr>
                <td>${material}</td>
                <td>${formatWeight(available)}</td>
                <td>${formatWeight(required)}</td>
                <td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">
                    ${isSufficient ? 'Sufficient' : 'Insufficient'}
                </td>
            </tr>
        `);
        rawMaterialsBody.append(row);
    });
    
    // Update overall status and enable/disable button
    if (allSufficient) {
        $('#materialsStatus').text('All raw materials are sufficient for manufacturing.');
        $('#materialsStatus').addClass('status-sufficient').removeClass('status-insufficient');
    } else {
        const message = `Insufficient raw materials: ${insufficientMaterials.join(', ')}`;
        $('#materialsStatus').text(`${message}. The order cannot proceed.`);
        $('#materialsStatus').addClass('status-insufficient').removeClass('status-sufficient');
    }
    
    return allSufficient;
}

// Function to update order action status
function updateOrderActionStatus(response) {
    let canProceed = true;
    let statusMessage = 'All inventory requirements are met. The order can proceed.';
    
    // Check if all finished products are in stock
    const finishedProducts = response.finishedProducts || {};
    const allProductsInStock = Object.values(finishedProducts).every(product => product.sufficient);
    
    // If manufacturing is needed, check raw materials
    if (!allProductsInStock && response.needsManufacturing) {
        // Check if all products can be manufactured
        const canManufactureAll = Object.values(finishedProducts).every(product => 
            product.sufficient || product.canManufacture !== false);
            
        if (!canManufactureAll) {
            canProceed = false;
            statusMessage = 'Some products cannot be manufactured due to missing ingredients.';
        } else {
            // Check if all raw materials are sufficient
            const materials = response.materials || {};
            const allMaterialsSufficient = Object.values(materials).every(material => material.sufficient);
            
            if (!allMaterialsSufficient) {
                canProceed = false;
                statusMessage = 'Insufficient raw materials for manufacturing required products.';
            } else {
                statusMessage = 'Products will be manufactured using raw materials. The order can proceed.';
            }
        }
    }
    
    // Update UI based on status
    $('#activeStatusBtn').prop('disabled', !canProceed);
    
    // Add overall status message if needed
    if ($('#materialsStatus').length === 0) {
        $('#rawMaterialsContainer').append('<div id="materialsStatus" class="materials-status"></div>');
    }
    
    $('#materialsStatus').text(statusMessage);
    
    if (canProceed) {
        $('#materialsStatus').addClass('status-sufficient').removeClass('status-insufficient');
    } else {
        $('#materialsStatus').addClass('status-insufficient').removeClass('status-sufficient');
    }
}

// Function to change order status
window.changeStatus = function(status) {
    var poNumber = $('#statusModal').data('po_number');
    
    // Only deduct materials if changing to Active
    const deductMaterials = (status === 'Active');
    
    $.ajax({
        type: 'POST',
        url: '/admin/backend/update_order_status.php',
        data: { 
            po_number: poNumber, 
            status: status,
            deduct_materials: deductMaterials
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Format status type for toast
                let toastType = status.toLowerCase();
                if (toastType === 'completed') toastType = 'complete';
                if (toastType === 'rejected') toastType = 'reject';
                
                // Create message
                let message = `Changed status for ${poNumber} to ${status}.`;
                if (status === 'Active' && deductMaterials) {
                    message = `Changed status for ${poNumber} to ${status}. Inventory has been updated.`;
                }
                
                // Show toast and reload
                showToast(message, toastType);
                setTimeout(() => location.reload(), 1500);
            } else {
                alert('Failed to change status: ' + (response.error || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', error);
            alert('Failed to change status. Please try again.');
        }
    });
};

// Close the status modal
window.closeStatusModal = function() {
    document.getElementById('statusModal').style.display = 'none';
};