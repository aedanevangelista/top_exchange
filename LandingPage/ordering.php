<?php
// Include the header
require_once 'header.php';

// Clear any leftover order session variables to prevent checkout issues
if (isset($_SESSION['new_order']) || isset($_SESSION['order_id']) || isset($_SESSION['redirect'])) {
    // Clear these variables as we're starting a new ordering process
    $_SESSION['new_order'] = false;
    unset($_SESSION['order_id']);
    unset($_SESSION['redirect']);
    error_log("Cleared leftover order session variables in ordering.php");
}

// Database connection - we need to reconnect because header.php might have closed the connection
require_once 'db_connection.php';

// Fetch all products from the database
$products = [];
$groupedProducts = []; // For storing products grouped by name
$query = "SELECT * FROM products ORDER BY category, item_description";

// Add error handling for the query
try {
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Store in regular category-based array for backward compatibility
            $products[$row['category']][] = $row;

            // Also group by product name for variant handling
            $productName = !empty($row['product_name']) ? $row['product_name'] : preg_replace('/\s*\([A-Z][^)]*\)$/', '', $row['item_description']);

            if (!isset($groupedProducts[$row['category']][$productName])) {
                $groupedProducts[$row['category']][$productName] = [
                    'main_product' => $row,
                    'variants' => []
                ];
            } else {
                // If this isn't the first product with this name, add it as a variant
                $groupedProducts[$row['category']][$productName]['variants'][] = $row;
            }
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

<!-- jQuery and Bootstrap JS are loaded in header.php and footer.php -->

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
        margin-bottom: 10px;
        font-size: 1.1rem;
    }

    .product-description {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 10px;
        line-height: 1.4;
    }

    .product-description p {
        margin: 0;
    }

    .view-details-hint {
        color: #9a7432;
        font-size: 0.85rem;
        margin-top: 10px;
        text-align: center;
        padding: 5px;
        background-color: #f9f9f9;
        border-radius: 4px;
        transition: all 0.2s ease;
    }

    .product-card:hover .view-details-hint {
        background-color: #9a7432;
        color: white;
    }

    /* Product packaging styling moved to the variant section */

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

    /* Variant selector styling */
    .variant-selector {
        margin-bottom: 10px;
    }

    .variant-label {
        display: block;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 4px;
        color: #555;
    }

    .variant-dropdown {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background-color: #f9f9f9;
        font-size: 0.9rem;
        color: #333;
    }

    .variant-dropdown:focus {
        border-color: #9a7432;
        outline: none;
        box-shadow: 0 0 0 2px rgba(154, 116, 50, 0.2);
    }

    /* Packaging styling */
    .packaging-container {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }

    .packaging-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #555;
        margin-right: 5px;
    }

    .product-packaging {
        margin: 0;
        padding: 3px 8px;
        background-color: #f5f5f5;
        border-radius: 4px;
        display: inline-block;
        border-left: 3px solid #9a7432;
        font-size: 0.85rem;
        color: #666;
    }

    /* Product Modal Styling */
    .product-modal-image-container {
        height: 300px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #f9f9f9;
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 15px;
    }

    .product-modal-image-container img {
        max-height: 100%;
        max-width: 100%;
        object-fit: contain;
    }

    #modal-product-name {
        color: #333;
        font-weight: 700;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 10px;
    }

    #modal-product-price {
        font-size: 1.3rem;
        color: #9a7432 !important;
    }

    #modal-product-packaging {
        background-color: #f5f5f5;
        color: #666;
        padding: 5px 10px;
        border-radius: 4px;
        border-left: 3px solid #9a7432;
    }

    #modal-product-description {
        background-color: #f9f9f9;
        padding: 10px;
        border-radius: 4px;
        font-size: 0.9rem;
        color: #555;
    }

    #modal-ingredients-container {
        background-color: #f9f9f9;
        padding: 15px;
        border-radius: 8px;
        margin-top: 20px;
    }

    #modal-ingredients-container h5 {
        color: #9a7432;
        border-bottom: 1px solid #e0e0e0;
        padding-bottom: 8px;
    }

    .ingredient-item {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
        padding: 5px;
        background-color: #fff;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .ingredient-name {
        font-weight: 600;
        color: #555;
    }

    .ingredient-amount {
        color: #777;
        font-size: 0.85rem;
        margin-left: 5px;
    }

    #modal-add-to-cart-btn {
        background-color: #9a7432;
        border-color: #9a7432;
        font-weight: 600;
        padding: 10px 15px;
        transition: all 0.3s ease;
    }

    #modal-add-to-cart-btn:hover {
        background-color: #8a6422;
        border-color: #8a6422;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    /* Make product cards clickable */
    .product-card {
        cursor: pointer;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.15);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .product-grid {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        }

        .page-title {
            font-size: 2rem;
        }

        .product-modal-image-container {
            height: 200px;
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

        .modal-dialog {
            margin: 0.5rem;
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

    <?php if (empty($groupedProducts)): ?>
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
        <?php foreach ($groupedProducts as $category => $categoryProducts): ?>
            <div class="category-section" data-category="<?php echo htmlspecialchars($category); ?>">
                <h2 class="category-title"><?php echo htmlspecialchars($category); ?></h2>
                <div class="product-grid">
                    <?php foreach ($categoryProducts as $productName => $productGroup): ?>
                        <?php
                            $mainProduct = $productGroup['main_product'];
                            $variants = $productGroup['variants'];
                            $hasVariants = !empty($variants);
                        ?>
                        <div class="product-card"
                             data-name="<?php echo htmlspecialchars(strtolower($productName)); ?>"
                             data-category="<?php echo htmlspecialchars(strtolower($category)); ?>">
                            <div class="product-image-container">
                                <img src="<?php echo htmlspecialchars($mainProduct['product_image'] ?: '/LandingPage/images/default-product.jpg'); ?>"
                                     alt="<?php echo htmlspecialchars($productName); ?>"
                                     class="product-image"
                                     id="product-image-<?php echo $mainProduct['product_id']; ?>">
                            </div>
                            <div class="product-info">
                                <h3 class="product-name"><?php echo htmlspecialchars($productName); ?></h3>

                                <?php if (!empty($mainProduct['additional_description'])): ?>
                                <div class="product-description">
                                    <p><?php echo htmlspecialchars(substr($mainProduct['additional_description'], 0, 100)); ?>
                                    <?php if (strlen($mainProduct['additional_description']) > 100): ?>...<?php endif; ?></p>
                                </div>
                                <?php endif; ?>

                                <div class="view-details-hint">
                                    <i class="fas fa-search-plus"></i> Click to view details
                                </div>
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

    // Handle variant selection dropdown changes
    $(document).on('change', '.variant-dropdown', function() {
        const selectElement = $(this);
        const selectedOption = selectElement.find('option:selected');
        const productId = selectedOption.val();
        const price = selectedOption.data('price');
        const packaging = selectedOption.data('packaging');
        const imagePath = selectedOption.data('image');
        const productName = selectedOption.data('name');

        // Update product ID in related elements
        const productCard = selectElement.closest('.product-card');
        const quantityControls = productCard.find('.quantity-controls');
        const addToCartBtn = productCard.find('.add-to-cart-btn');

        // Update quantity buttons and input
        quantityControls.find('.quantity-btn').attr('data-product-id', productId);
        quantityControls.find('.quantity-input').attr('data-product-id', productId);

        // Update add to cart button attributes
        addToCartBtn.attr('data-product-id', productId);
        addToCartBtn.attr('data-product-name', productName);
        addToCartBtn.attr('data-product-price', price);
        addToCartBtn.attr('data-product-image', imagePath);
        addToCartBtn.attr('data-product-packaging', packaging);

        // Update displayed price
        const priceElement = productCard.find('.product-price');
        priceElement.text('₱' + parseFloat(price).toFixed(2));

        // Update packaging information
        const packagingElement = productCard.find('.product-packaging');
        if (packagingElement.length) {
            packagingElement.text(packaging || 'Not specified');
        }

        // Update product image if it exists
        const imageElement = productCard.find('.product-image');
        if (imageElement.length) {
            imageElement.attr('src', imagePath);
            imageElement.attr('alt', productName);
        }

        console.log('Variant changed:', {
            productId: productId,
            productName: productName,
            price: price,
            packaging: packaging,
            imagePath: imagePath
        });
    });

    // Product card click handler to show modal
    $(document).on('click', '.product-card', function(e) {
        // Don't trigger modal if clicking on buttons or inputs
        if ($(e.target).closest('.add-to-cart-btn, .quantity-controls, .variant-dropdown').length) {
            return;
        }

        const productCard = $(this);
        const productName = productCard.data('name');
        const category = productCard.data('category');

        // Show loading state
        $('#modal-product-name').text('Loading...');
        $('#modal-product-image').attr('src', '/LandingPage/images/loading.gif');
        $('#modal-product-packaging').text('');
        $('#modal-product-price').text('');
        $('#modal-product-description').text('');
        $('#modal-ingredients-list').empty();
        $('#modal-variant-select').empty();

        // Show the modal
        $('#productDetailModal').modal('show');

        // Fetch product details
        $.ajax({
            url: '/LandingPage/get_product_modal_data.php',
            type: 'POST',
            data: {
                product_name: productName,
                category: category
            },
            success: function(response) {
                console.log('Product modal data:', response);

                if (response.success) {
                    const mainProduct = response.main_product;
                    const variants = response.variants;

                    // Update modal title
                    $('#modal-product-name').text(productName);

                    // Update product image
                    $('#modal-product-image').attr('src', mainProduct.product_image || '/LandingPage/images/default-product.jpg');
                    $('#modal-product-image').attr('alt', productName);

                    // Update description if available
                    if (mainProduct.additional_description) {
                        $('#modal-product-description').text(mainProduct.additional_description);
                        $('#modal-description-container').show();
                    } else {
                        $('#modal-description-container').hide();
                    }

                    // Populate variant dropdown
                    $('#modal-variant-select').empty();
                    variants.forEach(function(variant) {
                        $('#modal-variant-select').append(
                            $('<option></option>')
                                .val(variant.product_id)
                                .text(variant.item_description + ' - ₱' + parseFloat(variant.price).toFixed(2))
                                .data('price', variant.price)
                                .data('packaging', variant.packaging)
                                .data('image', variant.product_image || '/LandingPage/images/default-product.jpg')
                                .data('name', variant.item_description)
                                .data('description', variant.additional_description)
                                .data('ingredients', variant.ingredients_array)
                        );
                    });

                    // Set initial values
                    updateModalFromVariant();

                    // Show ingredients if available
                    if (mainProduct.ingredients_array && mainProduct.ingredients_array.length > 0) {
                        renderIngredients(mainProduct.ingredients_array);
                        $('#modal-ingredients-container').show();
                    } else {
                        $('#modal-ingredients-container').hide();
                    }
                } else {
                    console.error('Error fetching product details:', response.error);
                    $('#productDetailModal').modal('hide');
                    showPopup('Error loading product details', true);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                $('#productDetailModal').modal('hide');
                showPopup('Error loading product details: ' + error, true);
            }
        });
    });

    // Handle variant selection in modal
    $('#modal-variant-select').on('change', function() {
        updateModalFromVariant();
    });

    // Function to update modal content based on selected variant
    function updateModalFromVariant() {
        const selectedOption = $('#modal-variant-select option:selected');
        const productId = selectedOption.val();
        const price = selectedOption.data('price');
        const packaging = selectedOption.data('packaging');
        const imagePath = selectedOption.data('image');
        const productName = selectedOption.data('name');
        const description = selectedOption.data('description');
        const ingredients = selectedOption.data('ingredients');

        // Update modal content
        $('#modal-product-packaging').text(packaging || 'Not specified');
        $('#modal-product-price').text('₱' + parseFloat(price).toFixed(2));

        // Update image if it exists
        if (imagePath) {
            $('#modal-product-image').attr('src', imagePath);
            $('#modal-product-image').attr('alt', productName);
        }

        // Update description if available
        if (description) {
            $('#modal-product-description').text(description);
            $('#modal-description-container').show();
        } else {
            $('#modal-description-container').hide();
        }

        // Update ingredients if available
        if (ingredients && ingredients.length > 0) {
            renderIngredients(ingredients);
            $('#modal-ingredients-container').show();
        } else {
            $('#modal-ingredients-container').hide();
        }

        // Update add to cart button
        $('#modal-add-to-cart-btn').data('product-id', productId);
        $('#modal-add-to-cart-btn').data('product-name', productName);
        $('#modal-add-to-cart-btn').data('product-price', price);
        $('#modal-add-to-cart-btn').data('product-image', imagePath);
        $('#modal-add-to-cart-btn').data('product-packaging', packaging);
    }

    // Function to render ingredients list
    function renderIngredients(ingredients) {
        const ingredientsList = $('#modal-ingredients-list');
        ingredientsList.empty();

        if (ingredients && ingredients.length > 0) {
            ingredients.forEach(function(ingredient) {
                if (Array.isArray(ingredient) && ingredient.length >= 2) {
                    const name = ingredient[0];
                    const amount = ingredient[1];

                    const ingredientItem = $('<div class="col-md-4 col-6 mb-2"></div>');
                    const itemContent = $('<div class="ingredient-item"></div>');

                    itemContent.append('<span class="ingredient-name">' + name + '</span>');
                    if (amount) {
                        itemContent.append('<span class="ingredient-amount">(' + amount + 'g)</span>');
                    }

                    ingredientItem.append(itemContent);
                    ingredientsList.append(ingredientItem);
                }
            });
        } else {
            ingredientsList.append('<div class="col-12"><p class="text-muted">No ingredients information available</p></div>');
        }
    }

    // Modal quantity controls - using direct event binding to prevent double-triggering
    $('#modal-quantity-decrease').on('click', function(e) {
        e.stopPropagation(); // Prevent event bubbling
        let quantity = parseInt($('#modal-quantity-input').val());
        if (quantity > 1) {
            $('#modal-quantity-input').val(quantity - 1);
        }
    });

    $('#modal-quantity-increase').on('click', function(e) {
        e.stopPropagation(); // Prevent event bubbling
        let quantity = parseInt($('#modal-quantity-input').val());
        if (quantity < 100) {
            $('#modal-quantity-input').val(quantity + 1);
        }
    });

    $('#modal-quantity-input').on('change', function(e) {
        e.stopPropagation(); // Prevent event bubbling
        let quantity = parseInt($(this).val());
        if (isNaN(quantity) || quantity < 1) {
            $(this).val(1);
        } else if (quantity > 100) {
            $(this).val(100);
        }
    });

    // Modal add to cart button
    $('#modal-add-to-cart-btn').on('click', function(e) {
        e.stopPropagation(); // Prevent event bubbling

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
        const productId = $('#modal-variant-select').val();
        const productName = $('#modal-variant-select option:selected').data('name');
        const productPrice = $('#modal-variant-select option:selected').data('price');
        const productImage = $('#modal-variant-select option:selected').data('image');
        const productPackaging = $('#modal-variant-select option:selected').data('packaging');
        const productCategory = $('.category-section:visible').data('category') || '';
        const quantity = parseInt($('#modal-quantity-input').val());

        // Validate data before sending
        if (!productId || !productName || !productPrice) {
            console.error('Missing required product data:', {
                productId: productId,
                productName: productName,
                productPrice: productPrice
            });
            showPopup('Error: Missing product data', true);
            return;
        }

        // Disable the button to prevent multiple clicks
        button.prop('disabled', true).text('Adding...');

        // Show a temporary message
        showPopup('Adding to cart...');

        // Log the data being sent
        console.log('Adding to cart:', {
            product_id: productId,
            product_name: productName,
            price: productPrice,
            image_path: productImage,
            packaging: productPackaging,
            quantity: quantity
        });

        $.ajax({
            url: '/LandingPage/add_to_cart.php',
            type: 'POST',
            dataType: 'json',
            data: {
                product_id: productId,
                product_name: productName,
                price: productPrice,
                image_path: productImage,
                packaging: productPackaging,
                category: productCategory, // Include category information
                quantity: quantity
            },
            success: function(response) {
                console.log('Response received:', response);

                try {
                    if (response.success) {
                        // Update cart count
                        $('#cart-count').text(response.cart_count);

                        // Show success message
                        showPopup('Item added to cart');

                        // Reset quantity to 1
                        $('#modal-quantity-input').val(1);

                        // Close the modal
                        $('#productDetailModal').modal('hide');

                        // Update cart modal if it's open
                        if ($('#cartModal').hasClass('show')) {
                            updateCartModal();
                        }
                    } else {
                        showPopup(response.message || 'Error adding to cart', true);
                        console.error('Server error:', response);
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
                button.prop('disabled', false).text('Add to Cart');
            }
        });
    });

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
        // Get category from the product card's parent category section
        const productCategory = button.closest('.product-card').data('category') || button.data('product-category') || '';

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
            dataType: 'json',
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
                    // Handle the response
                    if (response.success) {
                        // Update cart count
                        $('#cart-count').text(response.cart_count);

                        // Show success message
                        showPopup('Item added to cart');

                        // Reset quantity to 1
                        quantityInput.val(1);

                        // Update cart modal if it's open
                        if ($('#cartModal').hasClass('show')) {
                            updateCartModal();
                        }
                    } else {
                        showPopup(response.message || 'Error adding to cart', true);
                    }
                } catch (e) {
                    console.error('Error handling response:', e);
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
// Include the footer
require_once 'footer.php';
?>

<!-- Product Detail Modal -->
<div class="modal fade" id="productDetailModal" tabindex="-1" role="dialog" aria-labelledby="productDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productDetailModalLabel">Product Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-5">
                        <div class="product-modal-image-container">
                            <img src="" alt="Product Image" id="modal-product-image" class="img-fluid">
                        </div>
                    </div>
                    <div class="col-md-7">
                        <h3 id="modal-product-name" class="mb-3"></h3>

                        <div class="form-group mb-3">
                            <label for="modal-variant-select" class="font-weight-bold">Select Variant:</label>
                            <select class="form-control" id="modal-variant-select"></select>
                        </div>

                        <div class="mb-3">
                            <span class="font-weight-bold">Packaging:</span>
                            <span id="modal-product-packaging" class="ml-2 badge badge-light"></span>
                        </div>

                        <div class="mb-3">
                            <span class="font-weight-bold">Price:</span>
                            <span id="modal-product-price" class="ml-2 text-success font-weight-bold"></span>
                        </div>

                        <div class="mb-3" id="modal-description-container">
                            <span class="font-weight-bold">Description:</span>
                            <p id="modal-product-description" class="mt-1"></p>
                        </div>

                        <div class="quantity-controls mb-3">
                            <label class="font-weight-bold">Quantity:</label>
                            <div class="d-flex align-items-center">
                                <button class="quantity-btn decrease" id="modal-quantity-decrease">-</button>
                                <input type="number" class="quantity-input" id="modal-quantity-input" value="1" min="1" max="100">
                                <button class="quantity-btn increase" id="modal-quantity-increase">+</button>
                            </div>
                        </div>

                        <button class="btn btn-primary btn-block" id="modal-add-to-cart-btn">
                            <i class="fas fa-cart-plus mr-2"></i> Add to Cart
                        </button>
                    </div>
                </div>

                <div class="mt-4" id="modal-ingredients-container">
                    <h5 class="mb-3">Ingredients</h5>
                    <div id="modal-ingredients-list" class="row"></div>
                </div>
            </div>
        </div>
    </div>
</div>

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
    });
</script>