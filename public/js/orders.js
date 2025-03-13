$(document).ready(function() {
    // Function to open the Add Order Form overlay
    function openAddOrderForm() {
        $('#addOrderOverlay').show();
        $('#order_date').val(new Date().toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true }));
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
                            <td><input type="number" min="0" data-price="${price}" class="product-quantity" placeholder="Quantity"></td>
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
        dateFormat: 'M d, yy'
    });

    // Bind functions to global scope
    window.openAddOrderForm = openAddOrderForm;
    window.closeAddOrderForm = closeAddOrderForm;
    window.openInventoryOverlay = openInventoryOverlay;
    window.closeInventoryOverlay = closeInventoryOverlay;
});