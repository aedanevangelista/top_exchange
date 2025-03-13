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

            const categories = {};
            data.forEach(product => {
                if (!categories[product.category]) {
                    categories[product.category] = [];
                }
                categories[product.category].push(product);
            });

            for (const category in categories) {
                categories[category].forEach(product => {
                    const price = parseFloat(product.price);
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
                existingProduct.quantity += quantity; // Accumulate quantity if product already exists
            } else {
                selectedProducts.push({ category, item_description: description, packaging, price, quantity });
            }
        }

        row.find('.product-quantity').val('');
        populateCart();
        updateOrderSummary();
    });

    // Function to prepare order data for submission
    function prepareOrderData() {
        const orderData = JSON.stringify(selectedProducts);
        console.log("Order data being sent: ", orderData); // Log the JSON data for debugging
        $('#orders').val(orderData);
        const totalAmount = selectedProducts.reduce((total, product) => total + product.price * product.quantity, 0);
        $('#total_amount').val(totalAmount.toFixed(2));
    }

    $('#submitOrderForm').on('submit', function(e) {
        e.preventDefault();
        const orderData = $('#orders').val();
        if (orderData && selectedProducts.length > 0) {
            $.post('/top_exchange/backend/add_order.php', $(this).serialize(), function(response) {
                if (response.success) {
                    alert('Order submitted successfully!');
                    selectedProducts = [];
                    populateCart();
                    updateOrderSummary();
                } else {
                    alert('Failed to submit order. Please try again.');
                }
            }, 'json');
        } else {
            alert('Your cart is empty. Please add products before submitting.');
        }
    });
});
