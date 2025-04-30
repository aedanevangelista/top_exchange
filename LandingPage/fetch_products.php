<?php
session_start();
include_once('db_connection.php');

// Get filter parameters
$category = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 1000;

// Build the SQL query
$sql = "SELECT p.product_id, p.product_name, p.item_description, p.price, p.product_image AS image_path, p.packaging, p.category 
        FROM products p 
        WHERE p.price BETWEEN ? AND ?";
$params = [$min_price, $max_price];
$types = 'dd';

// Add category filter if specified
if (!empty($category)) {
    $sql .= " AND p.category = ?";
    $params[] = $category;
    $types .= 's';
}

// Add search filter if specified
if (!empty($search)) {
    $search_term = "%$search%";
    $sql .= " AND (p.item_description LIKE ? OR p.category LIKE ? OR p.product_name LIKE ?)";
    array_push($params, $search_term, $search_term, $search_term);
    $types .= 'sss';
}

// Prepare and execute the query
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Function to get base product name
function getBaseProductName($productName) {
    return preg_replace('/\s*\([A-Z][^)]*\)$/', '', $productName);
}

// Group products by their base name
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

// Generate HTML for products
ob_start();
if (!empty($groupedProducts)):
    foreach ($groupedProducts as $productName => $productGroup): 
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