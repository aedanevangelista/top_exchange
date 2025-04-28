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

// Function to toggle delivery address fields
window.toggleDeliveryAddress = function() {
    const addressType = $('#delivery_address_type').val();
    if (addressType === 'company') {
        $('#company_address_container').show();
        $('#custom_address_container').hide();
        
        // Update ship_to with company address
        const companyAddress = $('#company_address').val();
        $('#ship_to').val(companyAddress);
    } else {
        $('#company_address_container').hide();
        $('#custom_address_container').show();
        
        // Update ship_to with custom address
        const customAddress = $('#custom_address').val();
        $('#ship_to').val(customAddress);
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
                
                // Get the selected option
                const selectedOption = $('#username option:selected');
                
                // Load data attributes - using the correct client data
                const billTo = selectedOption.data('bill-to');
                const billToAttn = selectedOption.data('bill-to-attn');
                const shipTo = selectedOption.data('ship-to');
                const shipToAttn = selectedOption.data('ship-to-attn');
                const companyAddress = selectedOption.data('company-address');
                const company = selectedOption.data('company');
                
                // Set form fields - preferring client-specific bill_to and ship_to data
                $('#bill_to').val(billTo || companyAddress || '');
                $('#bill_to_attn').val(billToAttn || company || '');
                $('#ship_to').val(shipTo || companyAddress || '');
                $('#ship_to_attn').val(shipToAttn || company || '');
            }
        });
    }
};

window.prepareOrderData = function() {
    console.log("Selected products before preparing:", selectedProducts);
    
    // Make sure all the address fields have values
    if (!$('#bill_to').val() && $('#username').val()) {
        // If bill_to is empty, use the company address as a default
        const companyAddress = $('#username option:selected').data('company-address') || '';
        $('#bill_to').val(companyAddress);
    }
    
    if (!$('#ship_to').val() && $('#username').val()) {
        // If ship_to is empty, use the company address as a default
        const companyAddress = $('#username option:selected').data('company-address') || '';
        $('#ship_to').val(companyAddress);
    }
    
    // Ensure selectedProducts is not empty
    if (!selectedProducts || selectedProducts.length === 0) {
        // Check if there are products in the summary table
        const summaryProducts = [];
        $('#summaryBody tr').each(function() {
            const row = $(this);
            const productId = row.data('product-id');
            const category = row.find('td:eq(0)').text().trim();
            const itemDescription = row.find('td:eq(1)').text().trim();
            const packaging = row.find('td:eq(2)').text().trim();
            const price = parseFloat(row.find('td:eq(3)').text().replace('PHP ', '').trim());
            const quantity = parseInt(row.find('.summary-quantity').val());
            
            summaryProducts.push({
                product_id: productId,
                category: category,
                item_description: itemDescription,
                packaging: packaging,
                price: price,
                quantity: quantity
            });
        });
        
        if (summaryProducts.length > 0) {
            selectedProducts = summaryProducts;
            console.log("Recovered products from summary table:", selectedProducts);
        } else {
            console.error("No products found in either selectedProducts or summary table");
            // Provide a default empty array to avoid saving "0"
            selectedProducts = [];
        }
    }
    
    const orderData = JSON.stringify(selectedProducts);
    $('#orders').val(orderData);
    
    const totalAmount = calculateCartTotal();
    $('#total_amount').val(totalAmount.toFixed(2));
    
    console.log("Final order data:", {
        orders: $('#orders').val(),
        total_amount: $('#total_amount').val()
    });
};

window.viewOrderDetails = function(orders) {
    try {
        // Make sure the orders variable is properly parsed
        let orderDetails;
        if (typeof orders === 'string') {
            orderDetails = JSON.parse(orders);
        } else {
            orderDetails = orders; // Already an object
        }

        // Check if orderDetails is an array
        if (!Array.isArray(orderDetails)) {
            throw new Error('Order details is not an array');
        }

        const orderDetailsBody = $('#orderDetailsBody');
        orderDetailsBody.empty();
        
        orderDetails.forEach(product => {
            const row = `
                <tr>
                    <td>${product.category || ''}</td>
                    <td>${product.item_description || ''}</td>
                    <td>${product.packaging || ''}</td>
                    <td>PHP ${parseFloat(product.price || 0).toFixed(2)}</td>
                    <td>${product.quantity || 0}</td>
                </tr>
            `;
            orderDetailsBody.append(row);
        });
        
        $('#orderDetailsModal').show();
    } catch (e) {
        console.error('Error parsing order details:', e);
        alert('Error displaying order details: ' + e.message);
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
    
    if (selectedProducts.length === 0) {
        alert('Please add products to your order');
        return;
    }

    prepareOrderData();
    
    // Validate ship_to as the delivery address field
    const shipTo = $('#ship_to').val();
    if (!shipTo || shipTo.trim() === '') {
        alert('Please provide a shipping address in the "Ship To" field');
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
        error: function(xhr, status, error) {
            // Add more detailed error handling
            console.error('AJAX Error:', xhr.responseText);
            
            let errorMsg = 'Error submitting order. Please try again.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = 'Error: ' + xhr.responseJSON.message;
            }
            
            alert(errorMsg);
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

// Variables to store the current PO for PDF generation
    let currentPOData = null;
    
function downloadPODirectly(poNumber, username, company, orderDate, deliveryDate, shipTo, ordersJson, totalAmount, specialInstructions) {
    try {
        // Store current PO data
        currentPOData = {
            poNumber,
            username,
            company,
            orderDate,
            deliveryDate,
            shipTo,  // Updated from deliveryAddress to shipTo
            ordersJson,
            totalAmount,
            specialInstructions
        };
        
        // Populate the hidden PDF content silently
        document.getElementById('printCompany').textContent = company || 'No Company Name';
        document.getElementById('printPoNumber').textContent = poNumber;
        document.getElementById('printUsername').textContent = username;
        document.getElementById('printDeliveryAddress').textContent = shipTo;  // Updated to use shipTo
        document.getElementById('printOrderDate').textContent = orderDate;
        document.getElementById('printDeliveryDate').textContent = deliveryDate;
          
        // Format the total amount
        document.getElementById('printTotalAmount').textContent = parseFloat(totalAmount).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        
        // Hide special instructions section completely regardless of content
        const instructionsSection = document.getElementById('printInstructionsSection');
        instructionsSection.style.display = 'none';
        
        // Parse and populate order items
        const orderItems = JSON.parse(ordersJson);
        const orderItemsBody = document.getElementById('printOrderItems');
        
        // Clear previous content
        orderItemsBody.innerHTML = '';
        
        // Add items to the table
        orderItems.forEach(item => {
            const row = document.createElement('tr');
            
            // Calculate item total
            const itemTotal = parseFloat(item.price) * parseInt(item.quantity);
            
            row.innerHTML = `
                <td>${item.category || ''}</td>
                <td>${item.item_description}</td>
                <td>${item.packaging || ''}</td>
                <td>${item.quantity}</td>
                <td>PHP ${parseFloat(item.price).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                })}</td>
                <td>PHP ${itemTotal.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                })}</td>
            `;
            
            orderItemsBody.appendChild(row);
        });
        
        // Get the element to convert to PDF
        const element = document.getElementById('contentToDownload');
        
        // Configure html2pdf options
        const opt = {
            margin:       [10, 10, 10, 10],
            filename:     `PO_${poNumber}.pdf`,
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        
        // Generate and download PDF directly
        html2pdf().set(opt).from(element).save().then(() => {
            showToast(`Purchase Order ${poNumber} has been downloaded.`, 'success');
        }).catch(error => {
            console.error('Error generating PDF:', error);
            alert('Error generating PDF. Please try again.');
        });
        
    } catch (e) {
        console.error('Error preparing PDF data:', e);
        alert('Error preparing PDF data');
    }
}

    // Function to generate Purchase Order PDF
function generatePO(poNumber, username, company, orderDate, deliveryDate, shipTo, ordersJson, totalAmount, specialInstructions) {
    try {
        // Store current PO data for later use
        currentPOData = {
            poNumber,
            username,
            company,
            orderDate,
            deliveryDate,
            shipTo,  // Updated from deliveryAddress to shipTo
            ordersJson,
            totalAmount,
            specialInstructions
        };
        
        // Set basic information
        document.getElementById('printCompany').textContent = company || 'No Company Name';
        document.getElementById('printPoNumber').textContent = poNumber;
        document.getElementById('printUsername').textContent = username;
        document.getElementById('printDeliveryAddress').textContent = shipTo;  // Updated to use shipTo
        document.getElementById('printOrderDate').textContent = orderDate;
        document.getElementById('printDeliveryDate').textContent = deliveryDate;
        
        // Hide special instructions section completely
        const instructionsSection = document.getElementById('printInstructionsSection');
        instructionsSection.style.display = 'none';
        
        // Format the total amount with commas and decimals
        document.getElementById('printTotalAmount').textContent = parseFloat(totalAmount).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        
        // Parse and populate order items
        const orderItems = JSON.parse(ordersJson);
        const orderItemsBody = document.getElementById('printOrderItems');
        
        // Clear previous content
        orderItemsBody.innerHTML = '';
        
        // Add items to the table
        orderItems.forEach(item => {
            const row = document.createElement('tr');
            
            // Calculate item total
            const itemTotal = parseFloat(item.price) * parseInt(item.quantity);
            
            row.innerHTML = `
                <td>${item.category || ''}</td>
                <td>${item.item_description}</td>
                <td>${item.packaging || ''}</td>
                <td>${item.quantity}</td>
                <td>PHP ${parseFloat(item.price).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                })}</td>
                <td>PHP ${itemTotal.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                })}</td>
            `;
            
            orderItemsBody.appendChild(row);
        });
        
        // Show the PDF preview
        document.getElementById('pdfPreview').style.display = 'block';
        
    } catch (e) {
        console.error('Error preparing PDF data:', e);
        alert('Error preparing PDF data');
    }
}
    
    // Function to close PDF preview
    function closePDFPreview() {
        document.getElementById('pdfPreview').style.display = 'none';
    }
    
    // Function to download the PDF
    function downloadPDF() {
        if (!currentPOData) {
            alert('No PO data available for download.');
            return;
        }
        
        // Get the element to convert to PDF
        const element = document.getElementById('contentToDownload');
        
        // Configure html2pdf options
        const opt = {
            margin:       [10, 10, 10, 10],
            filename:     `PO_${currentPOData.poNumber}.pdf`,
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        
        // Generate and download PDF
        html2pdf().set(opt).from(element).save().then(() => {
            showToast(`Purchase Order ${currentPOData.poNumber} has been downloaded as PDF.`, 'success');
            closePDFPreview();
        }).catch(error => {
            console.error('Error generating PDF:', error);
            alert('Error generating PDF. Please try again.');
        });
    }
    
    window.openStatusModal = function(poNumber, username, ordersJson) {
        $('#statusMessage').text('Change order status for ' + poNumber);
        $('#statusModal').data('po_number', poNumber).show();
        
        // Clear previous data and show loading state
        $('#rawMaterialsContainer').html('<h3>Loading inventory status...</h3>');
        
        // Parse the orders JSON and check materials
        try {
            $.ajax({
                url: '/backend/check_raw_materials.php',
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
                            $('#rawMaterialsContainer').append('<p>All products are in stock - no manufacturing needed</p>');
                        }
                        
                        // Enable or disable the Active button based on overall status
                        updateOrderActionStatus(response);
                    } else {
                        $('#rawMaterialsContainer').html(`
                            <h3>Error Checking Inventory</h3>
                            <p style="color:red;">${response.message || 'Unknown error'}</p>
                            <p>Order status can still be changed.</p>
                        `);
                        $('#activeStatusBtn').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    $('#rawMaterialsContainer').html(`
                        <h3>Server Error</h3>
                        <p style="color:red;">Could not connect to server: ${error}</p>
                        <p>Order status can still be changed.</p>
                    `);
                    $('#activeStatusBtn').prop('disabled', false);
                    console.error("AJAX Error:", status, error);
                }
            });
        } catch (e) {
            $('#rawMaterialsContainer').html(`
                <h3>Error Processing Data</h3>
                <p style="color:red;">${e.message}</p>
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
        const productsTableHTML = `
            <h3>Finished Products Status</h3>
            <table class="materials-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>In Stock</th>
                        <th>Required</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${Object.keys(productsData).map(product => {
                        const data = productsData[product];
                        const available = parseInt(data.available);
                        const required = parseInt(data.required);
                        const isSufficient = data.sufficient;
                        
                        return `
                            <tr>
                                <td>${product}</td>
                                <td>${available}</td>
                                <td>${required}</td>
                                <td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">
                                    ${isSufficient ? 'In Stock' : 'Need to manufacture ' + data.shortfall + ' more'}
                                </td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        `;
        
        // Update the HTML container
        $('#rawMaterialsContainer').html(productsTableHTML);
        
        // Check if any products need manufacturing
        const needsManufacturing = Object.values(productsData).some(product => !product.sufficient);
        
        if (needsManufacturing) {
            $('#rawMaterialsContainer').append(`
                <h3>Raw Materials Required for Manufacturing</h3>
                <div id="raw-materials-section">
                    <p>Loading raw materials information...</p>
                </div>
            `);
        }
    }

    // Function to display raw materials data
    function displayRawMaterials(materialsData) {
        if (!materialsData || Object.keys(materialsData).length === 0) {
            $('#raw-materials-section').html('<p>No raw materials information available.</p>');
            return;
        }
        
        // Count sufficient vs insufficient materials
        let allSufficient = true;
        let insufficientMaterials = [];
        
        const materialsTableHTML = `
            <table class="materials-table">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Available</th>
                        <th>Required</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${Object.keys(materialsData).map(material => {
                        const data = materialsData[material];
                        const available = parseFloat(data.available);
                        const required = parseFloat(data.required);
                        const isSufficient = data.sufficient;
                        
                        if (!isSufficient) {
                            allSufficient = false;
                            insufficientMaterials.push(material);
                        }
                        
                        return `
                            <tr>
                                <td>${material}</td>
                                <td>${formatWeight(available)}</td>
                                <td>${formatWeight(required)}</td>
                                <td class="${isSufficient ? 'material-sufficient' : 'material-insufficient'}">
                                    ${isSufficient ? 'Sufficient' : 'Insufficient'}
                                </td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        `;
        
        // Add status message
        const statusMessage = allSufficient 
            ? 'All raw materials are sufficient for manufacturing.' 
            : `Insufficient raw materials: ${insufficientMaterials.join(', ')}. The order cannot proceed.`;
        
        const statusClass = allSufficient ? 'status-sufficient' : 'status-insufficient';
        
        const fullHTML = `
            ${materialsTableHTML}
            <p class="materials-status ${statusClass}">${statusMessage}</p>
        `;
        
        $('#raw-materials-section').html(fullHTML);
        
        // Enable or disable the Active button
        $('#activeStatusBtn').prop('disabled', !allSufficient);
        
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
        
        // Add a summary at the end of the container
        const statusClass = canProceed ? 'status-sufficient' : 'status-insufficient';
        $('#rawMaterialsContainer').append(`
            <p class="materials-status ${statusClass}">${statusMessage}</p>
        `);
    }

    function closeStatusModal() {
        document.getElementById('statusModal').style.display = 'none';
    }

    // Function to change order status
    function changeStatus(status) {
        var poNumber = $('#statusModal').data('po_number');
        
        // Only deduct materials if changing to Active
        const deductMaterials = (status === 'Active');
        
        $.ajax({
            type: 'POST',
            url: '/backend/update_order_status.php',
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
                closeStatusModal();
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                alert('Failed to change status. Please try again.');
                closeStatusModal();
            }
        });
    }

    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i>
                <div class="message">${message}</div>
            </div>
            <i class="fas fa-times close" onclick="this.parentElement.remove()"></i>
        `;
        document.getElementById('toast-container').appendChild(toast);
        
        // Automatically remove the toast after 5 seconds
        setTimeout(() => {
            toast.remove();
        }, 5000);
    }
    
    function viewOrderDetails(ordersJson) {
        try {
            const orderDetails = JSON.parse(ordersJson);
            const orderDetailsBody = document.getElementById('orderDetailsBody');
            
            // Clear previous content
            orderDetailsBody.innerHTML = '';
            
            orderDetails.forEach(product => {
                const row = document.createElement('tr');
                
                row.innerHTML = `
                    <td>${product.category}</td>
                    <td>${product.item_description}</td>
                    <td>${product.packaging}</td>
                    <td>PHP ${parseFloat(product.price).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    })}</td>
                    <td>${product.quantity}</td>
                `;
                
                orderDetailsBody.appendChild(row);
            });
            
            // Show modal
            document.getElementById('orderDetailsModal').style.display = 'flex';
        } catch (e) {
            console.error('Error parsing order details:', e);
            alert('Error displaying order details');
        }
    }

    function closeOrderDetailsModal() {
        document.getElementById('orderDetailsModal').style.display = 'none';
    }
    
    // Add function to update company name when username changes

        function viewSpecialInstructions(poNumber, instructions) {
                document.getElementById('instructionsPoNumber').textContent = 'PO Number: ' + poNumber;
                const contentEl = document.getElementById('instructionsContent');
                
                if (instructions && instructions.trim().length > 0) {
                    contentEl.textContent = instructions;
                    contentEl.classList.remove('empty');
                } else {
                    contentEl.textContent = 'No special instructions provided for this order.';
                    contentEl.classList.add('empty');
                }
                
                document.getElementById('specialInstructionsModal').style.display = 'block';
            }

        function closeSpecialInstructions() {
            document.getElementById('specialInstructionsModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('specialInstructionsModal');
            if (event.target === modal) {
                closeSpecialInstructions();
            }
        });
