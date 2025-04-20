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
    <!-- basic -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- mobile metas -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="viewport" content="initial-scale=1, maximum-scale=1">
    <!-- site metas -->
    <title>Products | Top Exchange Food Corp</title> 
    <meta name="keywords" content="Filipino food, siopao, siomai, noodles, sauces, food supplier, Philippines">
    <meta name="description" content="Browse our premium selection of Filipino food products - siopao, siomai, noodles, and sauces from Top Exchange Food Corp.">
    <meta name="author" content="Top Food Exchange Corp.">
    <!-- bootstrap css -->
    <link rel="stylesheet" type="text/css" href="/LandingPage/css/bootstrap.min.css">
    <!-- style css -->
    <link rel="stylesheet" type="text/css" href="/LandingPage/css/style.css">
    <!-- Responsive-->
    <link rel="stylesheet" href="/LandingPage/css/responsive.css">
    <!-- fevicon -->
    <link rel="icon" href="/LandingPage/images/fevicon.png" type="image/gif" />
    <!-- font css -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&display=swap" rel="stylesheet">
    <!-- fontawesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        /* Primary brand colors and variables */
        :root {
            --primary-color: #9a7432;
            --primary-hover: #b08a3e;
            --secondary-color: #333;
            --light-color: #f8f9fa;
            --dark-color: #222;
            --accent-color: #dc3545;
            --section-padding: 100px 0;
            --box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
            --border-radius: 10px;
        }

        /* Custom Popup Styles */
        .custom-popup {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: var(--primary-color);
            color: white;
            padding: 15px 25px;
            border-radius: 4px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            z-index: 9999;
            display: none;
            animation: slideIn 0.5s forwards, fadeOut 0.5s forwards 2.5s;
            max-width: 300px;
        }

        .popup-content {
            display: flex;
            align-items: center;
        }

        .custom-popup.error {
            background-color: var(--accent-color);
        }

        @keyframes slideIn {
            from { right: -100%; opacity: 0; }
            to { right: 20px; opacity: 1; }
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        /* Cart count badge */
        .badge-danger {
            background-color: var(--accent-color);
            color: white;
            border-radius: 50%;
            padding: 3px 6px;
            font-size: 12px;
            position: relative;
            top: -10px;
            left: -5px;
        }

        /* Filter section styling */
        .filter-section {
            background-color: white;
            padding: 30px;
            border-radius: var(--border-radius);
            margin-bottom: 40px;
            box-shadow: var(--box-shadow);
        }
        
        .filter-section h2 {
            color: var(--primary-color);
            margin-bottom: 25px;
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        .filter-group {
            margin-bottom: 20px;
        }
        
        .filter-group label {
            font-weight: 500;
            margin-bottom: 10px;
            display: block;
            color: var(--secondary-color);
        }
        
        .form-control, .form-select {
            border-radius: var(--border-radius);
            border: 1px solid #ddd;
            padding: 10px 15px;
            transition: var(--transition);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(154, 116, 50, 0.25);
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
        }
        
        .btn {
            border-radius: 50px;
            padding: 10px 25px;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(154, 116, 50, 0.3);
        }
        
        .btn-outline-secondary {
            color: var(--primary-color);
            border-color: #ddd;
        }
        
        .btn-outline-secondary:hover {
            background-color: var(--light-color);
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
            color: #777;
        }
        
        .search-box input {
            padding-left: 45px;
            border-radius: var(--border-radius);
        }
        
        /* Product cards */
        .product-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: visible; /* Changed from hidden to fix dropdown */
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            margin-bottom: 30px;
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .product-img {
            height: 220px;
            overflow: hidden;
            position: relative;
        }
        
        .product-img img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.5s ease;
        }
        
        .product-card:hover .product-img img {
            transform: scale(1.1);
        }
        
        .product-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: var(--accent-color);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .product-body {
            padding: 25px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .product-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .product-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .product-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .product-rating {
            color: #ffc107;
            margin-bottom: 15px;
        }
        
        .product-btn {
            margin-top: auto;
        }
        
        .add-to-cart, .login-to-order {
            display: block;
            text-align: center;
            padding: 10px;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: var(--transition);
            width: 100%;
        }
        
        .add-to-cart {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }
        
        .add-to-cart:hover {
            background-color: var(--primary-hover);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(154, 116, 50, 0.3);
        }
        
        .login-to-order {
            background-color: var(--light-color);
            color: var(--secondary-color);
            border: 1px solid #ddd;
        }
        
        .login-to-order:hover {
            background-color: #e9ecef;
            color: var(--secondary-color);
            text-decoration: none;
        }
        
        /* No results styling */
        .no-results {
            text-align: center;
            padding: 50px 20px;
            background-color: white;
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
            color: #666;
        }
        
        /* Variant selector styling - FIXED */
        .variant-selector {
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }
        
        .variant-selector select {
            width: 100%;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            border: 1px solid #ddd;
            background-color: white;
            color: var(--secondary-color);
            font-size: 0.9rem;
            transition: var(--transition);
            z-index: 1;
            position: relative;
        }
        
        /* Fix for dropdown options visibility */
        .variant-selector select:focus {
            z-index: 1000;
            position: relative;
        }
        
        /* Ensure dropdown options appear above other elements */
        .variant-selector option {
            position: relative;
            z-index: 1001;
            background: white;
            padding: 8px 12px;
        }
        
        .variant-selector select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(154, 116, 50, 0.25);
        }
        
        /* Section title styling */
        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--primary-color);
        }
        
        .section-subtitle {
            font-size: 1.2rem;
            color: #777;
            margin-bottom: 50px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .filter-section {
                padding: 20px;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .product-img {
                height: 180px;
            }
            
            .price-range-inputs {
                flex-direction: column;
            }
            
            .section-title {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 576px) {
            .product-img {
                height: 160px;
            }
            
            .product-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="header_section">
        <div class="container">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <a class="navbar-brand" href="index.php"><img src="/LandingPage/images/resized_food_corp_logo.png" alt="Top Food Exchange Corp. Logo"></a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="/LandingPage/index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/LandingPage/about.php">About</a>
                        </li>
                        <li class="nav-item active">
                            <a class="nav-link" href="/LandingPage/ordering.php">Products</a>
                        </li>        
                        <li class="nav-item">
                            <a class="nav-link" href="/LandingPage/contact.php">Contact Us</a>
                        </li>
                    </ul>
                    <form class="form-inline my-2 my-lg-0">
                        <div class="login_bt">
                            <?php if (isset($_SESSION['username'])): ?>
                                <a href="#" class="cart-button" data-toggle="modal" data-target="#cartModal">
                                    <span style="color: #222222;"><i class="fa fa-shopping-cart" aria-hidden="true"></i></span>
                                    <span id="cart-count" class="badge badge-danger"><?php echo array_sum(array_column($_SESSION['cart'], 'quantity')); ?></span>
                                </a>
                                <a href="/LandingPage/logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>) 
                                    <span style="color: #222222;"><i class="fa fa-sign-out" aria-hidden="true"></i></span>
                                </a>
                            <?php else: ?>
                                <a href="/LandingPage/login.php">Login 
                                    <span style="color: #222222;"><i class="fa fa-user" aria-hidden="true"></i></span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </nav>
        </div>
        
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
                            <i class="fa fa-shopping-cart fa-4x mb-3" style="color: #ddd;"></i>
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
                                                        <img src="<?php echo htmlspecialchars($item['image_path'] ?? '/LandingPage/images/default-product.jpg'); ?>" 
                                                             style="width: 80px; height: 80px; object-fit: contain;">
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
                                                                        data-product-id="<?php echo $productId; ?>">
                                                                    <i class="fa fa-minus"></i>
                                                                </button>
                                                            </div>
                                                            <input type="text" 
                                                                   class="form-control text-center quantity-input" 
                                                                   value="<?php echo $item['quantity']; ?>" 
                                                                   readonly>
                                                            <div class="input-group-append">
                                                                <button class="btn btn-outline-secondary increase-quantity" 
                                                                        type="button" 
                                                                        data-product-id="<?php echo $productId; ?>">
                                                                    <i class="fa fa-plus"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>₱<?php echo number_format($itemSubtotal, 2); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-danger remove-from-cart" 
                                                                data-product-id="<?php echo $productId; ?>">
                                                            <i class="fa fa-trash"></i>
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
        
        <!-- Custom Popup Message -->
        <div id="customPopup" class="custom-popup">
            <div class="popup-content">
                <span id="popupMessage"></span>
            </div>
        </div>
    </div>
    
    <!-- Products Section -->
    <div class="cream_section layout_padding" style="padding: var(--section-padding); background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);">
        <div class="container">
            <div class="row mb-5">
                <div class="col-md-12 text-center">
                    <h1 class="section-title" data-aos="fade-up">Our Products</h1>
                    <p class="section-subtitle" data-aos="fade-up" data-aos-delay="200">Premium quality Filipino food products</p>
                </div>
            </div>
            
            <!-- Filter and Search Section -->
            <div class="filter-section" data-aos="fade-up">
                <h2><i class="fas fa-sliders-h me-2"></i>Filter Products</h2>
                <form id="filterForm" method="GET" action="">
                    <div class="row">
                        <div class="col-md-12 search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" id="searchInput" class="form-control" placeholder="Search products by name or category..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="col-md-4 filter-group">
                            <label for="category-filter"><i class="fas fa-tags me-2"></i>Category</label>
                            <select name="category" id="category-filter" class="form-select">
                                <option value="">All Categories</option>
                                <?php while ($row = $category_result->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($row['category']) ?>" <?= $category == $row['category'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($row['category']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4 filter-group">
                            <label><i class="fas fa-tag me-2"></i>Price Range</label>
                            <div class="price-range-inputs">
                                <input type="number" name="min_price" id="minPrice" class="form-control" placeholder="Min price" value="<?php echo htmlspecialchars($min_price); ?>" min="0">
                                <input type="number" name="max_price" id="maxPrice" class="form-control" placeholder="Max price" value="<?php echo htmlspecialchars($max_price); ?>" min="0">
                            </div>
                        </div>
                        
                        <div class="col-md-4 filter-group d-flex align-items-end">
                            <div class="filter-actions w-100">
                                <a href="ordering.php" class="btn btn-outline-secondary w-100 mt-2">
                                    <i class="fas fa-times me-2"></i>Reset
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div id="productsContainer" class="row">
                <?php if (!empty($groupedProducts)): ?>
                    <?php foreach ($groupedProducts as $productName => $productGroup) { 
                        $mainProduct = $productGroup['main_product'];
                        $variants = $productGroup['variants'];
                        $hasVariants = !empty($variants);
                    ?>
                        <div class="col-md-6 col-lg-4 product-item" data-aos="fade-up" data-aos-delay="<?php echo (($loop->index % 3) + 1) * 100; ?>">
                            <div class="product-card">
                                <div class="product-img">
                                    <img src="<?php echo htmlspecialchars($mainProduct['image_path'] ?? '/LandingPage/images/default-product.jpg'); ?>" alt="<?php echo htmlspecialchars($productName); ?>" id="product-image-<?php echo $mainProduct['product_id']; ?>">
                                    <div class="product-badge" id="product-price-<?php echo $mainProduct['product_id']; ?>">
                                        ₱<?php echo isset($mainProduct['price']) ? number_format($mainProduct['price'], 2) : '0.00'; ?>
                                    </div>
                                </div>
                                <div class="product-body">
                                    <h5 class="product-title"><?php echo htmlspecialchars($productName); ?></h5>
                                    <p class="product-description" id="product-packaging-<?php echo $mainProduct['product_id']; ?>">
                                        <i class="fas fa-box me-2"></i><?php echo isset($mainProduct['packaging']) ? htmlspecialchars($mainProduct['packaging']) : 'N/A'; ?>
                                    </p>
                                    
                                    <?php if ($hasVariants): ?>
                                    <div class="variant-selector">
                                        <label for="variant-select-<?php echo $mainProduct['product_id']; ?>">Select Variant:</label>
                                        <select id="variant-select-<?php echo $mainProduct['product_id']; ?>" class="form-select variant-dropdown">
                                            <option value="<?php echo $mainProduct['product_id']; ?>" 
                                                    data-price="<?php echo $mainProduct['price']; ?>"
                                                    data-packaging="<?php echo htmlspecialchars($mainProduct['packaging']); ?>"
                                                    data-image="<?php echo htmlspecialchars($mainProduct['image_path'] ?? '/LandingPage/images/default-product.jpg'); ?>"
                                                    data-name="<?php echo htmlspecialchars($mainProduct['item_description']); ?>" selected>
                                                <?php echo htmlspecialchars($mainProduct['item_description']); ?> - ₱<?php echo number_format($mainProduct['price'], 2); ?>
                                            </option>
                                            <?php foreach ($variants as $variant): ?>
                                            <option value="<?php echo $variant['product_id']; ?>" 
                                                    data-price="<?php echo $variant['price']; ?>"
                                                    data-packaging="<?php echo htmlspecialchars($variant['packaging']); ?>"
                                                    data-image="<?php echo htmlspecialchars($variant['image_path'] ?? '/LandingPage/images/default-product.jpg'); ?>"
                                                    data-name="<?php echo htmlspecialchars($variant['item_description']); ?>">
                                                <?php echo htmlspecialchars($variant['item_description']); ?> - ₱<?php echo number_format($variant['price'], 2); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="product-btn">
                                        <?php if (isset($_SESSION['username'])): ?>
                                            <a href="#" class="add-to-cart" 
                                                data-product-id="<?php echo $mainProduct['product_id']; ?>" 
                                                data-product-name="<?php echo htmlspecialchars($mainProduct['item_description']); ?>" 
                                                data-product-price="<?php echo $mainProduct['price']; ?>" 
                                                data-image-path="<?php echo htmlspecialchars($mainProduct['image_path'] ?? '/LandingPage/images/default-product.jpg'); ?>" 
                                                data-packaging="<?php echo htmlspecialchars($mainProduct['packaging']); ?>"
                                                id="add-to-cart-<?php echo $mainProduct['product_id']; ?>">
                                                <i class="fas fa-cart-plus me-2"></i>Add To Cart
                                            </a>
                                        <?php else: ?>
                                            <a href="/LandingPage/login.php" class="login-to-order">
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
                        <div class="no-results" data-aos="fade-up">
                            <i class="fas fa-search fa-4x"></i>
                            <h4>No products found</h4>
                            <p>We couldn't find any products matching your criteria. Try adjusting your filters.</p>
                            <a href="ordering.php" class="btn btn-primary mt-3">
                                <i class="fas fa-times me-2"></i>Clear all filters
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- copyright section start -->
    <div class="copyright_section">
        <div class="container">
            <p class="copyright_text">2025 All Rights Reserved. Design by STI Munoz Students</p>
        </div>
    </div>
    <!-- copyright section end -->
    
    <!-- Javascript files-->
    <script src="/LandingPage/js/jquery.min.js"></script>
    <script src="/LandingPage/js/popper.min.js"></script>
    <script src="/LandingPage/js/bootstrap.bundle.min.js"></script>
    <script src="/LandingPage/js/jquery-3.0.0.min.js"></script>
    <script src="/LandingPage/js/plugin.js"></script>
    <!-- AOS Animation -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <!-- sidebar -->
    <script src="/LandingPage/js/jquery.mCustomScrollbar.concat.min.js"></script>
    <script src="/LandingPage/js/custom.js"></script>
    
    <script>
        // Initialize AOS animation
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });
        
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

        // Function to update products based on filters
        function updateProducts() {
            const search = $('#searchInput').val();
            const category = $('#category-filter').val();
            const minPrice = $('#minPrice').val();
            const maxPrice = $('#maxPrice').val();
            
            // Show loading state
            $('#productsContainer').html('<div class="col-md-12 text-center py-5"><i class="fas fa-spinner fa-spin fa-3x"></i><p class="mt-3">Loading products...</p></div>');
            
            $.ajax({
                url: 'ordering.php',
                type: 'GET',
                data: {
                    search: search,
                    category: category,
                    min_price: minPrice,
                    max_price: maxPrice
                },
                success: function(response) {
                    // Extract the products container from the response
                    const temp = $('<div>').html(response);
                    const productsHtml = temp.find('#productsContainer').html();
                    
                    // Update the products container
                    $('#productsContainer').html(productsHtml);
                    
                    // Reinitialize AOS for new elements
                    AOS.refresh();
                    
                    // Update URL without reloading the page
                    const params = new URLSearchParams();
                    if (search) params.set('search', search);
                    if (category) params.set('category', category);
                    if (minPrice) params.set('min_price', minPrice);
                    if (maxPrice) params.set('max_price', maxPrice);
                    
                    const newUrl = params.toString() ? 'ordering.php?' + params.toString() : 'ordering.php';
                    window.history.replaceState(null, null, newUrl);
                },
                error: function(xhr, status, error) {
                    $('#productsContainer').html('<div class="col-md-12"><div class="no-results"><i class="fas fa-exclamation-triangle fa-4x"></i><h4>Error loading products</h4><p>Please try again later.</p></div></div>');
                    console.error("Error fetching filtered products:", error);
                }
            });
        }

        // Set up event listeners for live filtering
        $(document).ready(function() {
            // Search input with debounce
            let searchTimer;
            $('#searchInput').on('input', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(updateProducts, 500);
            });
            
            // Category filter
            $('#category-filter').on('change', updateProducts);
            
            // Price range filters with debounce
            let priceTimer;
            $('#minPrice, #maxPrice').on('input', function() {
                clearTimeout(priceTimer);
                priceTimer = setTimeout(updateProducts, 500);
            });
            
            // Reset button
            $('.btn-outline-secondary').on('click', function(e) {
                e.preventDefault();
                window.location.href = 'ordering.php';
            });
        });
    </script>

    <?php if (isset($_SESSION['username'])): ?>
    <script>
        // Function to update cart item quantity
        function updateCartItemQuantity(productId, change) {
            $.ajax({
                url: '/LandingPage/update_cart_item.php',
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
            $.ajax({
                url: '/LandingPage/remove_cart_item.php',
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
                url: '/LandingPage/fetch_cart_items.php',
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
                                            <img src="${item.image_path || '/LandingPage/images/default-product.jpg'}" 
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

        $(document).ready(function() {
            // Add to cart handler
            $(document).on('click', '.add-to-cart', function(e) {
                e.preventDefault();

                const productId = $(this).data('product-id');
                const productName = $(this).data('product-name');
                const productPrice = $(this).data('product-price');
                const imagePath = $(this).data('image-path');
                const packaging = $(this).data('packaging');

                $.ajax({
                    url: '/LandingPage/add_to_cart.php',
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
                
                // Update displayed information
                $('#product-price-' + mainProductId).text('₱' + parseFloat(variantPrice).toFixed(2));
                $('#product-packaging-' + mainProductId).html('<i class="fas fa-box me-2"></i>' + variantPackaging);
                
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
                window.location.href = '/LandingPage/checkout.php';
            });

            // Update cart modal when it's shown
            $('#cartModal').on('show.bs.modal', function() {
                updateCartModal();
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>