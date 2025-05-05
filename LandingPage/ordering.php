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
require_once 'db_connection.php'; // Verify this path

// Fetch all products from the database
$products = []; // Keep for category filter population if needed
$groupedProducts = []; // For storing products grouped by name
$query = "SELECT * FROM products ORDER BY category, product_name, price ASC"; // Ensure consistent grouping

// Add error handling for the query
try {
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Group by product name for variant handling
            $productName = !empty($row['product_name']) ? $row['product_name'] : preg_replace('/\s*\([A-Z][^)]*\)$/', '', $row['item_description']);
            $category = $row['category'];

            // Populate category list for filter dropdown
            if (!isset($products[$category])) {
                $products[$category] = [];
            }
            // $products[$category][] = $row; // Optional: keep if needed elsewhere

            if (!isset($groupedProducts[$category][$productName])) {
                // Initialize the group with the first product found as 'main_product'
                $groupedProducts[$category][$productName] = [
                    'main_product' => $row, // This will be the first one encountered (lowest price due to ORDER BY)
                    'variants' => [$row] // Add itself as the first variant
                ];
            } else {
                // If the group already exists, just add this row as another variant
                $groupedProducts[$category][$productName]['variants'][] = $row;
            }
        }
    } else {
        error_log("No products found or query failed: " . $conn->error);
    }
} catch (Exception $e) {
    error_log("Exception in ordering.php database query: " . $e->getMessage());
}

// Close the connection after we're done with it
$conn->close();

// Ensure arrays exist to prevent errors in loops/keys
if (empty($groupedProducts)) {
    $groupedProducts = [];
}
if (empty($products)) {
    $products = []; // Used for category filter
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
    echo '<h5>Grouped Products Data:</h5>';
    echo '<pre>'; print_r($groupedProducts); echo '</pre>';
    echo '</div>';
}
?>

<!-- jQuery and Bootstrap JS are loaded in header.php and footer.php -->

<style>
    /* Reset some styles that might conflict with the header */
    body {
        padding-top: 0; /* Adjust if header has fixed height */
    }

    /* Main content styling */
    .ordering-container {
        max-width: 1200px;
        margin: 20px auto; /* Added top margin */
        padding: 20px;
        background-color: #fff;
        border-radius: 8px; /* Optional: adds rounded corners */
        box-shadow: 0 2px 10px rgba(0,0,0,0.05); /* Optional: subtle shadow */
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
        display: flex; /* Use flexbox for layout */
        flex-direction: column; /* Stack elements vertically */
        cursor: pointer; /* Make the whole card clickable */
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
        position: relative; /* Needed for overlay/badges if added */
    }

    .product-image {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        padding: 10px;
        transition: transform 0.3s ease; /* Add zoom effect on hover */
    }
    .product-card:hover .product-image {
         transform: scale(1.05); /* Slight zoom on hover */
    }


    .product-info {
        padding: 15px;
        flex-grow: 1; /* Allow info section to grow */
        display: flex;
        flex-direction: column;
    }

    .product-name {
        font-weight: 600;
        color: #333;
        margin-bottom: 5px; /* Reduced margin */
        font-size: 1.1rem;
        flex-grow: 1; /* Push details hint down */
    }

    .product-description {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 10px;
        line-height: 1.4;
        /* Limit description lines */
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2; /* Limit to 2 lines */
        -webkit-box-orient: vertical;
    }

    .product-description p {
        margin: 0;
    }

    .view-details-hint {
        color: #9a7432;
        font-size: 0.85rem;
        margin-top: auto; /* Pushes to the bottom */
        text-align: center;
        padding: 5px;
        background-color: #f9f9f9;
        border-radius: 4px;
        transition: all 0.2s ease;
        border: 1px dashed #eee; /* Subtle border */
    }

    .product-card:hover .view-details-hint {
        background-color: #9a7432;
        color: white;
        border-color: #9a7432;
    }

    /* Login indicator for add to cart button (if needed on card) */
    .add-to-cart-btn.login-required {
        background-color: #6c757d;
    }

    .add-to-cart-btn.login-required:hover {
        background-color: #5a6268;
    }

    /* Search and filter section */
    .search-filter-section {
        margin-bottom: 30px;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: center;
        background-color: #f8f9fa; /* Light background */
        padding: 15px;
        border-radius: 5px;
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
        padding-left: 40px; /* Space for icon */
    }

    .search-box i.fa-search { /* Target only search icon */
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #777;
    }

    .clear-search {
        position: absolute;
        right: 10px; /* Adjusted position */
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #aaa;
        cursor: pointer;
        padding: 0;
        font-size: 16px; /* Slightly larger */
        width: 24px; /* Increased size */
        height: 24px; /* Increased size */
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        line-height: 1; /* Ensure icon centers */
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
        background-color: white; /* Ensure white background */
    }

    /* Active Filters Display */
     #active-filters {
        margin-bottom: 20px;
        padding: 10px;
        background-color: #e9ecef;
        border-radius: 4px;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap; /* Allow wrapping */
    }
    #active-filters .badge {
        font-size: 0.9rem;
        padding: 0.4em 0.8em;
        display: inline-flex; /* Use flex for alignment */
        align-items: center;
    }
     #active-filters .badge i {
         margin-right: 5px;
     }
    #active-filters .badge .close {
        font-size: 1.1rem; /* Make 'x' slightly larger */
        margin-left: 8px;
        line-height: 1;
        padding: 0 3px; /* Add padding */
        background-color: rgba(0,0,0,0.1); /* Subtle background */
        border-radius: 50%;
        opacity: 0.7;
        cursor: pointer;
    }
     #active-filters .badge .close:hover {
        opacity: 1;
        background-color: rgba(0,0,0,0.2);
     }
     #clearFilters {
         margin-left: auto; /* Push clear button to the right */
     }

    /* Product Modal Styling */
    .modal-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }
    .modal-title {
        color: #495057;
    }
    .modal-content {
        border-radius: 0.3rem;
        border: none;
    }
    .modal-body {
        padding: 1.5rem; /* More padding */
    }

    .product-modal-image-container {
        height: 300px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #fff; /* White background */
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 20px; /* Increased margin */
        border: 1px solid #eee; /* Subtle border */
    }

    .product-modal-image-container img {
        max-height: 100%;
        max-width: 100%;
        object-fit: contain;
        padding: 10px; /* Padding inside container */
    }

    #modal-product-name {
        color: #333;
        font-weight: 700;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 10px;
        margin-bottom: 20px; /* Spacing below name */
        font-size: 1.75rem; /* Larger name */
    }

    /* Styling for modal info sections */
    .modal-info-section {
        margin-bottom: 12px; /* Consistent spacing */
        display: flex;
        align-items: baseline; /* Align text nicely */
        min-height: 24px; /* Ensure consistent height */
    }
    .modal-info-section .info-label {
        font-weight: 600;
        color: #555;
        width: 90px; /* Fixed width for labels */
        flex-shrink: 0; /* Prevent label shrinking */
    }
    .modal-info-section .info-value {
        color: #333;
    }

    #modal-product-price .info-value {
        font-size: 1.4rem; /* Larger price */
        color: #9a7432 !important;
        font-weight: 700;
    }

    #modal-product-packaging .info-value {
        background-color: #e9ecef;
        color: #495057;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.9rem;
    }

    #modal-description-container {
        margin-top: 15px; /* Space above description */
    }
     #modal-description-container .info-label {
        display: block; /* Make label block */
        margin-bottom: 5px;
     }
    #modal-product-description {
        background-color: #f8f9fa;
        padding: 10px 15px;
        border-radius: 4px;
        font-size: 0.9rem;
        color: #555;
        border-left: 3px solid #9a7432;
        line-height: 1.5;
    }

    /* Modal Variant Selector */
    #modal-variant-select-group label {
        font-weight: 600;
        color: #555;
        margin-bottom: 0.3rem; /* Space below label */
        display: block;
    }
    #modal-variant-select {
        background-color: #fff;
        border-color: #ced4da;
    }
    #modal-variant-select:disabled {
        background-color: #e9ecef;
        opacity: 0.7;
    }

    /* Modal Quantity Controls */
    .modal-quantity-controls {
        margin-bottom: 20px;
        display: flex;
        align-items: center;
    }
    .modal-quantity-controls label { /* Using .info-label class */
         margin-right: 10px;
         margin-bottom: 0; /* Remove default margin */
    }
    .modal-quantity-controls .quantity-controls {
        display: flex;
        align-items: center;
    }
    .modal-quantity-controls .quantity-btn {
        width: 34px; /* Slightly larger */
        height: 34px;
        background-color: #e9ecef;
        border: 1px solid #ced4da;
        color: #495057;
        font-size: 1.1rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
        transition: background-color 0.2s;
    }
    .modal-quantity-controls .quantity-btn:hover {
        background-color: #dee2e6;
    }
    .modal-quantity-controls .quantity-btn.decrease {
        border-radius: 4px 0 0 4px;
    }
     .modal-quantity-controls .quantity-btn.increase {
        border-radius: 0 4px 4px 0;
    }
    .modal-quantity-controls .quantity-input {
        width: 50px;
        height: 34px;
        text-align: center;
        border: 1px solid #ced4da;
        border-left: none;
        border-right: none;
        margin: 0;
        font-size: 1rem;
        padding: 0 5px;
        -moz-appearance: textfield;
    }
    .modal-quantity-controls .quantity-input::-webkit-outer-spin-button,
    .modal-quantity-controls .quantity-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    /* Modal Ingredients */
    #modal-ingredients-container {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-top: 25px; /* More space above */
        border: 1px solid #eee;
    }
    #modal-ingredients-container h5 {
        color: #9a7432;
        border-bottom: 1px solid #e0e0e0;
        padding-bottom: 8px;
        margin-bottom: 15px; /* Space below title */
        font-weight: 600;
    }
    .ingredient-item {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
        padding: 8px 10px; /* More padding */
        background-color: #fff;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        border: 1px solid #eee;
        transition: box-shadow 0.2s;
    }
    .ingredient-item:hover {
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .ingredient-name {
        font-weight: 500; /* Slightly lighter */
        color: #555;
        flex-grow: 1; /* Take available space */
    }
    .ingredient-amount {
        color: #777;
        font-size: 0.85rem;
        margin-left: 8px; /* More space */
        white-space: nowrap; /* Prevent wrapping */
    }

    /* Modal Add to Cart Button */
    #modal-add-to-cart-btn {
        background-color: #9a7432;
        border-color: #9a7432;
        font-weight: 600;
        padding: 10px 15px;
        transition: all 0.3s ease;
        font-size: 1.1rem; /* Slightly larger text */
    }
    #modal-add-to-cart-btn:hover:not(:disabled) { /* Add :not(:disabled) */
        background-color: #8a6422;
        border-color: #8a6422;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
     #modal-add-to-cart-btn:disabled {
        background-color: #c0a16b; /* Lighter color when disabled */
        border-color: #c0a16b;
        cursor: not-allowed;
        opacity: 0.65;
     }

    /* Responsive adjustments */
    @media (max-width: 991px) { /* Adjust breakpoint for modal layout */
        .modal-body .row {
            flex-direction: column; /* Stack image and info vertically */
        }
         .modal-body .col-md-5, .modal-body .col-md-7 {
            width: 100%;
            max-width: 100%;
            flex: 0 0 100%;
         }
         .product-modal-image-container {
            height: 250px; /* Adjust height */
         }
    }
    @media (max-width: 768px) {
        .product-grid {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        }
        .page-title {
            font-size: 2rem;
        }
        .modal-dialog {
            max-width: 95%; /* Wider modal on smaller screens */
        }
    }

    @media (max-width: 576px) {
        .product-grid {
            grid-template-columns: 1fr; /* Single column */
        }
        .search-filter-section {
            flex-direction: column;
            align-items: stretch; /* Stretch items */
        }
        .search-box, .category-filter {
            width: 100%;
            min-width: unset;
        }
         #clearFilters {
             margin-left: 0; /* Align left on small screens */
             margin-top: 10px;
             width: 100%;
         }
        .modal-dialog {
            margin: 0.5rem; /* Smaller margin */
            max-width: calc(100% - 1rem); /* Full width minus margin */
        }
         .product-modal-image-container {
            height: 200px;
         }
         #modal-product-name {
            font-size: 1.5rem;
         }
         .modal-info-section .info-label {
            width: 80px; /* Adjust label width */
         }
         #modal-ingredients-container .row .col-md-4 {
            flex: 0 0 50%; /* 2 columns on small screens */
            max-width: 50%;
         }
    }

    /* No results message */
    #no-results-message {
        background-color: #fff3cd; /* Warning background */
        color: #664d03; /* Warning text */
        border-color: #ffecb5; /* Warning border */
    }

    /* Login Alert */
    .login-alert {
        background-color: #e2f3ff;
        border-color: #b8e0ff;
        color: #0056b3;
    }
    .login-alert .alert-link {
        color: #004085;
        font-weight: 600;
    }

    /* Global Popup Style */
    #globalPopup {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1060; /* Above modals */
        padding: 1rem 1.5rem;
        border-radius: 0.25rem;
        color: white;
        display: none;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        min-width: 250px;
        text-align: center;
    }
    #globalPopup.alert-success {
        background-color: #28a745; /* Bootstrap success green */
    }
    #globalPopup.alert-danger {
         background-color: #dc3545; /* Bootstrap danger red */
    }

</style>

<div class="ordering-container">
    <h1 class="page-title">Our Products</h1>

    <?php if (!isset($_SESSION['username'])): ?>
    <div class="alert login-alert" role="alert" style="margin-bottom: 20px;">
        <i class="fas fa-info-circle mr-2"></i> Please <a href="/LandingPage/login.php" class="alert-link">login</a> or <a href="/LandingPage/register.php" class="alert-link">register</a> to add products to your cart.
    </div>
    <?php endif; ?>

    <div class="search-filter-section">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" class="form-control" placeholder="Search products by name...">
            <button type="button" id="clearSearch" class="clear-search" style="display: none;" aria-label="Clear search">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="category-filter">
            <select id="categoryFilter" class="form-control">
                <option value="">All Categories</option>
                <?php foreach (array_keys($products) as $category): ?>
                    <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div id="active-filters" class="mb-3" style="display: none;">
        <span class="badge badge-pill badge-secondary mr-2" id="search-filter-badge" style="display: none;">
            <i class="fas fa-search"></i> <span id="search-term-display"></span>
            <button type="button" class="close ml-1" aria-label="Remove search filter" onclick="$('#searchInput').val('').trigger('input');">
                <span aria-hidden="true">&times;</span>
            </button>
        </span>
        <span class="badge badge-pill badge-secondary" id="category-filter-badge" style="display: none;">
            <i class="fas fa-tag"></i> <span id="category-display"></span>
            <button type="button" class="close ml-1" aria-label="Remove category filter" onclick="$('#categoryFilter').val('').trigger('change');">
                <span aria-hidden="true">&times;</span>
            </button>
        </span>
         <button type="button" id="clearFilters" class="btn btn-sm btn-outline-danger" style="display: none;">
            <i class="fas fa-times-circle mr-1"></i> Clear All Filters
        </button>
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
                            // Use the main_product data for the card display
                            $mainProduct = $productGroup['main_product'];
                            $variants = $productGroup['variants']; // We know variants exist from the grouping logic
                            $displayProductName = $productName; // Use the grouped product name
                        ?>
                        <div class="product-card"
                             data-name="<?php echo htmlspecialchars($displayProductName); ?>"
                             data-category="<?php echo htmlspecialchars($category); ?>">
                            <div class="product-image-container">
                                <img src="<?php echo htmlspecialchars($mainProduct['product_image'] ?: '/LandingPage/images/default-product.jpg'); ?>"
                                     alt="<?php echo htmlspecialchars($displayProductName); ?>"
                                     class="product-image">
                            </div>
                            <div class="product-info">
                                <h3 class="product-name"><?php echo htmlspecialchars($displayProductName); ?></h3>

                                <?php if (!empty($mainProduct['additional_description'])): ?>
                                <div class="product-description">
                                    <!-- Use paragraph for description -->
                                    <p><?php echo htmlspecialchars($mainProduct['additional_description']); ?></p>
                                </div>
                                <?php endif; ?>

                                <div class="view-details-hint">
                                    <i class="fas fa-info-circle mr-1"></i> View Details & Variants
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
         <div id="no-results-message" class="alert alert-warning text-center my-4" style="display: none;">
            <i class="fas fa-exclamation-triangle mr-2"></i> No products found matching your criteria.
        </div>
    <?php endif; ?>
</div>

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
                             <!-- Initial state handled by JS -->
                            <img src="" alt="" id="modal-product-image" class="img-fluid">
                        </div>
                    </div>
                    <div class="col-md-7">
                         <!-- Initial state handled by JS -->
                        <h3 id="modal-product-name" class="mb-3"></h3>

                        <!-- Variant Selector -->
                        <div class="form-group mb-3" id="modal-variant-select-group">
                            <label for="modal-variant-select" class="info-label">Variant:</label>
                            <select class="form-control" id="modal-variant-select">
                                <!-- Options populated by JS -->
                            </select>
                        </div>

                        <!-- Packaging Info -->
                        <div class="modal-info-section" id="modal-product-packaging">
                             <span class="info-label">Packaging:</span>
                             <span class="info-value badge badge-light"></span>
                        </div>

                        <!-- Price Info -->
                         <div class="modal-info-section" id="modal-product-price">
                             <span class="info-label">Price:</span>
                             <span class="info-value"></span>
                         </div>

                        <!-- Description -->
                        <div id="modal-description-container" style="display: none;">
                            <span class="info-label d-block mb-1">Description:</span>
                            <p id="modal-product-description" class="mt-1"></p>
                        </div>

                        <!-- Quantity Controls -->
                        <div class="modal-quantity-controls mb-3">
                            <label class="info-label">Quantity:</label>
                            <div class="quantity-controls">
                                <button type="button" class="quantity-btn decrease" id="modal-quantity-decrease" aria-label="Decrease quantity">-</button>
                                <input type="number" class="quantity-input" id="modal-quantity-input" value="1" min="1" max="100" aria-label="Quantity">
                                <button type="button" class="quantity-btn increase" id="modal-quantity-increase" aria-label="Increase quantity">+</button>
                            </div>
                        </div>

                        <!-- Add to Cart Button -->
                        <button type="button" class="btn btn-primary btn-block" id="modal-add-to-cart-btn" <?php echo !isset($_SESSION['username']) ? 'disabled' : ''; ?>>
                            <!-- Text/Icon set by JS -->
                        </button>
                         <?php if (!isset($_SESSION['username'])): ?>
                            <small class="form-text text-muted text-center mt-2">You must be logged in to add items to the cart.</small>
                         <?php endif; ?>
                    </div>
                </div>

                <!-- Ingredients Section -->
                <div class="mt-4" id="modal-ingredients-container" style="display: none;">
                    <h5 class="mb-3">Ingredients</h5>
                    <div id="modal-ingredients-list" class="row">
                        <!-- Content populated by JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Global Popup Placeholder (can be added here or in footer) -->
<div id="globalPopup"></div>

<!-- **** START: Updated JavaScript Block using shown.bs.modal **** -->
<script>
$(document).ready(function() {
    // Check if user is logged in
    const isLoggedIn = <?php echo isset($_SESSION['username']) ? 'true' : 'false'; ?>;
    let currentModalData = null; // Variable to store fetched data for the modal

    // --- Product Card Click Handler ---
    $(document).on('click', '.product-card', function(e) {
        if ($(e.target).closest('button, a, input, select').length) {
            return;
        }

        const productCard = $(this);
        const productName = productCard.data('name');
        const category = productCard.data('category');
        currentModalData = null; // Reset data store

        // --- Reset and Show Modal (Initial State) ---
        const modal = $('#productDetailModal');
        modal.data('category', category); // Store category
        modal.data('product-name', productName); // Store product name for the 'shown' event

        $('#productDetailModalLabel').text('Product Details');
        $('#modal-product-name').text('Loading...');
        // <<< FIX PATH HERE: Update this path if your default/loading image is different >>>
        $('#modal-product-image').attr('src', '/LandingPage/images/default-product.jpg').attr('alt', 'Loading Product Image');
        $('#modal-variant-select').html('<option>Loading variants...</option>').prop('disabled', true);
        $('#modal-variant-select-group').show();
        $('#modal-product-packaging .info-value').text('-');
        $('#modal-product-price .info-value').text('-');
        $('#modal-product-description').text('');
        $('#modal-description-container').hide();
        $('#modal-ingredients-list').html('<p class="col-12 text-muted">Loading ingredients...</p>');
        $('#modal-ingredients-container').hide();
        $('#modal-quantity-input').val(1);
        const initialBtnText = isLoggedIn ? '<i class="fas fa-spinner fa-spin mr-2"></i> Loading...' : '<i class="fas fa-lock mr-2"></i> Login to Add';
        $('#modal-add-to-cart-btn').prop('disabled', true).html(initialBtnText);

        // Show the modal - the 'shown.bs.modal' event will handle population
        modal.modal('show');

        // --- Fetch Product Data via AJAX (but don't populate yet) ---
        console.log(`Fetching data for: ${productName}`);
        $.ajax({
            url: '/LandingPage/get_product_modal_data.php', // Verify path
            type: 'POST',
            dataType: 'json',
            data: {
                product_name: productName,
                category: category
            },
            success: function(response) {
                console.log('AJAX success: Product modal data received:', response);
                if (response && response.success && response.variants && Array.isArray(response.variants)) {
                    // Store the data for the 'shown' event handler
                    currentModalData = response;
                    // Trigger a custom event to signal data is ready (optional but good practice)
                    modal.trigger('data-ready');
                } else {
                    console.error('AJAX success but data invalid:', response);
                    currentModalData = { error: true, message: response?.error || 'Invalid data received' };
                     modal.trigger('data-ready'); // Signal even if error
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error fetching product details:', status, error, xhr.responseText);
                 currentModalData = { error: true, message: 'Error contacting server: ' + error };
                 modal.trigger('data-ready'); // Signal even if error
            }
        });
    });

    // --- Modal 'shown' Event Handler (Populates content AFTER modal is visible) ---
    $('#productDetailModal').on('shown.bs.modal', function (e) {
        console.log("Modal 'shown.bs.modal' event triggered.");
        const modal = $(this);
        const productName = modal.data('product-name'); // Retrieve stored product name

        // Function to populate the modal once data is ready
        const populateModalContent = () => {
            console.log("Attempting to populate modal content. Data:", currentModalData);

            if (!currentModalData) {
                console.warn("Data not ready yet in shown.bs.modal handler.");
                // Optionally show a persistent loading state or wait longer
                return;
            }

            // --- Handle AJAX Error Case ---
            if (currentModalData.error) {
                 console.error("Populating modal with error state.");
                 $('#productDetailModalLabel').text(productName || 'Error');
                 $('#modal-product-name').text('Error Loading Details');
                 showGlobalPopup(currentModalData.message || 'Failed to load product details.', true);
                 $('#modal-add-to-cart-btn').prop('disabled', true).text('Error');
                 // Clear variants dropdown in case it was stuck on loading
                 $('#modal-variant-select').empty().append('<option>Error</option>').prop('disabled', true);
                 return; // Stop processing
            }

            // --- Handle AJAX Success Case ---
            const mainProduct = currentModalData.main_product;
            const variants = currentModalData.variants;

            // Double check variants array
             if (!mainProduct || !variants || !Array.isArray(variants) || variants.length === 0) {
                 console.error('Data was marked success, but variant array is invalid in shown.bs.modal:', currentModalData);
                 $('#modal-product-name').text('Data Error');
                 showGlobalPopup('Error: Invalid product data format received.', true);
                 $('#modal-add-to-cart-btn').prop('disabled', true).text('Error');
                  $('#modal-variant-select').empty().append('<option>Error</option>').prop('disabled', true);
                 return;
            }

            console.log("Populating modal header and base name.");
            $('#productDetailModalLabel').text(productName);
            $('#modal-product-name').text(productName);

            const variantSelect = $('#modal-variant-select');
            variantSelect.empty(); // Clear "Loading..." option
            console.log("Cleared variant select dropdown (in shown.bs.modal).");

            let optionsAppended = 0;

            // --- Loop through variants and Append Options ---
            console.log(`Looping through ${variants.length} variants (in shown.bs.modal)...`);
            variants.forEach(function(variant, index) {
                console.log(`Processing variant ${index}:`, variant);

                // Validation Check
                if (!variant || typeof variant.product_id === 'undefined' || typeof variant.item_description === 'undefined' || typeof variant.price === 'undefined') {
                    console.warn(`Skipping variant ${index} due to missing data (in shown.bs.modal).`, variant);
                    return;
                }

                console.log(`Variant ${index} passed validation. Appending option...`);
                try {
                    variantSelect.append(
                        $('<option></option>')
                            .val(variant.product_id)
                            .text(variant.item_description + ' - ₱' + parseFloat(variant.price).toFixed(2))
                            .data('price', variant.price)
                            .data('packaging', variant.packaging)
                            .data('image', variant.product_image || '/LandingPage/images/default-product.jpg')
                            .data('name', variant.item_description)
                            .data('description', variant.additional_description)
                            .data('ingredients', variant.ingredients_array || [])
                            .data('stock', variant.stock_quantity)
                    );
                    optionsAppended++;
                    console.log(`Successfully appended option for variant ${index}: ${variant.item_description} (in shown.bs.modal)`);

                } catch (appendError) {
                    console.error(`Error appending option for variant ${index} (in shown.bs.modal):`, appendError, variant);
                }
            }); // End forEach loop

            console.log(`Finished looping (in shown.bs.modal). Total options appended: ${optionsAppended}`);

            // --- Handle Single vs Multiple Variants ---
            if (optionsAppended <= 1) {
                console.log("Hiding variant select group (<= 1 option appended).");
                $('#modal-variant-select-group').hide();
                variantSelect.prop('disabled', true);
            } else {
                console.log("Showing variant select group (> 1 option appended).");
                $('#modal-variant-select-group').show();
                variantSelect.prop('disabled', false);
            }

             // --- Check if options REALLY exist after appending ---
             // A brief delay might help ensure DOM is updated before checking
             setTimeout(() => {
                 const optionsInDOM = modal.find('#modal-variant-select option').length;
                 console.log(`Verification check: Found ${optionsInDOM} options in DOM.`);
                 if (optionsInDOM !== optionsAppended) {
                     console.error(`!!!! DISCREPANCY !!!! Appended ${optionsAppended} options according to logs, but found ${optionsInDOM} in DOM!`);
                 }
             }, 100); // 100ms delay


            // --- Update Modal Content Based on First/Selected Variant ---
            if (optionsAppended > 0) {
                console.log("Calling updateModalFromVariant() (in shown.bs.modal).");
                updateModalFromVariant(); // Update with the first variant's details
            } else {
                 console.warn("No options were appended, skipping updateModalFromVariant() (in shown.bs.modal).");
                 $('#modal-product-packaging .info-value').text('N/A');
                 $('#modal-product-price .info-value').text('N/A');
                 $('#modal-product-image').attr('src', '/LandingPage/images/default-product.jpg').attr('alt', 'No variants available');
            }

            // --- Enable Add to Cart button ---
            if (optionsAppended > 0 && isLoggedIn) {
                 console.log("Enabling Add to Cart button (in shown.bs.modal).");
                 $('#modal-add-to-cart-btn').prop('disabled', false).html('<i class="fas fa-cart-plus mr-2"></i> Add to Cart');
            } else {
                const reason = !isLoggedIn ? "(User not logged in)" : "(No variants appended)";
                 console.log(`Add to Cart button remains disabled ${reason}.`);
                 const buttonText = !isLoggedIn ? '<i class="fas fa-lock mr-2"></i> Login to Add' : '<i class="fas fa-times-circle mr-2"></i> Not Available';
                 $('#modal-add-to-cart-btn').prop('disabled', true).html(buttonText);
            }
        }; // End populateModalContent function

        // Check if data is already available (AJAX finished before modal animation)
        if (currentModalData) {
             console.log("Data was ready before 'shown.bs.modal' finished, populating now.");
             populateModalContent();
        } else {
             // If data not ready, wait for the custom 'data-ready' event
             console.log("Data not ready, waiting for 'data-ready' event on modal.");
             modal.one('data-ready', function() {
                 console.log("Modal 'data-ready' event received, populating now.");
                 populateModalContent();
             });
             // Add a timeout failsafe in case 'data-ready' never fires
             setTimeout(() => {
                 if (!currentModalData) {
                     console.error("Timeout waiting for data-ready event. Populating with error state.");
                     currentModalData = { error: true, message: "Timeout loading product data." };
                     populateModalContent();
                 }
             }, 5000); // 5 second timeout
        }

    }); // End 'shown.bs.modal' handler


    // --- Function to update modal content based on selected variant ---
    // (This function remains largely the same as before)
    function updateModalFromVariant() {
        const selectedOption = $('#modal-variant-select option:selected');
        if (!selectedOption.length) {
            console.warn('updateModalFromVariant called but no option selected.');
            // Maybe reset fields if this happens unexpectedly
            return;
        }

        const price = selectedOption.data('price');
        const packaging = selectedOption.data('packaging');
        const imagePath = selectedOption.data('image');
        const description = selectedOption.data('description');
        const ingredients = selectedOption.data('ingredients');
        // const stock = selectedOption.data('stock'); // Uncomment if using stock logic
        const productId = selectedOption.val();
        const variantName = selectedOption.data('name');

        console.log('Updating modal display for variant:', { productId, variantName, price, packaging });

        // Update fields
        $('#modal-product-packaging .info-value').text(packaging || '-');
        $('#modal-product-price .info-value').text(price ? '₱' + parseFloat(price).toFixed(2) : '-');
        $('#modal-product-image').attr('src', imagePath || '/LandingPage/images/default-product.jpg').attr('alt', variantName || 'Product Image');

        if (description) {
            $('#modal-product-description').text(description);
            $('#modal-description-container').show();
        } else {
            $('#modal-product-description').text('');
            $('#modal-description-container').hide();
        }

        renderIngredients(ingredients);

        const addToCartBtn = $('#modal-add-to-cart-btn');
        addToCartBtn.data('product-id', productId);
        addToCartBtn.data('product-name', variantName);
        addToCartBtn.data('product-price', price);
        addToCartBtn.data('product-image', imagePath);
        addToCartBtn.data('product-packaging', packaging);

        $('#modal-quantity-input').val(1); // Reset quantity on variant change

        // Add stock checking logic here if needed
    }

    // --- Handle variant selection change ---
    $('#modal-variant-select').on('change', function() {
        updateModalFromVariant();
    });


    // --- Function to render ingredients list ---
    // (This function remains the same)
    function renderIngredients(ingredients) {
        const ingredientsList = $('#modal-ingredients-list');
        ingredientsList.empty();

        if (ingredients && Array.isArray(ingredients) && ingredients.length > 0) {
            let hasValidIngredient = false;
            ingredients.forEach(function(ingredient) {
                if (Array.isArray(ingredient) && ingredient.length >= 1 && ingredient[0]) {
                    hasValidIngredient = true;
                    const name = ingredient[0];
                    const amount = (ingredient.length >= 2 && (ingredient[1] || ingredient[1] === 0)) ? ingredient[1] : null;

                    const ingredientItemCol = $('<div class="col-md-4 col-6 mb-2"></div>');
                    const itemContent = $('<div class="ingredient-item"></div>');

                    itemContent.append($('<span class="ingredient-name"></span>').text(name));
                    if (amount !== null) {
                        let amountDisplay = (typeof amount === 'number') ? `(${amount}g)` : `(${amount})`;
                        itemContent.append($('<span class="ingredient-amount"></span>').text(amountDisplay));
                    }

                    ingredientItemCol.append(itemContent);
                    ingredientsList.append(ingredientItemCol);
                } else {
                     console.warn("Skipping invalid ingredient format:", ingredient);
                }
            });

            if (hasValidIngredient) {
                $('#modal-ingredients-container').show();
            } else {
                 $('#modal-ingredients-container').hide();
            }
        } else {
            $('#modal-ingredients-container').hide();
        }
    }

    // --- Modal Quantity Controls ---
    // (These remain the same)
    $('#modal-quantity-decrease').on('click', function() {
        const input = $('#modal-quantity-input');
        let quantity = parseInt(input.val());
        if (!isNaN(quantity) && quantity > 1) {
            input.val(quantity - 1).trigger('change');
        }
     });
    $('#modal-quantity-increase').on('click', function() {
        const input = $('#modal-quantity-input');
        let quantity = parseInt(input.val());
        const maxQuantity = 100;
        if (!isNaN(quantity) && quantity < maxQuantity) {
            input.val(quantity + 1).trigger('change');
        } else if (isNaN(quantity)) {
             input.val(1).trigger('change');
        }
     });
    $('#modal-quantity-input').on('change input', function() {
        let quantity = parseInt($(this).val());
        const minQuantity = 1;
        const maxQuantity = 100;
        if (isNaN(quantity) || quantity < minQuantity) {
            if ($(this).val() !== '') { $(this).val(minQuantity); }
        } else if (quantity > maxQuantity) { $(this).val(maxQuantity); }
    }).on('blur', function() {
         let quantity = parseInt($(this).val());
         const minQuantity = 1;
         if (isNaN(quantity) || quantity < minQuantity) { $(this).val(minQuantity); }
    });

    // --- Modal Add to Cart Button ---
    // (This remains the same)
    $('#modal-add-to-cart-btn').on('click', function() {
         if (!isLoggedIn) {
             showGlobalPopup('Please login to add items to cart.', true);
             return;
         }
         const button = $(this);
         const productId = button.data('product-id');
         const productName = button.data('product-name');
         const productPrice = button.data('product-price');
         const productImage = button.data('product-image');
         const productPackaging = button.data('product-packaging');
         const productCategory = $('#productDetailModal').data('category') || '';
         const quantity = parseInt($('#modal-quantity-input').val());

         if (!productId || !productName || typeof productPrice === 'undefined' || isNaN(quantity) || quantity < 1) {
             console.error('Missing or invalid data for add to cart:', { productId, productName, productPrice, quantity });
             showGlobalPopup('Error: Could not add item. Invalid data selected.', true);
             return;
         }

         button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Adding...');
         showGlobalPopup('Adding item to cart...');

         console.log('Adding to cart (from modal):', { product_id: productId, product_name: productName, price: productPrice, quantity: quantity, image_path: productImage, packaging: productPackaging, category: productCategory });

         $.ajax({
             url: '/LandingPage/add_to_cart.php', // Verify path
             type: 'POST',
             dataType: 'json',
             data: { product_id: productId, product_name: productName, price: productPrice, image_path: productImage, packaging: productPackaging, category: productCategory, quantity: quantity },
             success: function(response) {
                 console.log('Add to cart response:', response);
                 if (response && response.success) {
                     $('#cart-count').text(response.cart_count || 0);
                     showGlobalPopup('Item added to cart successfully!');
                     $('#productDetailModal').modal('hide');
                 } else {
                     showGlobalPopup(response.message || 'Error adding item to cart.', true);
                 }
             },
             error: function(xhr, status, error) {
                 console.error('AJAX error adding to cart:', status, error, xhr.responseText);
                 showGlobalPopup('Error contacting server: ' + error, true);
             },
             complete: function() {
                 if (isLoggedIn) { button.prop('disabled', false).html('<i class="fas fa-cart-plus mr-2"></i> Add to Cart'); }
             }
         });
     });

    // --- Search and Filter Logic ---
    // (These remain the same)
    function updateFilterDisplay() {
        const searchTerm = $('#searchInput').val().trim();
        const selectedCategory = $('#categoryFilter').val();
        let filtersActive = false;
        if (searchTerm !== '') { $('#search-term-display').text(searchTerm); $('#search-filter-badge').show(); filtersActive = true; } else { $('#search-filter-badge').hide(); }
        if (selectedCategory !== '') { const categoryText = $('#categoryFilter option:selected').text(); $('#category-display').text(categoryText); $('#category-filter-badge').show(); filtersActive = true; } else { $('#category-filter-badge').hide(); }
        if (filtersActive) { $('#active-filters').slideDown(200); $('#clearFilters').show(); } else { $('#active-filters').slideUp(200); $('#clearFilters').hide(); }
        $('#clearSearch').toggle(searchTerm !== '');
    }
    function applyFilters() {
        const searchTerm = $('#searchInput').val().toLowerCase().trim();
        const selectedCategory = $('#categoryFilter').val();
        let resultsFound = false;
        console.log(`Applying filters - Search: '${searchTerm}', Category: '${selectedCategory}'`);
        $('.product-card').each(function() {
            const card = $(this);
            const productName = card.data('name').toLowerCase();
            const productCategory = card.data('category');
            const categoryMatch = (selectedCategory === '' || productCategory === selectedCategory);
            const searchMatch = (searchTerm === '' || productName.includes(searchTerm));
            if (categoryMatch && searchMatch) { card.show(); resultsFound = true; } else { card.hide(); }
        });
        $('.category-section').each(function() { $(this).toggle($(this).find('.product-card:visible').length > 0); });
        $('#no-results-message').toggle(!resultsFound);
        updateFilterDisplay();
    }
    $('#searchInput').on('input', debounce(applyFilters, 300));
    $('#categoryFilter').on('change', applyFilters);
    $('#clearSearch').on('click', function() { $('#searchInput').val('').trigger('input'); });
    $('#clearFilters').on('click', function() { $('#searchInput').val(''); $('#categoryFilter').val(''); applyFilters(); showGlobalPopup('All filters cleared'); });
    function debounce(func, wait) { let timeout; return function executedFunction(...args) { const later = () => { clearTimeout(timeout); func(...args); }; clearTimeout(timeout); timeout = setTimeout(later, wait); }; }

    // --- Global Popup Function ---
    // (This remains the same)
    function showGlobalPopup(message, isError = false) {
        let popup = $('#globalPopup');
        if (!popup.length) { console.error("Global popup element #globalPopup not found."); alert(message); return; }
        popup.removeClass('alert-success alert-danger').addClass(isError ? 'alert-danger' : 'alert-success');
        popup.text(message);
        popup.stop(true, true).fadeIn(200).delay(3000).fadeOut(400);
    }
    window.showGlobalPopup = showGlobalPopup;

    // Initialize Bootstrap tooltips
    $('[data-toggle="tooltip"]').tooltip();

}); // End $(document).ready()
</script>
<!-- **** END: Updated JavaScript Block **** -->

<?php
// Include the footer
require_once 'footer.php'; // Verify this path
?>