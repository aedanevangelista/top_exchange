<?php
// Start the session
session_start();

// Include database connection file
include_once('db_connection.php');

// Check connection
if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed']));
}

// Get filter parameters from AJAX request
$category = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$min_price = isset($_GET['min_price']) ? $_GET['min_price'] : '';
$max_price = isset($_GET['max_price']) ? $_GET['max_price'] : '';

// Function to get the base product name (remove variant identifiers)
function getBaseProductName($productName) {
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
        $product['product_name'] = getBaseProductName($product['item_description']);
    }
    
    $productName = $product['product_name'];
    
    if (!isset($groupedProducts[$productName])) {
        $groupedProducts[$productName] = [
            'main_product' => $product,
            'variants' => []
        ];
    } else {
        $groupedProducts[$productName]['variants'][] = $product;
    }
}

// Generate HTML for the products
ob_start(); // Start output buffering
?>

<div class="row">
    <?php if (!empty($groupedProducts)): ?>
        <?php foreach ($groupedProducts as $productName => $productGroup): 
            $mainProduct = $productGroup['main_product'];
            $variants = $productGroup['variants'];
            $hasVariants = !empty($variants);
        ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="cream_box">
                    <div class="cream_img">
                        <img src="<?php echo htmlspecialchars($mainProduct['image_path'] ?? 'images/default-product.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($productName); ?>" 
                             id="product-image-<?php echo $mainProduct['product_id']; ?>">
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
                            <select id="variant-select-<?php echo $mainProduct['product_id']; ?>" 
                                    class="form-select variant-dropdown" 
                                    data-product-container="product-container-<?php echo $mainProduct['product_id']; ?>">
                                <option value="<?php echo $mainProduct['product_id']; ?>" 
                                        data-price="<?php echo $mainProduct['price']; ?>"
                                        data-packaging="<?php echo htmlspecialchars($mainProduct['packaging']); ?>"
                                        data-image="<?php echo htmlspecialchars($mainProduct['image_path'] ?? 'images/default-product.jpg'); ?>"
                                        data-name="<?php echo htmlspecialchars($mainProduct['item_description']); ?>" 
                                        selected>
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
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-md-12">
            <div class="no-results">
                <i class="fas fa-search fa-4x"></i>
                <h4>No products found</h4>
                <p>We couldn't find any products matching your criteria. Try adjusting your filters.</p>
                <button id="reset-filters-inner" class="btn btn-primary mt-3">
                    <i class="fas fa-times me-2"></i>Reset all filters
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$html = ob_get_clean(); // Get the buffered output
echo $html;

$stmt->close();
$conn->close();
?>