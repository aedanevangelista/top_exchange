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
                    resolve();
                } else {
                    // Format the error message for display
                    let errorMsg = response.message + ':\n\n';
                    if (response.insufficient_materials && response.insufficient_materials.length > 0) {
                        response.insufficient_materials.forEach(item => {
                            errorMsg += `- ${item.product}: ${item.material} (Need: ${item.required}g, Available: ${item.available}g)\n`;
                        });
                    }
                    reject(errorMsg);
                }
            },
            error: function() {
                reject('Error checking raw material availability. Please try again.');
            }
        });
    });
}

// Update the form submission logic (around line 458)
// Replace the existing form submission event handler with this:

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
    
    // First check raw material availability
    checkRawMaterialAvailability()
        .then(() => {
            // Show a toast notification when saving the order
            const poNumber = $('#po_number').val();
            const username = $('#username').val();
            
            if (poNumber && username) {
                showToast(`The order: ${poNumber} is being processed for ${username}.`, 'info');
            }

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: $(this).serialize(),
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
        })
        .catch(error => {
            alert(error);
        });
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
});