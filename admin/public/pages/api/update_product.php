<?php
session_start();
include "../../../backend/db_connection.php";

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit;
}

$product_id = intval($_POST['product_id']);
$category = $_POST['category'];
$item_description = $_POST['item_description'];
$packaging = $_POST['packaging'];
$price = floatval($_POST['price']);
$stock_quantity = intval($_POST['stock_quantity']);
$additional_description = $_POST['additional_description'];

// Handle new category
if ($category === 'new' && isset($_POST['new_category']) && !empty($_POST['new_category'])) {
    $category = $_POST['new_category'];
}

// Check if item description changed
$stmt = $conn->prepare("SELECT item_description, product_image FROM products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$old_product = $result->fetch_assoc();
$stmt->close();

$old_item_description = $old_product['item_description'];
$old_product_image = $old_product['product_image'];

// Process image upload if provided
$product_image = $old_product_image; // Default to current image

if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
    $allowed_types = ['image/jpeg', 'image/png'];
    $max_size = 20 * 1024 * 1024; // 20MB
    $file_type = $_FILES['product_image']['type'];
    $file_size = $_FILES['product_image']['size'];
    
    if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
        // Create upload directory if it doesn't exist
        $upload_dir = __DIR__ . '/uploads/products/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Create folder based on item description
        $item_folder = preg_replace('/[^a-zA-Z0-9]/', '_', $item_description);
        $item_dir = $upload_dir . $item_folder . '/';
        
        if (!file_exists($item_dir)) {
            mkdir($item_dir, 0777, true);
        }
        
        // Delete old image if item description changed
        if ($old_item_description != $item_description && !empty($old_product_image)) {
            $old_item_folder = preg_replace('/[^a-zA-Z0-9]/', '_', $old_item_description);
            $old_item_dir = $upload_dir . $old_item_folder . '/';
            
            // Extract filename from path
            $old_filename = basename($old_product_image);
            
            // Delete file if it exists
            if (file_exists($old_item_dir . $old_filename)) {
                @unlink($old_item_dir . $old_filename);
            }
        }
        
        // Save the new image
        $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
        $filename = 'product_image.' . $file_extension;
        $product_image_path = '' . $item_folder . '/' . $filename;
        
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $item_dir . $filename)) {
            $product_image = $product_image_path;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid file type or size. Maximum file size is 20MB.']);
        exit;
    }
}

// Update product in database
$stmt = $conn->prepare("UPDATE products SET category = ?, item_description = ?, packaging = ?, price = ?, stock_quantity = ?, additional_description = ?, product_image = ? WHERE product_id = ?");
$stmt->bind_param("sssddisi", $category, $item_description, $packaging, $price, $stock_quantity, $additional_description, $product_image, $product_id);

if ($stmt->execute()) {
    // If item description changed, update all products with the same description to use the new image
    if ($old_item_description != $item_description && !empty($product_image)) {
        $stmt = $conn->prepare("UPDATE products SET product_image = ? WHERE item_description = ? AND product_id != ?");
        $stmt->bind_param("ssi", $product_image, $item_description, $product_id);
        $stmt->execute();
    }
    
    echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error updating product: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>