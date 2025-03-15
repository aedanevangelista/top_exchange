$(document).ready(function() {
    let selectedProducts = [];

    // Function to open the Add Order Form overlay
    function openAddOrderForm() {
        $('#addOrderOverlay').show();
        $('#order_date').val(new Date().toLocaleDateString('en-CA')); // YYYY-MM-DD format
    }

    // Function to close the Add Order Form overlay
    function closeAddOrderForm() {
        $('#addOrderOverlay').hide();
    }

    // Function to open the Inventory Overlay
    function openInventoryOverlay() {
        $('#inventoryOverlay').show();
        fetchCategories();
        fetchInventory();
    }

    // Function to close the Inventory Overlay
    function closeInventoryOverlay() {
        $('#inventoryOverlay').hide();
    }

    // Function to open the Cart Modal
    function openCartModal() {
        $('#cartModal').show();
        populateCart();
    }

    // Function to close the Cart Modal
    function closeCartModal() {
        $('#cartModal').hide();
    }

    // Function to open the Order Details Modal
    function viewOrderDetails(orders) {
        const orderDetails = JSON.parse(orders);
        const orderDetailsBody = $('#orderDetailsBody');
        orderDetailsBody.empty();
        orderDetails.forEach(product => {
            const productElement = `
                <tr>
                    <td>${product.category}</td>
                    <td>${product.item_description}</td>
                    <td>${product.packaging}</td>
                    <td>PHP ${product.price.toFixed(2)}</td>
                    <td>${product.quantity}</td>
                </tr>
            `;
            orderDetailsBody.append(productElement);
        });
        $('#orderDetailsModal').show();
    }

    // Function to close the Order Details Modal
    function closeOrderDetailsModal() {
        $('#orderDetailsModal').hide();
    }

    // Fetch inventory and populate the inventory table
    function fetchInventory() {
        $.getJSON('/top_exchange/backend/fetch_inventory.php', function(data) {
            const inventory = $('.inventory');
            inventory.empty();

            // Group products by category
            const categories = {};
            data.forEach(product => {
                if (!categories[product.category]) {
                    categories[product.category] = [];
                }
                categories[product.category].push(product);
            });

            // Populate inventory with categories and products
            for (const category in categories) {
                categories[category].forEach(product => {
                    const price = parseFloat(product.price); // Ensure price is a number
                    const productElement = `
                        <tr>
                            <td>${product.category}</td>
                            <td>${product.item_description}</td>
                            <td>${product.packaging}</td>
                            <td>PHP ${!isNaN(price) ? price.toFixed(2) : 'NaN'}</td>
                            <td><input type="number" min="1" data-category="${product.category}" data-description="${product.item_description}" data-packaging="${product.packaging}" data-price="${price}" class="product-quantity" placeholder="Quantity" oninput="this.value = Math.abs(this.value)"></td>
                            <td><button class="add-to-cart-btn">Add</button></td>
                        </tr>
                    `;
                    inventory.append(productElement);
                });
            }
        });
    }

    // Fetch categories and populate the dropdown
    function fetchCategories() {
        $.getJSON('/top_exchange/backend/fetch_categories.php', function(data) {
            const categoryFilter = $('#inventoryFilter');
            categoryFilter.empty();
            categoryFilter.append('<option value="all">All Categories</option>');
            data.forEach(category => {
                categoryFilter.append(`<option value="${category}">${category}</option>`);
            });
        });
    }

    // Populate the cart with selected products
    function populateCart() {
        const cart = $('.cart');
        cart.empty();
        let totalAmount = 0;
        if (selectedProducts.length > 0) {
            selectedProducts.forEach(product => {
                const productElement = `
                    <tr>
                        <td>${product.category}</td>
                        <td>${product.item_description}</td>
                        <td>${product.packaging}</td>
                        <td>PHP ${product.price.toFixed(2)}</td>
                        <td><input type="number" min="1" value="${product.quantity}" class="cart-quantity" oninput="this.value = Math.abs(this.value)"></td>
                    </tr>
                `;
                cart.append(productElement);
                totalAmount += product.price * product.quantity;
            });
            $('.no-products').hide();
        } else {
            $('.no-products').show();
        }
        $('.total-amount').text(`PHP ${totalAmount.toFixed(2)}`);
    }

    // Update order summary
    function updateOrderSummary() {
        const summaryBody = $('#summaryBody');
        summaryBody.empty();
        let totalAmount = 0;
        selectedProducts.forEach(product => {
            const productElement = `
                <tr>
                    <td>${product.category}</td>
                    <td>${product.item_description}</td>
                    <td>${product.packaging}</td>
                    <td>PHP ${product.price.toFixed(2)}</td>
                    <td>${product.quantity}</td>
                </tr>
            `;
            summaryBody.append(productElement);
            totalAmount += product.price * product.quantity;
        });
        $('.summary-total-amount').text(`PHP ${totalAmount.toFixed(2)}`);
    }

    // Filter and search functionality
    $('#inventorySearch').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        $('.inventory tr').each(function() {
            const itemDescription = $(this).find('td:eq(1)').text().toLowerCase();
            $(this).toggle(itemDescription.includes(searchTerm));
        });
    });

    $('#inventoryFilter').change(function() {
        const selectedCategory = $(this).val();
        if (selectedCategory === 'all') {
            $('.inventory tr').show();
        } else {
            $('.inventory tr').hide();
            $(`.inventory tr:contains(${selectedCategory})`).show();
        }
    });

    // Datepicker for delivery date with MWF restriction
    $('#delivery_date').datepicker({
        beforeShowDay: function(date) {
            const day = date.getDay();
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Set time to midnight to compare only dates
            return [(date > today && (day === 1 || day === 3 || day === 5)), ''];
        },
        minDate: 1,
        dateFormat: 'yy-mm-dd', // YYYY-MM-DD format
        onSelect: function(dateText, inst) {
            $(this).val(dateText); // Ensure only date selection is allowed
        }
    });

    // Add product to cart
    $('.inventory').on('click', '.add-to-cart-btn', function() {
        const row = $(this).closest('tr');
        const category = row.find('.product-quantity').data('category');
        const description = row.find('.product-quantity').data('description');
        const packaging = row.find('.product-quantity').data('packaging');
        const price = row.find('.product-quantity').data('price');
        const quantity = parseInt(row.find('.product-quantity').val(), 10) || 0;

        if (quantity > 0) {
            const existingProduct = selectedProducts.find(product => product.item_description === description);
            if (existingProduct) {
                existingProduct.quantity += quantity;
            } else {
                selectedProducts.push({ category, item_description: description, packaging, price, quantity });
            }
        }

        row.find('.product-quantity').val('');
        populateCart();
        updateOrderSummary();
    });

    // Save changes in the cart
    function saveCartChanges() {
        selectedProducts.forEach((product, index) => {
            product.quantity = parseInt($(`.cart tr:eq(${index}) .cart-quantity`).val(), 10) || 0;
        });
        closeCartModal();
        openInventoryOverlay();
        updateOrderSummary();
    }

    // Generate PO number
    function generatePONumber() {
        const username = $('#username').val();
        if (username) {
            $.post('/top_exchange/backend/generatePONumber.php', { username: username }, function(data) {
                $('#po_number').val(data);
            });
        }
    }

    // Bind functions to global scope
    window.openAddOrderForm = openAddOrderForm;
    window.closeAddOrderForm = closeAddOrderForm;
    window.openInventoryOverlay = openInventoryOverlay;
    window.closeInventoryOverlay = closeInventoryOverlay;
    window.openCartModal = openCartModal;
    window.closeCartModal = closeCartModal;
    window.saveCartChanges = saveCartChanges;
    window.prepareOrderData = prepareOrderData;
    window.generatePONumber = generatePONumber;
    window.viewOrderDetails = viewOrderDetails;
    window.closeOrderDetailsModal = closeOrderDetailsModal;
});

// Function to prepare order data for submission
function prepareOrderData() {
    const orderData = JSON.stringify(selectedProducts);
    console.log("Order data being sent: ", orderData); // Log the JSON data for debugging
    $('#orders').val(orderData);
    const totalAmount = selectedProducts.reduce((total, product) => total + product.price * product.quantity, 0);
    $('#total_amount').val(totalAmount.toFixed(2));
}