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
            // Calculate the maximum allowed quantity (minimum of stock quantity and 200)
            const maxQuantity = Math.min(product.stock_quantity, 200);
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
                            max="200"
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
                        class="summary-quantity" 
                        value="${product.quantity}" 
                        min="1"
                        max="200"
                        data-index="${index}">
                </td>
            </tr>
        `;
        summaryBody.append(row);
    });

    $('.summary-total-amount').text(`PHP ${total.toFixed(2)}`);
    
    // Add event listener for quantity changes in summary
    $('.summary-quantity').on('change input', function() {
        const index = $(this).data('index');
        let newQuantity = parseInt($(this).val(), 10);
        
        if (isNaN(newQuantity) || newQuantity < 1) {
            newQuantity = 1;
            $(this).val(1);
        } else if (newQuantity > 200) {
            newQuantity = 200;
            $(this).val(200);
        }
        
        selectedProducts[index].quantity = newQuantity;
        updateSummaryTotal();
    });
}

// Function to update just the summary total without rebuilding the entire table
function updateSummaryTotal() {
    let total = 0;
    selectedProducts.forEach(product => {
        total += product.price * product.quantity;
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
                            max="200"
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
            selectedProducts[index].quantity = Math.min(newQuantity, 200); // Ensure quantity doesn't exceed 200
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
                
                // Now call updateAddressInfo to populate address fields
                updateAddressInfo(username);
            }
        });
    }
};

window.prepareOrderData = function() {
    // Get address fields directly - no need for delivery_address handling anymore
    const orderData = JSON.stringify(selectedProducts);
    $('#orders').val(orderData);
    const totalAmount = calculateCartTotal();
    $('#total_amount').val(totalAmount.toFixed(2));
    
    // Make sure special instructions are included
    const specialInstructions = $('#special_instructions').val();
    $('#special_instructions_hidden').val(specialInstructions);
    
    // Validate required fields
    if (!$('#username').val()) {
        alert('Please select a username');
        return false;
    }
    
    if (!$('#ship_to').val()) {
        alert('Please provide a Ship To address');
        return false;
    }
    
    if (selectedProducts.length === 0) {
        alert('Please add at least one product to the order');
        return false;
    }
    
    return true;
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
    
    // Clear previous form data
    document.getElementById('username').selectedIndex = 0;
    document.getElementById('bill_to').value = '';
    document.getElementById('bill_to_attn').value = '';
    document.getElementById('ship_to').value = '';
    document.getElementById('ship_to_attn').value = '';
    document.getElementById('special_instructions').value = '';
    
    // Set current date as order date
    const today = new Date();
    const formattedDate = today.toISOString().split('T')[0]; // Format as YYYY-MM-DD
    document.getElementById('order_date').value = formattedDate;
    
    // Initialize datepicker for delivery date
    $("#delivery_date").datepicker({
        dateFormat: 'yy-mm-dd',
        minDate: 0 // Prevent selecting dates in the past
    }).datepicker("setDate", new Date(today.getTime() + 86400000)); // Set default to tomorrow
    
    // Reset selected products
    selectedProducts = [];
    updateOrderSummary();
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

// Function to update address fields based on selected user
function updateAddressInfo(username) {
    if (!username) return;
    
    // Get the selected option element
    const selectedOption = document.querySelector(`#username option[value="${username}"]`);
    if (!selectedOption) return;
    
    // Get address data from data attributes
    const billTo = selectedOption.getAttribute('data-bill-to') || selectedOption.getAttribute('data-company-address') || '';
    const billToAttn = selectedOption.getAttribute('data-bill-to-attn') || selectedOption.getAttribute('data-company') || '';
    const shipTo = selectedOption.getAttribute('data-ship-to') || selectedOption.getAttribute('data-company-address') || '';
    const shipToAttn = selectedOption.getAttribute('data-ship-to-attn') || selectedOption.getAttribute('data-company') || '';
    
    // Set values to form fields
    document.getElementById('bill_to').value = billTo;
    document.getElementById('bill_to_attn').value = billToAttn;
    document.getElementById('ship_to').value = shipTo;
    document.getElementById('ship_to_attn').value = shipToAttn;
    
    // If any of the fields are empty, try to fetch from backend as a fallback
    if (!billTo || !billToAttn || !shipTo || !shipToAttn) {
        fetchAddressInfo(username);
    }
}

// Function to fetch address info from backend
function fetchAddressInfo(username) {
    $.ajax({
        url: '/backend/get_client_address_info.php',
        type: 'GET',
        data: { username: username },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Only update fields if they're empty or if the data from backend is more complete
                const billToField = document.getElementById('bill_to');
                const billToAttnField = document.getElementById('bill_to_attn');
                const shipToField = document.getElementById('ship_to');
                const shipToAttnField = document.getElementById('ship_to_attn');
                
                if (!billToField.value && response.bill_to) {
                    billToField.value = response.bill_to;
                }
                
                if (!billToAttnField.value && response.bill_to_attn) {
                    billToAttnField.value = response.bill_to_attn;
                }
                
                if (!shipToField.value && response.ship_to) {
                    shipToField.value = response.ship_to;
                }
                
                if (!shipToAttnField.value && response.ship_to_attn) {
                    shipToAttnField.value = response.ship_to_attn;
                }
            } else {
                console.warn('Failed to fetch address info:', response.error);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error fetching address info:', error);
        }
    });
}

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

    // Add event listeners for username change to update address fields
    $('#username').change(function() {
        const username = $(this).val();
        if (username) {
            updateAddressInfo(username);
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
            quantity: Math.min(quantity, 200) // Ensure quantity doesn't exceed 200
        };

        const existingProductIndex = selectedProducts.findIndex(p => 
            p.product_id === product.product_id);

        if (existingProductIndex !== -1) {
            // Add to existing product, but cap at 200
            const newTotalQuantity = Math.min(selectedProducts[existingProductIndex].quantity + quantity, 200);
            selectedProducts[existingProductIndex].quantity = newTotalQuantity;
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
    $(document).on('change input', '.cart-quantity', function() {
        const index = $(this).data('index');
        let newQuantity = parseInt($(this).val(), 10);
        
        if (isNaN(newQuantity) || newQuantity < 1) {
            newQuantity = 1;
            $(this).val(1);
        } else if (newQuantity > 200) {
            newQuantity = 200;
            $(this).val(200);
        }
        
        selectedProducts[index].quantity = newQuantity;
        updateCartTotal();
    });

    // Form submission
    $('#addOrderForm').on('submit', function(e) {
        e.preventDefault();
        
        if (!prepareOrderData()) {
            return; // Stop if validation fails
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
    
    // Add a global input event handler to restrict all number inputs in the app to max 200
    $(document).on('input', 'input[type="number"]', function() {
        if (parseInt(this.value) > 200) {
            this.value = 200;
        }
    });
});