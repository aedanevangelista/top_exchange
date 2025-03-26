<?php
include "db_connection.php";

// Handle file upload
$product_image = null;
if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
    $allowed_types = ['image/jpeg', 'image/png'];
    $max_size = 20 * 1024 * 1024; // 20MB
    $file_type = $_FILES['product_image']['type'];
    $file_size = $_FILES['product_image']['size'];
    
    if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
        // Create folder based on item description (for grouping similar products)
        $upload_dir = __DIR__ . '/../uploads/products/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $item_description = $_POST['item_description'] ?? '';
        $item_folder = preg_replace('/[^a-zA-Z0-9]/', '_', $item_description);
        $item_dir = $upload_dir . $item_folder . '/';
        
        if (!file_exists($item_dir)) {
            mkdir($item_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
        $filename = 'product_image.' . $file_extension;
        $product_image = '/top_exchange/uploads/products/' . $item_folder . '/' . $filename;
        
        move_uploaded_file($_FILES['product_image']['tmp_name'], $item_dir . $filename);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid file type or size. Maximum file size is 20MB.']);
        exit;
    }
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

// If JSON data is present, use it. Otherwise, use POST data
if (isset($data) && !empty($data)) {
    $category = $data['category'];
    $item_description = $data['item_description'];
    $packaging = $data['packaging'];
    $price = $data['price'];
    $stock_quantity = $data['stock_quantity'] ?? 0;
    $additional_description = $data['additional_description'] ?? '';
} else {
    $category = $_POST['category'];
    $item_description = $_POST['item_description'];
    $packaging = $_POST['packaging'];
    $price = $_POST['price'];
    $stock_quantity = $_POST['stock_quantity'] ?? 0;
    $additional_description = $_POST['additional_description'] ?? '';
}

// Check if product already exists
$check_sql = "SELECT COUNT(*) as count FROM products WHERE item_description = ? AND packaging = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ss", $item_description, $packaging);
$check_stmt->execute();
$result = $check_stmt->get_result();
$row = $result->fetch_assoc();

if ($row['count'] > 0) {
    echo json_encode(['success' => false, 'message' => 'Product with this description and packaging already exists.']);
    exit;
}

// If we have a product image and the product shares the same name as existing products,
// update all those products to use the same image
if ($product_image) {
    $update_images_sql = "UPDATE products SET product_image = ? WHERE item_description = ?";
    $update_images_stmt = $conn->prepare($update_images_sql);
    $update_images_stmt->bind_param("ss", $product_image, $item_description);
    $update_images_stmt->execute();
}

// Insert new product with the additional columns
$sql = "INSERT INTO products (category, item_description, packaging, price, stock_quantity, additional_description, product_image) VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssdiss", $category, $item_description, $packaging, $price, $stock_quantity, $additional_description, $product_image);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Product added successfully"]);
} else {
    echo json_encode(["success" => false, "message" => "Error adding product: " . $conn->error]);
}

$stmt->close();
$conn->close();
?>