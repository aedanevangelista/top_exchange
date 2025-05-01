<?php
// Include the header
require_once 'header.php';

// Database connection - we need to reconnect because header.php might have closed the connection
require_once 'db_connection.php';

// Fetch all products from the database
$products = [];
$query = "SELECT * FROM products ORDER BY category, item_description";

// Add error handling for the query
try {
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $products[$row['category']][] = $row;
        }
    } else {
        // Log the error or display a message for debugging
        error_log("No products found or query failed: " . $conn->error);
    }
} catch (Exception $e) {
    // Log the exception
    error_log("Exception in ordering.php: " . $e->getMessage());
}

// Close the connection after we're done with it
$conn->close();

// Make sure we have at least an empty array to avoid errors
if (empty($products)) {
    $products = [];
}

// Debug information - set to true to enable debugging
$debug_mode = false;
if ($debug_mode) {
    echo '<div class="alert alert-warning" style="margin-top: 20px;">';
    echo '<h4>Debug Information</h4>';
    echo '<p>This is only visible when debug mode is enabled.</p>';
    echo '<hr>';
    echo '<h5>Session Data:</h5>';
    echo '<pre>'; print_r($_SESSION); echo '</pre>';
    echo '<h5>Products Data:</h5>';
    echo '<pre>'; print_r($products); echo '</pre>';
    echo '<h5>Database Connection:</h5>';
    echo 'Connection Status: ' . ($conn->connect_errno ? 'Error: ' . $conn->connect_error : 'Connected');
    echo '</div>';
}
?>

<!-- Add jQuery and Bootstrap JS at the top -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>

<style>
    /* Reset some styles that might conflict with the header */
    body {
        padding-top: 0;
    }

    /* Main content styling */
    .ordering-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        background-color: #fff;
    }

    .page-title {
        color: #9a7432;
        margin-bottom: 30px;
        font-weight: 700;
        text-align: center;
        font-size: 2.5rem;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }

    /* Category sections */
    .category-section {
        margin-bottom: 40px;
    }

    .category-title {
        background-color: #9a7432;
        color: white;
        padding: 10px 15px;
        border-radius: 5px;
        font-size: 1.5rem;
        margin-bottom: 20px;
        position: relative;
    }

    /* Product cards */
    .product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
    }

    .product-card {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    }

    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.15);
    }

    .product-image-container {
        height: 200px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #f9f9f9;
    }

    .product-image {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        padding: 10px;
    }

    .product-info {
        padding: 15px;
    }

    .product-name {
        font-weight: 600;
        color: #333;
        margin-bottom: 5px;
        font-size: 1.1rem;
    }

    .product-packaging {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 10px;
    }

    .product-price {
        font-weight: 700;
        color: #9a7432;
        font-size: 1.2rem;
        margin-bottom: 15px;
    }

    .add-to-cart-btn {
        background-color: #9a7432;
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 4px;
        cursor: pointer;
        width: 100%;
        transition: background-color 0.3s;
        font-weight: 500;
    }

    .add-to-cart-btn:hover {
        background-color: #b08a3e;
    }

    /* Login indicator for add to cart button */
    .add-to-cart-btn.login-required {
        background-color: #6c757d;
    }

    .add-to-cart-btn.login-required:hover {
        background-color: #5a6268;
    }

    /* Quantity controls */
    .quantity-controls {
        display: flex;
        margin-bottom: 15px;
        align-items: center;
    }

    .quantity-btn {
        width: 30px;
        height: 30px;
        background-color: #f0f0f0;
        border: none;
        font-size: 1rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .quantity-input {
        width: 50px;
        height: 30px;
        text-align: center;
        border: 1px solid #ddd;
        margin: 0 5px;
        -moz-appearance: textfield;
    }

    .quantity-input::-webkit-outer-spin-button,
    .quantity-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    /* Search and filter section */
    .search-filter-section {
        margin-bottom: 30px;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: center;
    }

    .search-box {
        flex: 1;
        min-width: 250px;
        position: relative;
    }

    .search-box input {
        width: 100%;
        padding: 10px 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding-left: 40px;
    }

    .search-box i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #777;
    }

    .clear-search {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #aaa;
        cursor: pointer;
        padding: 0;
        font-size: 14px;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }

    .clear-search:hover {
        color: #9a7432;
        background-color: rgba(154, 116, 50, 0.1);
    }

    .category-filter {
        min-width: 200px;
    }

    .category-filter select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .product-grid {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        }

        .page-title {
            font-size: 2rem;
        }
    }

    @media (max-width: 576px) {
        .product-grid {
            grid-template-columns: 1fr;
        }

        .search-filter-section {
            flex-direction: column;
        }

        .search-box, .category-filter {
            width: 100%;
        }
    }
</style>

<div class="ordering-container">
    <h1 class="page-title">Our Products</h1>

    <?php if (!isset($_SESSION['username'])): ?>
    <div class="alert alert-info" role="alert" style="margin-bottom: 20px;">
        <i class="fas fa-info-circle"></i> Please <a href="/LandingPage/login.php" class="alert-link">login</a> to add products to your cart.
    </div>
    <?php endif; ?>

    <div class="search-filter-section">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search products...">
            <button type="button" id="clearSearch" class="clear-search" style="display: none;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="category-filter">
            <select id="categoryFilter">
                <option value="">All Categories</option>
                <?php foreach (array_keys($products) as $category): ?>
                    <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="button" id="clearFilters" class="btn btn-sm btn-outline-secondary ml-2" style="display: none;">
            <i class="fas fa-times-circle"></i> Clear Filters
        </button>
    </div>

    <div id="active-filters" class="mb-3" style="display: none;">
        <span class="badge badge-pill badge-light mr-2" id="search-filter-badge" style="display: none;">
            <i class="fas fa-search"></i> <span id="search-term-display"></span>
            <button type="button" class="close ml-1" aria-label="Close" onclick="$('#searchInput').val('').trigger('input');">
                <span aria-hidden="true">&times;</span>
            </button>
        </span>
        <span class="badge badge-pill badge-light" id="category-filter-badge" style="display: none;">
            <i class="fas fa-tag"></i> <span id="category-display"></span>
            <button type="button" class="close ml-1" aria-label="Close" onclick="$('#categoryFilter').val('').trigger('change');">
                <span aria-hidden="true">&times;</span>
            </button>
        </span>
    </div>

    <?php if (empty($products)): ?>
        <div class="alert alert-info text-center my-5">
            <i class="fas fa-info-circle fa-2x mb-3"></i>
            <h4>No products available</h4>
            <p>We're currently updating our product catalog. Please check back later.</p>
            <p class="mt-3">
                <a href="/LandingPage/index.php" class="btn btn-primary">Return to Home Page</a>
                <button onclick="window.location.reload();" class="btn btn-outline-secondary ml-2">Refresh Page</button>
            </p>
        </div>
    <?php else: ?>
        <?php foreach ($products as $category => $categoryProducts): ?>
            <div class="category-section" data-category="<?php echo htmlspecialchars($category); ?>">
                <h2 class="category-title"><?php echo htmlspecialchars($category); ?></h2>
                <div class="product-grid">
                    <?php foreach ($categoryProducts as $product): ?>
                        <div class="product-card" data-name="<?php echo htmlspecialchars(strtolower($product['item_description'])); ?>" data-category="<?php echo htmlspecialchars(strtolower($category)); ?>">
                            <div class="product-image-container">
                                <img src="<?php echo htmlspecialchars($product['product_image'] ?: '/LandingPage/images/default-product.jpg'); ?>"
                                     alt="<?php echo htmlspecialchars($product['item_description']); ?>"
                                     class="product-image">
                            </div>
                            <div class="product-info">
                                <h3 class="product-name"><?php echo htmlspecialchars($product['item_description']); ?></h3>
                                <p class="product-packaging"><?php echo htmlspecialchars($product['packaging']); ?></p>
                                <p class="product-price">₱<?php echo number_format($product['price'], 2); ?></p>

                                <div class="quantity-controls">
                                    <button class="quantity-btn decrease" data-product-id="<?php echo $product['product_id']; ?>">-</button>
                                    <input type="number" class="quantity-input" value="1" min="1" max="100"
                                           data-product-id="<?php echo $product['product_id']; ?>">
                                    <button class="quantity-btn increase" data-product-id="<?php echo $product['product_id']; ?>">+</button>
                                </div>

                                <button class="add-to-cart-btn"
                                        data-product-id="<?php echo $product['product_id']; ?>"
                                        data-product-name="<?php echo htmlspecialchars($product['item_description']); ?>"
                                        data-product-price="<?php echo $product['price']; ?>"
                                        data-product-image="<?php echo htmlspecialchars($product['product_image'] ?: '/LandingPage/images/default-product.jpg'); ?>"
                                        data-product-packaging="<?php echo htmlspecialchars($product['packaging']); ?>"
                                        data-product-category="<?php echo htmlspecialchars($category); ?>">
                                    <?php echo isset($_SESSION['username']) ? 'Add to Cart' : 'Login to Add to Cart'; ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    // Check if user is logged in
    const isLoggedIn = <?php echo isset($_SESSION['username']) ? 'true' : 'false'; ?>;

    // Apply login-required class to buttons if user is not logged in
    if (!isLoggedIn) {
        $('.add-to-cart-btn').addClass('login-required');
    }

    // Initialize search and filter state
    let currentSearchTerm = '';
    let currentCategory = '';

    // Function to update the clear search button visibility
    function updateClearSearchButton() {
        if ($('#searchInput').val().trim() !== '') {
            $('#clearSearch').show();
        } else {
            $('#clearSearch').hide();
        }
    }

    // Clear search button functionality
    $('#clearSearch').on('click', function() {
        $('#searchInput').val('');
        $('#searchInput').trigger('input');
        $(this).hide();
    });

    // Update clear button on input
    $('#searchInput').on('input', function() {
        updateClearSearchButton();
    });

    // Function to update filter badges and clear filters button
    function updateFilterDisplay() {
        const searchTerm = $('#searchInput').val().trim();
        const selectedCategory = $('#categoryFilter').val();

        // Update search filter badge
        if (searchTerm !== '') {
            $('#search-term-display').text(searchTerm);
            $('#search-filter-badge').show();
        } else {
            $('#search-filter-badge').hide();
        }

        // Update category filter badge
        if (selectedCategory !== '') {
            $('#category-display').text(selectedCategory);
            $('#category-filter-badge').show();
        } else {
            $('#category-filter-badge').hide();
        }

        // Show/hide active filters container and clear filters button
        if (searchTerm !== '' || selectedCategory !== '') {
            $('#active-filters').show();
            $('#clearFilters').show();
        } else {
            $('#active-filters').hide();
            $('#clearFilters').hide();
        }
    }

    // Clear all filters button
    $('#clearFilters').on('click', function() {
        $('#searchInput').val('');
        $('#categoryFilter').val('');

        // Trigger events to update the UI
        $('#searchInput').trigger('input');
        $('#categoryFilter').trigger('change');

        // Hide filter badges and clear button
        $('#active-filters').hide();
        $(this).hide();

        showPopup('All filters cleared');
    });

    // Search functionality
    $('#searchInput').on('input', function() {
        const searchTerm = $(this).val().toLowerCase().trim();
        currentSearchTerm = searchTerm;

        // Update filter display
        updateFilterDisplay();

        // If search is empty, show all products (or respect category filter)
        if (searchTerm === '') {
            // Get the current category filter value
            const selectedCategory = $('#categoryFilter').val();

            if (selectedCategory === '') {
                // If no category is selected, show all products
                $('.product-card').show();
                $('.category-section').show();
            } else {
                // If a category is selected, only show products from that category
                $('.category-section').hide();
                $(`.category-section[data-category="${selectedCategory}"]`).show();

                $('.product-card').hide();
                $(`.product-card[data-category="${selectedCategory.toLowerCase()}"]`).show();
            }
            return;
        }

        // Filter products based on search term
        $('.product-card').each(function() {
            const productName = $(this).data('name') || '';
            const productCategory = $(this).data('category') || '';
            const productInfo = $(this).find('.product-name').text().toLowerCase() + ' ' +
                               $(this).find('.product-packaging').text().toLowerCase();

            // Check if product name, category, or any product info contains the search term
            if (productName.includes(searchTerm) ||
                productCategory.includes(searchTerm) ||
                productInfo.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });

        // Show/hide category sections based on visible products
        $('.category-section').each(function() {
            const category = $(this);
            const visibleProducts = category.find('.product-card:visible').length;

            if (visibleProducts > 0) {
                category.show();
            } else {
                category.hide();
            }
        });

        // Show a message if no products match the search
        if ($('.product-card:visible').length === 0) {
            if ($('#no-results-message').length === 0) {
                $('.ordering-container').append(
                    '<div id="no-results-message" class="alert alert-info text-center my-4">' +
                    '<i class="fas fa-search mr-2"></i> No products found matching "' + searchTerm + '"</div>'
                );
            } else {
                $('#no-results-message').html(
                    '<i class="fas fa-search mr-2"></i> No products found matching "' + searchTerm + '"'
                ).show();
            }
        } else {
            $('#no-results-message').hide();
        }
    });

    // Category filter functionality
    $('#categoryFilter').on('change', function() {
        const selectedCategory = $(this).val();
        currentCategory = selectedCategory;

        // Clear any existing search
        $('#searchInput').val('');
        $('#no-results-message').hide();

        // Update filter display
        updateFilterDisplay();

        if (selectedCategory === '') {
            // Show all categories and products
            $('.category-section').show();
            $('.product-card').show();
        } else {
            // Hide all categories first
            $('.category-section').hide();
            // Show only the selected category
            $(`.category-section[data-category="${selectedCategory}"]`).show();

            // Hide all products first
            $('.product-card').hide();
            // Show only products in the selected category
            $(`.product-card[data-category="${selectedCategory.toLowerCase()}"]`).show();
        }

        // Show a message if the category is empty
        if (selectedCategory !== '' && $(`.product-card[data-category="${selectedCategory.toLowerCase()}"]:visible`).length === 0) {
            if ($('#no-results-message').length === 0) {
                $('.ordering-container').append(
                    '<div id="no-results-message" class="alert alert-info text-center my-4">' +
                    '<i class="fas fa-info-circle mr-2"></i> No products found in the "' + selectedCategory + '" category</div>'
                );
            } else {
                $('#no-results-message').html(
                    '<i class="fas fa-info-circle mr-2"></i> No products found in the "' + selectedCategory + '" category'
                ).show();
            }
        }

        // Show a success message when filter is applied
        if (selectedCategory !== '') {
            showPopup(`Showing products in "${selectedCategory}" category`);
        } else {
            showPopup('Showing all products');
        }

        // Update clear search button visibility
        updateClearSearchButton();
    });

    // Quantity controls - using event delegation for better reliability
    $(document).on('click', '.quantity-btn', function() {
        console.log('Quantity button clicked:', this);

        // Find the quantity input within the same quantity-controls div
        const quantityControls = $(this).closest('.quantity-controls');
        const input = quantityControls.find('.quantity-input');

        console.log('Found input:', input);

        let quantity = parseInt(input.val()) || 1;

        if ($(this).hasClass('decrease')) {
            if (quantity > 1) {
                quantity--;
                input.val(quantity);
                console.log('Decreased quantity to: ' + quantity);
            }
        } else if ($(this).hasClass('increase')) {
            if (quantity < 100) {
                quantity++;
                input.val(quantity);
                console.log('Increased quantity to: ' + quantity);
            }
        }
    });

    // Quantity input validation - using event delegation
    $(document).on('change', '.quantity-input', function() {
        let quantity = parseInt($(this).val());

        if (isNaN(quantity) || quantity < 1) {
            $(this).val(1);
        } else if (quantity > 100) {
            $(this).val(100);
        }

        console.log('Quantity changed to: ' + $(this).val());
    });

    // Add to cart functionality - using event delegation for better reliability
    $(document).on('click', '.add-to-cart-btn', function() {
        console.log('Add to cart button clicked');

        // Check if user is logged in
        if (!isLoggedIn) {
            // Show login prompt
            showPopup('Please login to add items to cart', true);

            // Redirect to login page after a short delay
            setTimeout(function() {
                window.location.href = '/LandingPage/login.php';
            }, 2000);

            return;
        }

        const button = $(this);
        const productId = button.data('product-id');
        const productName = button.data('product-name');
        const productPrice = button.data('product-price');
        const productImage = button.data('product-image');
        const productPackaging = button.data('product-packaging');
        const productCategory = button.data('product-category');

        // Find the quantity input - need to look in parent container first
        const productCard = button.closest('.product-info');
        const quantityControls = productCard.find('.quantity-controls');
        const quantityInput = quantityControls.find('.quantity-input');

        console.log('Product card:', productCard);
        console.log('Quantity controls:', quantityControls);
        console.log('Quantity input:', quantityInput);

        // Default to 1 if we can't find the input or parse the value
        let quantity = 1;
        if (quantityInput.length > 0) {
            quantity = parseInt(quantityInput.val()) || 1;
        }

        console.log('Adding to cart:', {
            product_id: productId,
            product_name: productName,
            price: productPrice,
            quantity: quantity
        });

        // Disable the button to prevent multiple clicks
        button.prop('disabled', true).text('Adding...');

        // Show a temporary message
        showPopup('Adding to cart...');

        // Log the data being sent
        console.log('AJAX request data:', {
            url: '/LandingPage/add_to_cart.php',
            type: 'POST',
            data: {
                product_id: productId,
                product_name: productName,
                price: productPrice,
                image_path: productImage,
                packaging: productPackaging,
                category: productCategory,
                quantity: quantity
            }
        });

        $.ajax({
            url: '/LandingPage/add_to_cart.php',
            type: 'POST',
            data: {
                product_id: productId,
                product_name: productName,
                price: productPrice,
                image_path: productImage,
                packaging: productPackaging,
                category: productCategory,
                quantity: quantity
            },
            success: function(response) {
                console.log('Response received:', response);

                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        // Update cart count
                        $('#cart-count').text(result.cart_count);

                        // Show success message
                        showPopup('Item added to cart');

                        // Reset quantity to 1
                        quantityInput.val(1);
                    } else {
                        showPopup(result.message || 'Error adding to cart', true);
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    console.error('Raw response:', response);
                    showPopup('Error processing response from server', true);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.error('Response text:', xhr.responseText);
                showPopup('Error adding to cart: ' + error, true);
            },
            complete: function() {
                // Re-enable the button
                button.prop('disabled', false).text(isLoggedIn ? 'Add to Cart' : 'Login to Add to Cart');
            }
        });
    });

    // Use the global showPopup function defined at the bottom of the page
});
</script>

<?php
// Include the footer if you have one
// require_once 'footer.php';
?>

<!-- jQuery and Bootstrap scripts are now loaded in the head section -->

<!-- Initialize Bootstrap components -->
<script>
    // Initialize Bootstrap components when the page is fully loaded
    $(document).ready(function() {
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();

        // Initialize popovers
        $('[data-toggle="popover"]').popover();

        // Initialize modals
        $('.modal').modal({
            show: false
        });

        // Fix for cart modal
        $(document).on('click', '.cart-button', function(e) {
            e.preventDefault();
            console.log('Cart button clicked');
            $('#cartModal').modal('show');
        });

        // Cart functionality for the modal
        $(document).on('click', '.increase-quantity', function() {
            const productId = $(this).data('product-id');
            updateCartItemQuantity(productId, 1);
        });

        $(document).on('click', '.decrease-quantity', function() {
            const productId = $(this).data('product-id');
            updateCartItemQuantity(productId, -1);
        });

        $(document).on('click', '.remove-from-cart', function() {
            const productId = $(this).data('product-id');
            removeCartItem(productId);
        });

        $(document).on('click', '#checkout-button', function() {
            const specialInstructions = $('#special-instructions').val();
            sessionStorage.setItem('specialInstructions', specialInstructions);
            window.location.href = '/LandingPage/checkout.php';
        });

        // Function to update cart item quantity
        function updateCartItemQuantity(productId, change) {
            console.log('Updating cart item quantity:', productId, change);

            $.ajax({
                url: '/LandingPage/update_cart_item.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    product_id: productId,
                    quantity_change: change
                },
                success: function(response) {
                    console.log('Update response:', response);
                    if(response.success) {
                        $('#cart-count').text(response.cart_count);
                        updateCartModal();
                        showPopup("Cart updated successfully");
                    } else {
                        showPopup(response.message || "Error updating quantity", true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error updating cart item:", error);
                    showPopup("Error updating cart item.", true);
                }
            });
        }

        // Function to remove cart item
        function removeCartItem(productId) {
            console.log('Removing cart item:', productId);

            $.ajax({
                url: '/LandingPage/remove_cart_item.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    product_id: productId
                },
                success: function(response) {
                    console.log('Remove response:', response);
                    if(response.success) {
                        $('#cart-count').text(response.cart_count);
                        updateCartModal();
                        showPopup("Item removed from cart");
                    } else {
                        showPopup(response.message || "Error removing item", true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error removing cart item:", error);
                    showPopup("Error removing cart item.", true);
                }
            });
        }

        // Function to update the cart modal
        function updateCartModal() {
            console.log('Updating cart modal');

            $.ajax({
                url: '/LandingPage/fetch_cart_items.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Cart items response:', response);
                    if(response && response.cart_items !== undefined) {
                        if(response.cart_items.length === 0) {
                            $('#empty-cart-message').show();
                            $('#cart-items-container').hide();
                        } else {
                            $('#empty-cart-message').hide();
                            $('#cart-items-container').show();

                            let cartItemsHtml = '';
                            let subtotal = 0;

                            response.cart_items.forEach(item => {
                                const price = parseFloat(item.price);
                                const itemSubtotal = price * item.quantity;
                                subtotal += itemSubtotal;

                                cartItemsHtml += `
                                    <tr>
                                        <td>
                                            <img src="${item.image_path || '/LandingPage/images/default-product.jpg'}"
                                                 alt="${item.name}"
                                                 style="width: 80px; height: 80px; object-fit: contain;">
                                        </td>
                                        <td>
                                            <h6>${item.name}</h6>
                                            <small class="text-muted">${item.packaging || ''}</small>
                                        </td>
                                        <td>₱${price.toFixed(2)}</td>
                                        <td>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <button class="btn btn-outline-secondary decrease-quantity"
                                                            type="button"
                                                            data-product-id="${item.product_id}">
                                                        <i class="fa fa-minus"></i>
                                                    </button>
                                                </div>
                                                <input type="text"
                                                       class="form-control text-center quantity-input"
                                                       value="${item.quantity}"
                                                       readonly>
                                                <div class="input-group-append">
                                                    <button class="btn btn-outline-secondary increase-quantity"
                                                            type="button"
                                                            data-product-id="${item.product_id}">
                                                        <i class="fa fa-plus"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                        <td>₱${itemSubtotal.toFixed(2)}</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-danger remove-from-cart"
                                                    data-product-id="${item.product_id}">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                `;
                            });

                            $('#cart-items-list').html(cartItemsHtml);
                            $('#subtotal-amount').text('₱' + subtotal.toFixed(2));

                            // Calculate delivery fee
                            const deliveryFee = subtotal > 500 ? 0 : 50;
                            $('#delivery-fee').text('₱' + deliveryFee.toFixed(2));

                            const totalAmount = subtotal + deliveryFee;
                            $('#total-amount').text('₱' + totalAmount.toFixed(2));
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error fetching cart items:", error);
                    showPopup("Error fetching cart items.", true);
                }
            });
        }

        // Update cart modal when it's shown
        $('#cartModal').on('show.bs.modal', function() {
            updateCartModal();
        });
    });

    // Function to show popup messages (global scope)
    function showPopup(message, isError = false) {
        const popup = $('#customPopup');
        const popupMessage = $('#popupMessage');

        popupMessage.text(message);
        popup.removeClass('error');

        if (isError) {
            popup.addClass('error');
        }

        popup.css('display', 'block');

        // Hide after 3 seconds
        setTimeout(function() {
            popup.css('display', 'none');
        }, 3000);
    }
</script>"