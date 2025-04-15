function filterByStatus() {
    const status = document.getElementById('statusFilter').value;
    const currentUrl = new URL(window.location.href);
    
    if (status) {
        currentUrl.searchParams.set('status', status);
    } else {
        currentUrl.searchParams.delete('status');
    }
    
    window.location.href = currentUrl.toString();
}

// Global variable for selected products
let selectedProducts = [];

// Toast notification function
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toast-container');
    
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icon = document.createElement('i');
    
    // Set appropriate icon based on type
    if (type === 'success') {
        icon.className = 'fas fa-check-circle';
    } else if (type === 'error' || type === 'remove') {
        icon.className = 'fas fa-times-circle';
    } else if (type === 'info') {
        icon.className = 'fas fa-info-circle';
    } else if (type === 'active') {
        icon.className = 'fas fa-check';
    } else if (type === 'pending') {
        icon.className = 'fas fa-clock';
    } else if (type === 'reject') {
        icon.className = 'fas fa-ban';
    } else if (type === 'complete') {
        icon.className = 'fas fa-check-circle';
    }
    
    const text = document.createElement('span');
    text.textContent = message;
    
    toast.appendChild(icon);
    toast.appendChild(text);
    toastContainer.appendChild(toast);
    
    // Remove toast after 3 seconds
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => {
            toastContainer.removeChild(toast);
        }, 300);
    }, 3000);
    
    return toast;
}

// Global function for fetching inventory
function fetchInventory() {
    $.getJSON('/backend/fetch_inventory.php', function(data) {
        const inventory = $('.inventory');
        inventory.empty();

        data.forEach(product => {
            const price = parseFloat(product.price);
            const productElement = `
                <tr>
                    <td>${product.category}</td>
                    <td>${product.item_description}</td>
                    <td>${product.packaging}</td>
                    <td>PHP ${price.toFixed(2)}</td>
                    <td>
                        <input type="number" 
                            class="product-quantity" 
                            min="1" 
                            max="${product.stock_quantity}"
                            data-product-id="${product.product_id}"
                            data-category="${product.category}"
                            data-description="${product.item_description}"
                            data-packaging="${product.packaging}"
                            data-price="${price}">
                    </td>
                    <td>
                        <button class="add-to-cart-btn">Add</button>
                    </td>
                </tr>
            `;
            inventory.append(productElement);
        });
    });
}

// Calculate cart total
function calculateCartTotal() {
    let total = 0;
    selectedProducts.forEach(product => {
        total += product.price * product.quantity;
    });
    return total;
}

// Update cart total display
function updateCartTotal() {
    const total = calculateCartTotal();
    $('.total-amount').text(`PHP ${total.toFixed(2)}`);
}

// Global function for updating order summary
function updateOrderSummary() {
    const summaryBody = $('#summaryBody');
    summaryBody.empty();
    let total = 0;

    selectedProducts.forEach(product => {
        const subtotal = product.price * product.quantity;
        total += subtotal;
        
        const row = `
            <tr>
                <td>${product.category}</td>
                <td>${product.item_description}</td>
                <td>${product.packaging}</td>
                <td>PHP ${product.price.toFixed(2)}</td>
                <td>${product.quantity}</td>
            </tr>
        `;
        summaryBody.append(row);
    });

    $('.summary-total-amount').text(`PHP ${total.toFixed(2)}`);
}

// Global function for populating cart
function populateCart() {
    const cartBody = $('.cart');
    cartBody.empty();
    
    if (selectedProducts.length === 0) {
        $('.no-products').show();
        $('.cart-table').hide();
        $('.total-amount').text(`PHP 0.00`);
    } else {
        $('.no-products').hide();
        $('.cart-table').show();
        
        let total = 0;
        selectedProducts.forEach((product, index) => {
            const subtotal = product.price * product.quantity;
            total += subtotal;
            
            const row = `
                <tr>
                    <td>${product.category}</td>
                    <td>${product.item_description}</td>
                    <td>${product.packaging}</td>
                    <td>PHP ${product.price.toFixed(2)}</td>
                    <td>
                        <input type="number" 
                            class="cart-quantity" 
                            value="${product.quantity}" 
                            min="1" 
                            data-index="${index}">
                        <button class="remove-from-cart" data-index="${index}" data-product="${product.item_description}" data-quantity="${product.quantity}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            cartBody.append(row);
        });
        
        $('.total-amount').text(`PHP ${total.toFixed(2)}`);
    }
}

// Function to toggle delivery address fields
window.toggleDeliveryAddress = function() {
    const addressType = $('#delivery_address_type').val();
    if (addressType === 'company') {
        $('#company_address_container').show();
        $('#custom_address_container').hide();
        
        // Update hidden delivery address field with company address
        const companyAddress = $('#company_address').val();
        $('#delivery_address').val(companyAddress);
    } else {
        $('#company_address_container').hide();
        $('#custom_address_container').show();
        
        // Update hidden delivery address field with custom address
        const customAddress = $('#custom_address').val();
        $('#delivery_address').val(customAddress);
    }
};

// Global functions for modal operations
window.openCartModal = function() {
    $('#cartModal').show();
    populateCart();
};

window.closeCartModal = function() {
    $('#cartModal').hide();
};

window.saveCartChanges = function() {
    $('.cart-quantity').each(function() {
        const index = $(this).data('index');
        const newQuantity = parseInt($(this).val(), 10);
        if (newQuantity > 0) {
            selectedProducts[index].quantity = newQuantity;
        }
    });
    
    updateOrderSummary();
    closeCartModal();
};

window.generatePONumber = function() {
    const username = $('#username').val();
    if (username) {
        $.ajax({
            url: '/backend/get_next_po_number.php',
            type: 'POST',
            data: { username: username },
            success: function(response) {
                $('#po_number').val(response.po_number);
                
                // Update the company address field with the selected user's company address
                const selectedOption = $('#username option:selected');
                const companyAddress = selectedOption.data('company-address');
                $('#company_address').val(companyAddress || 'No company address available');
                
                // If company address is selected, update the delivery address field
                if ($('#delivery_address_type').val() === 'company') {
                    $('#delivery_address').val(companyAddress || 'No company address available');
                }
            }
        });
    }
};

window.prepareOrderData = function() {
    // Update delivery address based on the selected type
    const addressType = $('#delivery_address_type').val();
    if (addressType === 'company') {
        $('#delivery_address').val($('#company_address').val());
    } else {
        $('#delivery_address').val($('#custom_address').val());
    }
    
    const orderData = JSON.stringify(selectedProducts);
    $('#orders').val(orderData);
    const totalAmount = calculateCartTotal();
    $('#total_amount').val(totalAmount.toFixed(2));
};

window.viewOrderDetails = function(orders) {
    try {
        const orderDetails = JSON.parse(orders);
        const orderDetailsBody = $('#orderDetailsBody');
        orderDetailsBody.empty();
        
        orderDetails.forEach(product => {
            const row = `
                <tr>
                    <td>${product.category}</td>
                    <td>${product.item_description}</td>
                    <td>${product.packaging}</td>
                    <td>PHP ${parseFloat(product.price).toFixed(2)}</td>
                    <td>${product.quantity}</td>
                </tr>
            `;
            orderDetailsBody.append(row);
        });
        
        $('#orderDetailsModal').show();
    } catch (e) {
        console.error('Error parsing order details:', e);
        alert('Error displaying order details');
    }
};

window.openAddOrderForm = function() {
    $('#addOrderOverlay').show();
};

window.closeAddOrderForm = function() {
    $('#addOrderOverlay').hide();
    selectedProducts = [];
    updateOrderSummary();
};

window.openInventoryOverlay = function() {
    $('#inventoryOverlay').show();
    fetchInventory();
};

window.closeInventoryOverlay = function() {
    $('#inventoryOverlay').hide();
};

window.closeOrderDetailsModal = function() {
    $('#orderDetailsModal').hide();
};

window.openStatusModal = function(poNumber, username) {
    $('#statusMessage').text('Change order-status for ' + poNumber);
    $('#statusModal').data('po_number', poNumber).show();
};

window.closeStatusModal = function() {
    $('#statusModal').hide();
};

window.changeStatus = function(status) {
    var poNumber = $('#statusModal').data('po_number');
    $.ajax({
        type: 'POST',
        url: '/backend/update_order_status.php',
        data: { 
            po_number: poNumber, 
            status: status
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Convert status to lowercase for consistency in toast types
                // and handle variations like "Completed"/"Complete" and "Rejected"/"Reject"
                let toastType = status.toLowerCase();
                
                // Standardize status names for CSS classes
                if (toastType === 'completed') {
                    toastType = 'complete';
                } else if (toastType === 'rejected') {
                    toastType = 'reject';
                }
                
                showToast(`Changed status for ${poNumber} to ${status}.`, toastType);
                
                // Wait a moment for the toast to be visible before reloading
                setTimeout(() => {
                    location.reload();
                }, 1500);
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

// Document ready function
$(document).ready(function() {
    // Initialize datepicker for delivery date
    $('#delivery_date').datepicker({
        dateFormat: 'yy-mm-dd',
        minDate: 0,
        beforeShowDay: function(date) {
            const day = date.getDay();
            return [day === 1 || day === 3 || day === 5];
        }
    });

    // Set current date for order_date
    $('#order_date').val(new Date().toISOString().split('T')[0]);

    // Initialize delivery address type change handler
    $('#delivery_address_type').change(function() {
        toggleDeliveryAddress();
    });

    // Initialize custom address input change handler
    $('#custom_address').on('input', function() {
        if ($('#delivery_address_type').val() === 'custom') {
            $('#delivery_address').val($(this).val());
        }
    });

    // Initialize inventory search and filter
    $('#inventorySearch').on('keyup', function() {
        const searchText = $(this).val().toLowerCase();
        $('.inventory tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(searchText) > -1);
        });
    });

    // Add product to cart
    $(document).on('click', '.add-to-cart-btn', function() {
        const row = $(this).closest('tr');
        const quantityInput = row.find('.product-quantity');
        const quantity = parseInt(quantityInput.val(), 10);
        
        if (!quantity || quantity < 1) {
            alert('Please enter a valid quantity');
            return;
        }

        const product = {
            product_id: quantityInput.data('product-id'),
            category: quantityInput.data('category'),
            item_description: quantityInput.data('description'),
            packaging: quantityInput.data('packaging'),
            price: parseFloat(quantityInput.data('price')),
            quantity: quantity
        };

        const existingProductIndex = selectedProducts.findIndex(p => 
            p.product_id === product.product_id);

        if (existingProductIndex !== -1) {
            selectedProducts[existingProductIndex].quantity += quantity;
        } else {
            selectedProducts.push(product);
        }

        // Show toast notification immediately with updated text
        showToast(`${product.item_description} with a quantity of ${quantity} has been added`, 'success');

        quantityInput.val('');
        updateOrderSummary();
    });

    // Remove product from cart
    $(document).on('click', '.remove-from-cart', function() {
        const index = $(this).data('index');
        const productName = $(this).data('product');
        const quantity = $(this).data('quantity');
        
        // Show red toast notification for removal
        showToast(`${productName} with a quantity of ${quantity} has been removed`, 'remove');
        
        selectedProducts.splice(index, 1);
        
        // Update both the order summary and cart displays
        updateOrderSummary();
        updateCartTotal();
        populateCart();
    });

    // Handle quantity change in cart
    $(document).on('change', '.cart-quantity', function() {
        const index = $(this).data('index');
        const newQuantity = parseInt($(this).val(), 10);
        
        if (newQuantity > 0) {
            selectedProducts[index].quantity = newQuantity;
            updateCartTotal();
        }
    });

    // Form submission
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
        
        // Show a toast notification when saving the order
        const poNumber = $('#po_number').val();
        const username = $('#username').val();
        
        if (poNumber && username) {
            showToast(`The order: ${poNumber} has been created for ${username}.`, 'success');
        }

        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Wait a moment for the toast to be visible before reloading
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Error submitting order. Please try again.');
            }
        });
    });
    
    // Category filter change handler
    $('#inventoryFilter').on('change', function() {
        const category = $(this).val();
        if (category === 'all') {
            $('.inventory tr').show();
        } else {
            $('.inventory tr').hide();
            $(`.inventory tr:contains('${category}')`).show();
        }
    });

    // Fetch categories for filter
    $.getJSON('/backend/fetch_categories.php', function(categories) {
        const filter = $('#inventoryFilter');
        filter.empty();
        filter.append('<option value="all">All Categories</option>');
        categories.forEach(category => {
            filter.append(`<option value="${category}">${category}</option>`);
        });
    });

    function checkRawMaterialAvailability() {
    const orderData = {
        orders: document.getElementById('orders').value
    };

    // Disable the save button during check
    const saveBtn = document.querySelector('.save-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking materials...';

    // AJAX request to check material availability
    $.ajax({
        url: '/backend/check_raw_materials_api.php',
        type: 'POST',
        data: orderData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Materials are available, continue with form submission
                document.getElementById('addOrderForm').submit();
            } else {
                // Show material shortage notification
                showMaterialShortageDialog(response.insufficientMaterials);
                
                // Re-enable the save button
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save"></i> Save';
            }
        },
        error: function(xhr, status, error) {
            showToast('Error checking material availability: ' + error, 'error');
            
            // Re-enable the save button
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Save';
        }
    });
}

// Function to display material shortage in a dialog
function showMaterialShortageDialog(insufficientMaterials) {
    // Create the modal if it doesn't exist
    if (!document.getElementById('materialShortageModal')) {
        const modal = document.createElement('div');
        modal.id = 'materialShortageModal';
        modal.className = 'modal';
        modal.style.display = 'none';
        
        modal.innerHTML = `
            <div class="modal-content">
                <h2><i class="fas fa-exclamation-triangle"></i> Insufficient Raw Materials</h2>
                <p>The following raw materials have insufficient quantities:</p>
                <div class="material-shortage-table-container">
                    <table class="material-shortage-table">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Available</th>
                                <th>Required</th>
                                <th>Shortage</th>
                            </tr>
                        </thead>
                        <tbody id="materialShortageBody">
                            <!-- Will be populated dynamically -->
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button onclick="closeMaterialShortageModal()" class="modal-cancel-btn">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }
    
    // Populate the shortage details
    const tableBody = document.getElementById('materialShortageBody');
    tableBody.innerHTML = '';
    
    for (const material in insufficientMaterials) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${material}</td>
            <td>${insufficientMaterials[material].available} g</td>
            <td>${insufficientMaterials[material].required} g</td>
            <td class="shortage">${insufficientMaterials[material].missing} g</td>
        `;
        tableBody.appendChild(row);
    }
    
    // Show the modal
    document.getElementById('materialShortageModal').style.display = 'block';
}

// Function to close the material shortage modal
function closeMaterialShortageModal() {
    document.getElementById('materialShortageModal').style.display = 'none';
}

// Override the form submission to first check material availability
function prepareOrderData() {
    // Get all selected products
    const selectedProducts = [];
    $('.cart tr').each(function() {
        const productId = $(this).data('product-id');
        const category = $(this).find('td:eq(0)').text();
        const item_description = $(this).find('td:eq(1)').text();
        const packaging = $(this).find('td:eq(2)').text();
        const price = parseFloat($(this).find('td:eq(3)').text().replace('PHP ', ''));
        const quantity = parseInt($(this).find('td:eq(4)').text());
        
        selectedProducts.push({
            product_id: productId,
            category: category,
            item_description: item_description,
            packaging: packaging,
            price: price,
            quantity: quantity
        });
    });
    
    // Set the orders input for the form
    document.getElementById('orders').value = JSON.stringify(selectedProducts);
    
    // Calculate total
    const totalAmount = selectedProducts.reduce((sum, product) => sum + (product.price * product.quantity), 0);
    document.getElementById('total_amount').value = totalAmount.toFixed(2);
    
    // Get final delivery address
    const deliveryAddressType = document.getElementById('delivery_address_type').value;
    if (deliveryAddressType === 'company') {
        document.getElementById('delivery_address').value = document.getElementById('company_address').value;
    } else {
        document.getElementById('delivery_address').value = document.getElementById('custom_address').value;
    }
    
    // Instead of submitting the form, check material availability first
    event.preventDefault();
    checkRawMaterialAvailability();
}

// Add this function to show material details when viewing order details
function showMaterialRequirements(ordersJson) {
    // First convert the orders JSON string to an object
    const orders = JSON.parse(ordersJson);
    
    // Get material details through AJAX
    $.ajax({
        url: '/backend/get_material_requirements.php',
        type: 'POST',
        data: { orders: ordersJson },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Create and show the materials modal
                showMaterialRequirementsModal(response.materials, response.availableMaterials);
            } else {
                showToast('Error fetching material requirements: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            showToast('Error: ' + error, 'error');
        }
    });
}

// Function to show a modal with material requirements
function showMaterialRequirementsModal(materials, availableMaterials) {
    // Create the modal if it doesn't exist
    if (!document.getElementById('materialRequirementsModal')) {
        const modal = document.createElement('div');
        modal.id = 'materialRequirementsModal';
        modal.className = 'overlay';
        modal.style.display = 'none';
        
        modal.innerHTML = `
            <div class="overlay-content">
                <h2><i class="fas fa-list-check"></i> Raw Materials Requirements</h2>
                <div class="material-requirements-container">
                    <table class="material-requirements-table">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Available</th>
                                <th>Required</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="materialRequirementsBody">
                            <!-- Will be populated dynamically -->
                        </tbody>
                    </table>
                </div>
                <div class="form-buttons" style="margin-top: 20px;">
                    <button type="button" class="back-btn" onclick="closeMaterialRequirementsModal()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    }
    
    // Populate the requirements details
    const tableBody = document.getElementById('materialRequirementsBody');
    tableBody.innerHTML = '';
    
    for (const material in materials) {
        const row = document.createElement('tr');
        const required = materials[material].required;
        const available = availableMaterials[material] ? availableMaterials[material].available : 0;
        const status = available >= required ? 'Available' : 'Insufficient';
        const statusClass = available >= required ? 'material-available' : 'material-unavailable';
        
        row.innerHTML = `
            <td>${material}</td>
            <td>${available} g</td>
            <td>${required} g</td>
            <td class="${statusClass}">${status}</td>
        `;
        tableBody.appendChild(row);
    }
    
    // Show the modal
    document.getElementById('materialRequirementsModal').style.display = 'block';
}

// Function to close the material requirements modal
function closeMaterialRequirementsModal() {
    document.getElementById('materialRequirementsModal').style.display = 'none';
}

// Add a new button to view orders that also shows material requirements
function viewOrderDetailsWithMaterials(ordersJson) {
    // First show the regular order details
    viewOrderDetails(ordersJson);
    
    // Then show material requirements
    showMaterialRequirements(ordersJson);
}
});