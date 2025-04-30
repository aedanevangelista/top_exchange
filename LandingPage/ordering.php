<?php
// Start the session
session_start();

// Prevent caching of the page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Initialize the cart in the session if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Include database connection file
include_once('db_connection.php');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get filter parameters
$category = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$min_price = isset($_GET['min_price']) ? $_GET['min_price'] : '';
$max_price = isset($_GET['max_price']) ? $_GET['max_price'] : '';

// Function to get the base product name (remove variant identifiers)
function getBaseProductName($productName) {
    // Remove variant identifiers like (A), (B), etc.
    return preg_replace('/\s*\([A-Z][^)]*\)$/', '', $productName);
}

// Build the SQL query to get products
$sql = "SELECT p.product_id, p.product_name, p.item_description, p.price, p.product_image AS image_path, p.packaging, p.category 
        FROM products p 
        WHERE 1=1";
$params = [];
$types = '';

// Add filters to the query
if (!empty($category)) {
    $sql .= " AND p.category = ?";
    $params[] = $category;
    $types .= 's';
}

if (!empty($search)) {
    $sql .= " AND (p.item_description LIKE ? OR p.category LIKE ? OR p.product_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sss';
}

if (!empty($min_price) && is_numeric($min_price)) {
    $sql .= " AND p.price >= ?";
    $params[] = $min_price;
    $types .= 'd';
}

if (!empty($max_price) && is_numeric($max_price)) {
    $sql .= " AND p.price <= ?";
    $params[] = $max_price;
    $types .= 'd';
}

// Prepare and execute the query
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Group products by their base name to handle variants
$groupedProducts = [];
while ($product = $result->fetch_assoc()) {
    if (empty($product['product_name'])) {
        // Use item_description as product_name if product_name is empty
        $product['product_name'] = getBaseProductName($product['item_description']);
    }
    
    // Use product_name as the grouping key
    $productName = $product['product_name'];
    
    if (!isset($groupedProducts[$productName])) {
        $groupedProducts[$productName] = [
            'main_product' => $product,
            'variants' => []
        ];
    } else {
        // Add as a variant
        $groupedProducts[$productName]['variants'][] = $product;
    }
}

// Fetch available categories
$category_result = $conn->query("SELECT DISTINCT category FROM products");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Products | Top Exchange Food Corp</title>
    <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="icon" href="images/fevicon.png" type="image/gif" />
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
    <!-- fontawesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/admin/css/all.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/admin/css/all.min.css">
    <style>
        :root {
            --primary-color:#9a7432;
            --secondary-color: #e74c3c;
            --accent-color: #f39c12;
            --light-bg: #f8f9fa;
            --dark-text: #2c3e50;
            --light-text: #7f8c8d;
            --white: #ffffff;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        /* Custom Popup Styles */
        .custom-popup {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: var(--accent-color);
            color: var(--white);
            padding: 15px 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            z-index: 9999;
            display: none;
            animation: slideIn 0.5s forwards, fadeOut 0.5s forwards 2.5s;
            max-width: 300px;
            font-size: 14px;
        }

        .custom-popup.error {
            background-color: var(--secondary-color);
        }

        @keyframes slideIn {
            from { right: -100%; opacity: 0; }
            to { right: 20px; opacity: 1; }
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        /* Cart badge styling */
        .badge-danger {
            background-color: var(--secondary-color);
            color: var(--white);
            border-radius: 50%;
            padding: 3px 6px;
            font-size: 12px;
            position: relative;
            top: -10px;
            left: -5px;
        }
        
        /* Filter section styling */
        .filter-section {
            background-color: var(--white);
            padding: 25px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
        }
        
        .filter-section h2 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        .filter-group {
            margin-bottom: 20px;
        }
        
        .filter-group label {
            font-weight: 500;
            margin-bottom: 8px;
            display: block;
            color: var(--dark-text);
            font-size: 0.9rem;
        }
        
        .form-control, .form-select {
            border-radius: var(--border-radius);
            border: 1px solid #ddd;
            padding: 10px 15px;
            font-size: 0.9rem;
            transition: var(--transition);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(243, 156, 18, 0.25);
        }
        
        .price-range-inputs {
            display: flex;
            gap: 15px;
        }
        
        .price-range-inputs input {
            flex: 1;
        }
        
        .filter-actions {
            margin-top: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            border-radius: var(--border-radius);
            padding: 10px 20px;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #1a252f;
            border-color: #1a252f;
        }
        
        .btn-outline-secondary {
            color: var(--primary-color);
            border-color: #ddd;
        }
        
        .btn-outline-secondary:hover {
            background-color: var(--light-bg);
            border-color: #ccc;
        }
        
        .search-box {
            position: relative;
            margin-bottom: 25px;
        }
        
        .search-box .fas {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--light-text);
        }
        
        .search-box input {
            padding-left: 45px;
            border-radius: var(--border-radius);
        }
        
        /* Product cards */
        .cream_box {
            background: var(--white);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            margin-bottom: 25px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .cream_box:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }
        
        .cream_img {
            height: 200px;
            overflow: hidden;
            position: relative;
        }
        
        .cream_img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }
        
        .cream_box:hover .cream_img img {
            transform: scale(1.05);
        }
        
        .price_text {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: var(--accent-color);
            color: var(--white);
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .cream_box_content {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .strawberry_text {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }
        
        .cream_text {
            color: var(--light-text);
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .cart_bt {
            margin-top: auto;
        }
        
        .add-to-cart, .login-to-order {
            display: block;
            text-align: center;
            padding: 10px;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: var(--transition);
        }
        
        .add-to-cart {
            background-color: var(--primary-color);
            color: var(--white);
            border: 1px solid var(--primary-color);
        }
        
        .add-to-cart:hover {
            background-color: #1a252f;
            color: var(--white);
        }
        
        .login-to-order {
            background-color: var(--light-bg);
            color: var(--dark-text);
            border: 1px solid #ddd;
        }
        
        .login-to-order:hover {
            background-color: #e9ecef;
            color: var(--dark-text);
        }
        
        /* No results styling */
        .no-results {
            text-align: center;
            padding: 50px 20px;
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }
        
        .no-results i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .no-results h4 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .no-results p {
            color: var(--light-text);
        }
        
        /* Loading spinner */
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
            color: var(--primary-color);
        }
        
        /* Accessibility improvements */
        a:focus, button:focus, input:focus, select:focus {
            outline: 3px solid var(--accent-color);
            outline-offset: 2px;
        }
        
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .price-range-inputs {
                flex-direction: column;
                gap: 10px;
            }
            
            .filter-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .cream_img {
                height: 160px;
            }
        }
        
        /* Variant selector styling */
        .variant-selector {
            margin-bottom: 15px;
        }
        
        .variant-selector select {
            width: 100%;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            border: 1px solid #ddd;
            background-color: var(--white);
            color: var(--dark-text);
            font-size: 0.9rem;
            transition: var(--transition);
        }
        
        .variant-selector select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(243, 156, 18, 0.25);
        }
        
        /* Price range slider */
        .price-slider-container {
            margin-top: 10px;
        }
        
        .price-slider {
            width: 100%;
            height: 5px;
            border-radius: 5px;
            background: #ddd;
            outline: none;
            -webkit-appearance: none;
        }
        
        .price-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--primary-color);
            cursor: pointer;
        }
        
        .price-slider::-moz-range-thumb {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--primary-color);
            cursor: pointer;
        }
        
        .price-slider-values {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            font-size: 0.8rem;
            color: var(--light-text);
        }
    </style>
</head>
<body>

<!-- Include external header -->
<?php include('header.php'); ?>

<?php if (isset($_SESSION['username'])): ?>
<!-- Cart Modal -->
<div class="modal fade" id="cartModal" tabindex="-1" role="dialog" aria-labelledby="cartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cartModalLabel">Your Shopping Cart</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="empty-cart-message" class="text-center py-4" style="display: <?php echo empty($_SESSION['cart']) ? 'block' : 'none'; ?>;">
                    <i class="fas fa-shopping-cart fa-4x mb-3" style="color: #ddd;"></i>
                    <h4>Your cart is empty</h4>
                    <p>Start shopping to add items to your cart</p>
                </div>
                <div id="cart-items-container" style="display: <?php echo empty($_SESSION['cart']) ? 'none' : 'block'; ?>;">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 120px;">Product</th>
                                    <th>Description</th>
                                    <th style="width: 100px;">Price</th>
                                    <th style="width: 150px;">Quantity</th>
                                    <th style="width: 100px;">Subtotal</th>
                                    <th style="width: 40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="cart-items-list">
                                <?php if (!empty($_SESSION['cart'])): ?>
                                    <?php 
                                    $subtotal = 0;
                                    foreach ($_SESSION['cart'] as $productId => $item): 
                                        $itemSubtotal = $item['price'] * $item['quantity'];
                                        $subtotal += $itemSubtotal;
                                    ?>
                                        <tr>
                                            <td>
                                                <img src="<?php echo htmlspecialchars($item['image_path'] ?? 'images/default-product.jpg'); ?>" 
                                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                     style="width: 80px; height: 80px; object-fit: cover;">
                                            </td>
                                            <td>
                                                <h6><?php echo htmlspecialchars($item['name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($item['packaging'] ?? ''); ?></small>
                                            </td>
                                            <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                            <td>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <button class="btn btn-outline-secondary decrease-quantity" 
                                                                type="button" 
                                                                data-product-id="<?php echo $productId; ?>"
                                                                aria-label="Decrease quantity">
                                                            <i class="fas fa-minus"></i>
                                                        </button>
                                                    </div>
                                                    <input type="text" 
                                                           class="form-control text-center quantity-input" 
                                                           value="<?php echo $item['quantity']; ?>" 
                                                           readonly
                                                           aria-label="Quantity">
                                                    <div class="input-group-append">
                                                        <button class="btn btn-outline-secondary increase-quantity" 
                                                                type="button" 
                                                                data-product-id="<?php echo $productId; ?>"
                                                                aria-label="Increase quantity">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>₱<?php echo number_format($itemSubtotal, 2); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-danger remove-from-cart" 
                                                        data-product-id="<?php echo $productId; ?>"
                                                        aria-label="Remove item">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="special-instructions">Special Instructions</label>
                                <textarea class="form-control" id="special-instructions" rows="3" placeholder="Any special requests or notes for your order..."></textarea>
                            </div>
                        </div>
                        <div class="col-md-6 text-right">
                            <div class="order-summary">
                                <h5>Order Summary</h5>
                                <div class="d-flex justify-content-between">
                                    <span>Subtotal:</span>
                                    <span id="subtotal-amount">₱<?php echo number_format($subtotal ?? 0, 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Delivery Fee:</span>
                                    <span id="delivery-fee">₱<?php echo number_format(($subtotal ?? 0) > 500 ? 0 : 50, 2); ?></span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <strong>Total:</strong>
                                    <strong id="total-amount">₱<?php echo number_format(($subtotal ?? 0) + (($subtotal ?? 0) > 500 ? 0 : 50), 2); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Continue Shopping</button>
                <button type="button" class="btn btn-primary" id="checkout-button">Proceed to Checkout</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="cream_section layout_padding">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <h1 class="cream_taital">Our Products</h1>
                <p class="cream_text">Discover our high-quality selection</p>
            </div>
        </div>
        
        <!-- Filter and Search Section -->
        <div class="filter-section">
            <h2><i class="fas fa-sliders-h me-2"></i>Filter Products</h2>
            <div class="row">
                <div class="col-md-12 search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="search-input" class="form-control" placeholder="Search products by name or category..." 
                           value="<?php echo htmlspecialchars($search); ?>" aria-label="Search products">
                </div>
                
                <div class="col-md-4 col-lg-3 filter-group">
                    <label for="category-filter"><i class="fas fa-tags me-2"></i>Category</label>
                    <select id="category-filter" class="form-select">
                        <option value="">All Categories</option>
                        <?php while ($row = $category_result->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($row['category']) ?>" <?= $category == $row['category'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($row['category']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-8 col-lg-6 filter-group">
                    <label><span class="me-2">₱</span>Price Range</label>
                    <div class="price-slider-container">
                        <input type="range" class="price-slider" id="price-range" min="0" max="1000" step="10" 
                               value="<?php echo !empty($max_price) ? $max_price : 1000; ?>">
                        <div class="price-slider-values">
                            <span id="min-price-value">₱0</span>
                            <span id="max-price-value">₱<?php echo !empty($max_price) ? $max_price : '1000'; ?></span>
                        </div>
                    </div>
                    <div class="price-range-inputs mt-2">
                        <input type="number" id="min-price-input" class="form-control" placeholder="Min price" 
                               value="<?php echo htmlspecialchars($min_price); ?>" aria-label="Minimum price" min="0">
                        <input type="number" id="max-price-input" class="form-control" placeholder="Max price" 
                               value="<?php echo htmlspecialchars($max_price); ?>" aria-label="Maximum price" min="0">
                    </div>
                </div>
                
                <div class="col-md-12 filter-actions">
                    <button type="button" id="reset-filters" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Reset Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading Spinner -->
        <div class="loading-spinner" id="loading-spinner">
            <div class="spinner-border" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p class="mt-2">Loading products...</p>
        </div>

        <!-- Products Container -->
        <div class="cream_section_2" id="products-container">
            <div class="row">
                <?php if (!empty($groupedProducts)): ?>
                    <?php foreach ($groupedProducts as $productName => $productGroup) { 
                        $mainProduct = $productGroup['main_product'];
                        $variants = $productGroup['variants'];
                        $hasVariants = !empty($variants);
                    ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="cream_box">
                                <div class="cream_img">
                                    <img src="<?php echo htmlspecialchars($mainProduct['image_path'] ?? 'images/default-product.jpg'); ?>" alt="<?php echo htmlspecialchars($productName); ?>" id="product-image-<?php echo $mainProduct['product_id']; ?>">
                                    <div class="price_text" id="product-price-<?php echo $mainProduct['product_id']; ?>">
                                        ₱<?php echo isset($mainProduct['price']) ? number_format($mainProduct['price'], 2) : '0.00'; ?>
                                    </div>
                                </div>
                                <div class="cream_box_content">
                                    <h6 class="strawberry_text">
                                        <?php echo htmlspecialchars($productName); ?>
                                    </h6>
                                    <p class="cream_text" id="product-packaging-<?php echo $mainProduct['product_id']; ?>">
                                        <i class="fas fa-box me-2"></i>Packaging: <?php echo isset($mainProduct['packaging']) ? htmlspecialchars($mainProduct['packaging']) : 'N/A'; ?>
                                    </p>
                                    
                                    <?php if ($hasVariants): ?>
                                    <div class="variant-selector">
                                        <label for="variant-select-<?php echo $mainProduct['product_id']; ?>">Select Variant:</label>
                                        <select id="variant-select-<?php echo $mainProduct['product_id']; ?>" class="form-select variant-dropdown" 
                                                data-product-container="product-container-<?php echo $mainProduct['product_id']; ?>">
                                            <option value="<?php echo $mainProduct['product_id']; ?>" 
                                                    data-price="<?php echo $mainProduct['price']; ?>"
                                                    data-packaging="<?php echo htmlspecialchars($mainProduct['packaging']); ?>"
                                                    data-image="<?php echo htmlspecialchars($mainProduct['image_path'] ?? 'images/default-product.jpg'); ?>"
                                                    data-name="<?php echo htmlspecialchars($mainProduct['item_description']); ?>" selected>
                                                <?php echo htmlspecialchars($mainProduct['item_description']); ?> - ₱<?php echo number_format($mainProduct['price'], 2); ?>
                                            </option>
                                            <?php foreach ($variants as $variant): ?>
                                            <option value="<?php echo $variant['product_id']; ?>" 
                                                    data-price="<?php echo $variant['price']; ?>"
                                                    data-packaging="<?php echo htmlspecialchars($variant['packaging']); ?>"
                                                    data-image="<?php echo htmlspecialchars($variant['image_path'] ?? 'images/default-product.jpg'); ?>"
                                                    data-name="<?php echo htmlspecialchars($variant['item_description']); ?>">
                                                <?php echo htmlspecialchars($variant['item_description']); ?> - ₱<?php echo number_format($variant['price'], 2); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="cart_bt">
                                        <?php if (isset($_SESSION['username'])): ?>
                                            <a href="#" class="add-to-cart" 
                                                data-product-id="<?php echo $mainProduct['product_id']; ?>" 
                                                data-product-name="<?php echo htmlspecialchars($mainProduct['item_description']); ?>" 
                                                data-product-price="<?php echo $mainProduct['price']; ?>" 
                                                data-image-path="<?php echo htmlspecialchars($mainProduct['image_path'] ?? 'images/default-product.jpg'); ?>" 
                                                data-packaging="<?php echo htmlspecialchars($mainProduct['packaging']); ?>"
                                                id="add-to-cart-<?php echo $mainProduct['product_id']; ?>">
                                                <i class="fas fa-cart-plus me-2"></i>Add To Cart
                                            </a>
                                        <?php else: ?>
                                            <a href="login.php" class="login-to-order">
                                                <i class="fas fa-sign-in-alt me-2"></i>Login to Order
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                <?php else: ?>
                    <div class="col-md-12">
                        <div class="no-results">
                            <i class="fas fa-search fa-4x"></i>
                            <h4>No products found</h4>
                            <p>We couldn't find any products matching your criteria. Try adjusting your filters.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Custom Popup Message -->
<div id="customPopup" class="custom-popup" role="alert" aria-live="assertive">
    <div class="popup-content">
        <span id="popupMessage"></span>
    </div>
</div>

<!-- Include external footer -->
<?php include('footer.php'); ?>

<?php $conn->close(); ?>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.bundle.min.js"></script>
<script>
    // Function to show custom popup message
    function showPopup(message, isError = false) {
        const popup = $('#customPopup');
        const popupMessage = $('#popupMessage');
        
        popupMessage.text(message);
        popup.removeClass('error');
        
        if (isError) {
            popup.addClass('error');
        }
        
        // Reset animation by briefly showing/hiding
        popup.hide().show();
        
        // Automatically hide after 3 seconds
        setTimeout(() => {
            popup.hide();
        }, 3000);
    }

    // Function to update URL parameters without reloading
    function updateUrlParams(params) {
        const url = new URL(window.location.href);
        Object.keys(params).forEach(key => {
            if (params[key]) {
                url.searchParams.set(key, params[key]);
            } else {
                url.searchParams.delete(key);
            }
        });
        window.history.pushState({}, '', url);
    }

    // Function to fetch filtered products
    function fetchFilteredProducts() {
        const search = $('#search-input').val();
        const category = $('#category-filter').val();
        const minPrice = $('#min-price-input').val();
        const maxPrice = $('#max-price-input').val();
        
        // Show loading spinner
        $('#loading-spinner').show();
        $('#products-container').hide();
        
        // Update URL parameters
        updateUrlParams({
            search: search,
            category: category,
            min_price: minPrice,
            max_price: maxPrice
        });
        
        $.ajax({
            url: 'fetch_products.php',
            type: 'GET',
            data: {
                search: search,
                category: category,
                min_price: minPrice,
                max_price: maxPrice
            },
            success: function(response) {
                $('#products-container').html(response);
                $('#loading-spinner').hide();
                $('#products-container').fadeIn();
            },
            error: function(xhr, status, error) {
                console.error("Error fetching products:", error);
                $('#loading-spinner').hide();
                $('#products-container').html(`
                    <div class="col-md-12">
                        <div class="no-results">
                            <i class="fas fa-exclamation-triangle fa-4x"></i>
                            <h4>Error loading products</h4>
                            <p>There was an error loading the products. Please try again.</p>
                        </div>
                    </div>
                `).fadeIn();
            }
        });
    }

    // Price range slider functionality
    function updatePriceSlider() {
        const minPrice = $('#min-price-input').val() || 0;
        const maxPrice = $('#max-price-input').val() || 1000;
        
        // Update slider values
        $('#price-range').attr('min', minPrice);
        $('#price-range').attr('max', maxPrice > 1000 ? maxPrice : 1000);
        $('#price-range').val(maxPrice);
        $('#max-price-value').text('₱' + maxPrice);
        $('#min-price-value').text('₱' + minPrice);
    }

    $(document).ready(function() {
        // Initialize price slider
        updatePriceSlider();
        
        // Set up event listeners for real-time filtering
        let searchTimer;
        $('#search-input').on('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(fetchFilteredProducts, 500);
        });
        
        $('#category-filter').on('change', fetchFilteredProducts);
        
        // Price range inputs
        $('#min-price-input, #max-price-input').on('input', function() {
            updatePriceSlider();
            fetchFilteredProducts();
        });
        
        // Price slider
        $('#price-range').on('input', function() {
            $('#max-price-input').val($(this).val());
            $('#max-price-value').text('₱' + $(this).val());
        });
        
        $('#price-range').on('change', function() {
            fetchFilteredProducts();
        });
        
        // Reset filters
        $('#reset-filters').on('click', function() {
            $('#search-input').val('');
            $('#category-filter').val('');
            $('#min-price-input').val('');
            $('#max-price-input').val('');
            updatePriceSlider();
            fetchFilteredProducts();
        });
        
        // Add to cart handler
        $(document).on('click', '.add-to-cart', function(e) {
            e.preventDefault();

            const productId = $(this).data('product-id');
            const productName = $(this).data('product-name');
            const productPrice = $(this).data('product-price');
            const imagePath = $(this).data('image-path');
            const packaging = $(this).data('packaging');

            $.ajax({
                url: 'add_to_cart.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    product_id: productId,
                    product_name: productName,
                    product_price: productPrice,
                    image_path: imagePath,
                    packaging: packaging
                },
                success: function(response) {
                    if(response.success) {
                        $('#cart-count').text(response.cart_count);
                        showPopup(productName + " added to cart!");
                        
                        // Update cart modal if it's open
                        if ($('#cartModal').hasClass('show')) {
                            updateCartModal();
                        }
                    } else {
                        showPopup(response.message || "Error adding to cart", true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error adding product to cart:", error);
                    showPopup("Error adding product to cart.", true);
                }
            });
        });

        // Variant selection handler
        $(document).on('change', '.variant-dropdown', function() {
            const selectedOption = $(this).find('option:selected');
            const mainProductId = $(this).attr('id').replace('variant-select-', '');
            
            const variantId = selectedOption.val();
            const variantName = selectedOption.data('name');
            const variantPrice = selectedOption.data('price');
            const variantPackaging = selectedOption.data('packaging');
            const variantImage = selectedOption.data('image');
            
            // Update price text
            $('#product-price-' + mainProductId).text('₱' + parseFloat(variantPrice).toFixed(2));
            
            // Update packaging display
            $('#product-packaging-' + mainProductId).html('<i class="fas fa-box me-2"></i>Packaging: ' + variantPackaging);
            
            // Update image if exists
            if (variantImage) {
                $('#product-image-' + mainProductId).attr('src', variantImage);
            }
            
            // Update add to cart button with variant data
            $('#add-to-cart-' + mainProductId)
                .data('product-id', variantId)
                .data('product-name', variantName)
                .data('product-price', variantPrice)
                .data('packaging', variantPackaging)
                .data('image-path', variantImage);
        });

        // Quantity adjustment handlers
        $(document).on('click', '.increase-quantity', function() {
            const productId = $(this).data('product-id');
            updateCartItemQuantity(productId, 1);
        });

        $(document).on('click', '.decrease-quantity', function() {
            const productId = $(this).data('product-id');
            updateCartItemQuantity(productId, -1);
        });

        // Remove item handler
        $(document).on('click', '.remove-from-cart', function() {
            const productId = $(this).data('product-id');
            removeCartItem(productId);
        });

        // Checkout button handler
        $(document).on('click', '#checkout-button', function() {
            const specialInstructions = $('#special-instructions').val();
            sessionStorage.setItem('specialInstructions', specialInstructions);
            $('#cartModal').modal('hide');
            window.location.href = 'checkout.php';
        });

        // Function to update cart item quantity
        function updateCartItemQuantity(productId, change) {
            $.ajax({
                url: 'update_cart_item.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    product_id: productId,
                    quantity_change: change
                },
                success: function(response) {
                    if(response.success) {
                        $('#cart-count').text(response.cart_count);
                        updateCartModal();
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
            $.ajax({
                url: 'remove_cart_item.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    product_id: productId
                },
                success: function(response) {
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
            $.ajax({
                url: 'fetch_cart_items.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
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
                                            <img src="${item.image_path || 'images/default-product.jpg'}" 
                                                 alt="${item.name}" 
                                                 style="width: 80px; height: 80px; object-fit: cover;">
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
                                                            data-product-id="${item.product_id}"
                                                            aria-label="Decrease quantity">
                                                        <i class="fas fa-minus"></i>
                                                    </button>
                                                </div>
                                                <input type="text" 
                                                       class="form-control text-center quantity-input" 
                                                       value="${item.quantity}" 
                                                       readonly
                                                       aria-label="Quantity">
                                                <div class="input-group-append">
                                                    <button class="btn btn-outline-secondary increase-quantity" 
                                                            type="button" 
                                                            data-product-id="${item.product_id}"
                                                            aria-label="Increase quantity">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                        <td>₱${itemSubtotal.toFixed(2)}</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-danger remove-from-cart" 
                                                    data-product-id="${item.product_id}"
                                                    aria-label="Remove item">
                                                <i class="fas fa-trash"></i>
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
</script>
</body>
</html>