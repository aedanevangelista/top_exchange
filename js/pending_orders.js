// Variables to store the current PO for PDF generation
    let currentPOData = null;
    
function downloadPODirectly(poNumber, username, company, orderDate, deliveryDate, ordersJson, totalAmount, specialInstructions, billTo, billToAttn, shipTo, shipToAttn) {
    try {
        // Store current PO data
        currentPOData = {
            poNumber,
            username,
            company,
            orderDate,
            deliveryDate,
            ordersJson,
            totalAmount,
            specialInstructions,
            billTo,
            billToAttn,
            shipTo,
            shipToAttn
        };
        
        // Populate the hidden PDF content silently
        document.getElementById('printCompany').textContent = company || 'No Company Name';
        document.getElementById('printPoNumber').textContent = poNumber;
        document.getElementById('printUsername').textContent = username;
        document.getElementById('printOrderDate').textContent = orderDate;
        document.getElementById('printDeliveryDate').textContent = deliveryDate;
        
        // Add address information
        const billToSection = document.getElementById('printBillToSection');
        const billToAttnSection = document.getElementById('printBillToAttnSection');
        const shipToSection = document.getElementById('printShipToSection');
        const shipToAttnSection = document.getElementById('printShipToAttnSection');
        
        if (billTo) {
            document.getElementById('printBillTo').textContent = billTo;
            billToSection.style.display = 'block';
        } else {
            billToSection.style.display = 'none';
        }
        
        if (billToAttn) {
            document.getElementById('printBillToAttn').textContent = billToAttn;
            billToAttnSection.style.display = 'block';
        } else {
            billToAttnSection.style.display = 'none';
        }
        
        if (shipTo) {
            document.getElementById('printShipTo').textContent = shipTo;
            shipToSection.style.display = 'block';
        } else {
            shipToSection.style.display = 'none';
        }
        
        if (shipToAttn) {
            document.getElementById('printShipToAttn').textContent = shipToAttn;
            shipToAttnSection.style.display = 'block';
        } else {
            shipToAttnSection.style.display = 'none';
        }
        
        // Format the total amount
        document.getElementById('printTotalAmount').textContent = parseFloat(totalAmount).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        
        // Hide special instructions section if it exists
        const instructionsSection = document.getElementById('printInstructionsSection');
        if (instructionsSection) {
            instructionsSection.style.display = 'none';
        }
        
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
function generatePO(poNumber, username, company, orderDate, deliveryDate, deliveryAddress, ordersJson, totalAmount, specialInstructions, billTo, billToAttn, shipTo, shipToAttn) {
    try {
        // Store current PO data for later use
        currentPOData = {
            poNumber,
            username,
            company,
            orderDate,
            deliveryDate,
            deliveryAddress,
            ordersJson,
            totalAmount,
            specialInstructions,
            billTo,
            billToAttn,
            shipTo,
            shipToAttn
        };
        
        // Set basic information
        document.getElementById('printCompany').textContent = company || 'No Company Name';
        document.getElementById('printPoNumber').textContent = poNumber;
        document.getElementById('printUsername').textContent = username;
        document.getElementById('printDeliveryAddress').textContent = deliveryAddress;
        document.getElementById('printOrderDate').textContent = orderDate;
        document.getElementById('printDeliveryDate').textContent = deliveryDate;
        
        // Populate the billing and shipping information
        document.getElementById('printBillTo').textContent = billTo || 'N/A';
        document.getElementById('printBillToAttn').textContent = billToAttn || 'N/A';
        document.getElementById('printShipTo').textContent = shipTo || 'N/A';
        document.getElementById('printShipToAttn').textContent = shipToAttn || 'N/A';
        
        // Hide rows if data is not present
        document.getElementById('billToRow').style.display = billTo ? 'block' : 'none';
        document.getElementById('billToAttnRow').style.display = billToAttn ? 'block' : 'none';
        document.getElementById('shipToRow').style.display = shipTo ? 'block' : 'none';
        document.getElementById('shipToAttnRow').style.display = shipToAttn ? 'block' : 'none';
        
        // Hide special instructions section completely
        const instructionsSection = document.getElementById('printInstructionsSection');
        if (instructionsSection) {
            instructionsSection.style.display = 'none';
        }
        
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

        // Address info modal functions
        function viewAddressInfo(billTo, billToAttn, shipTo, shipToAttn) {
            // Bill To information
            document.getElementById("modalBillTo").textContent = billTo || 'N/A';
            document.getElementById("noBillingInfo").style.display = (!billTo && !billToAttn) ? "block" : "none";
            
            // Bill To Attention with conditional display
            if (billToAttn) {
                document.getElementById("modalBillToAttn").textContent = billToAttn;
                document.getElementById("billToAttnRow").style.display = "table-row";
            } else {
                document.getElementById("billToAttnRow").style.display = "none";
            }
            
            // Ship To information
            document.getElementById("modalShipTo").textContent = shipTo || 'N/A';
            document.getElementById("noShippingInfo").style.display = (!shipTo && !shipToAttn) ? "block" : "none";
            
            // Ship To Attention with conditional display
            if (shipToAttn) {
                document.getElementById("modalShipToAttn").textContent = shipToAttn;
                document.getElementById("shipToAttnRow").style.display = "table-row";
            } else {
                document.getElementById("shipToAttnRow").style.display = "none";
            }
            
            document.getElementById("addressInfoModal").style.display = "block";
        }

        function closeAddressInfoModal() {
            document.getElementById("addressInfoModal").style.display = "none";
        }

        // Function to fetch client shipping information
function fetchClientInfo() {
    const username = document.getElementById('username').value;
    if (!username) return;
    
    // Show loading indicators
    document.getElementById('ship_to_display').textContent = 'Loading...';
    document.getElementById('ship_to_attn_display').textContent = 'Loading...';
    document.getElementById('bill_to_display').textContent = 'Loading...';
    document.getElementById('bill_to_attn_display').textContent = 'Loading...';
    
    // Make AJAX request to get client info
    $.ajax({
        url: '/backend/get_client_info.php', // Create this file
        type: 'POST',
        data: { username: username },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Update display fields
                document.getElementById('ship_to_display').textContent = response.ship_to || 'Not provided';
                document.getElementById('ship_to_attn_display').textContent = response.ship_to_attn || 'Not provided';
                document.getElementById('bill_to_display').textContent = response.bill_to || 'Not provided';
                document.getElementById('bill_to_attn_display').textContent = response.bill_to_attn || 'Not provided';
                
                // Show warning if shipping info is missing
                if (!response.ship_to) {
                    showToast('Warning: This client has no shipping address in their profile.', 'warning');
                }
            } else {
                // Display error
                document.getElementById('ship_to_display').textContent = 'Error loading data';
                document.getElementById('ship_to_attn_display').textContent = 'Error loading data';
                document.getElementById('bill_to_display').textContent = 'Error loading data';
                document.getElementById('bill_to_attn_display').textContent = 'Error loading data';
                
                showToast('Failed to load client information: ' + response.message, 'error');
            }
        },
        error: function() {
            // Handle AJAX error
            document.getElementById('ship_to_display').textContent = 'Error loading data';
            document.getElementById('ship_to_attn_display').textContent = 'Error loading data';
            document.getElementById('bill_to_display').textContent = 'Error loading data';
            document.getElementById('bill_to_attn_display').textContent = 'Error loading data';
            
            showToast('Failed to connect to server. Please try again.', 'error');
        }
    });
    
    // Also update company info
    updateCompany();
}

// Update prepare order data function to not include delivery_address
function prepareOrderData() {
    // Get cart data
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    
    // Set orders JSON
    document.getElementById('orders').value = JSON.stringify(cart);
    
    // Calculate and set total amount
    let total = 0;
    cart.forEach(item => {
        total += parseFloat(item.price) * parseInt(item.quantity);
    });
    document.getElementById('total_amount').value = total.toFixed(2);
    
    return true;
}