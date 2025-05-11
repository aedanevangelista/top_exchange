<?php
// --- Database Configuration ---
$db_host = '127.0.0.1'; // Or your host
$db_name = 'u701062148_top_exchange';
$db_user = 'your_db_user'; // Replace with your database username
$db_pass = 'your_db_password'; // Replace with your database password
$charset = 'utf8mb4';

$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
    // In a real application, log this error and show a user-friendly message
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// --- Global Variables & Initializations ---
$message = ''; // For success or error messages
$edit_product = null; // Holds product data for editing
$action = $_GET['action'] ?? 'list'; // Default action
$product_id_to_edit = $_GET['id'] ?? null;

// --- Handle Delete Action ---
if ($action === 'delete' && $product_id_to_edit) {
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
        $stmt->execute([$product_id_to_edit]);
        $message = "Product deleted successfully!";
        // Redirect to avoid re-deletion on refresh, or simply fall through to list
        // header("Location: inventory.php?message=" . urlencode($message));
        // exit;
    } catch (\PDOException $e) {
        $message = "Error deleting product: " . $e->getMessage();
    }
    $action = 'list'; // Go back to list view
}

// --- Handle Form Submission (Add/Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id        = $_POST['product_id'] ?? null; // Hidden field for editing
    $category          = trim($_POST['category'] ?? '');
    $product_name      = trim($_POST['product_name'] ?? '');
    $item_description  = trim($_POST['item_description'] ?? '');
    $packaging         = trim($_POST['packaging'] ?? '');
    $price             = trim($_POST['price'] ?? '0');
    $stock_quantity    = trim($_POST['stock_quantity'] ?? '0');
    // New expiration fields
    $expiration_duration = trim($_POST['expiration_duration'] ?? '');
    $expiration_unit   = trim($_POST['expiration_unit'] ?? ''); // "Week" or "Month"

    $expiration_string_to_save = NULL;
    if (!empty($expiration_duration) && is_numeric($expiration_duration) && $expiration_duration > 0 && !empty($expiration_unit)) {
        $duration = intval($expiration_duration);
        $unit_display = ($duration == 1) ? $expiration_unit : $expiration_unit . 's';
        $expiration_string_to_save = $duration . " " . $unit_display;
    }

    // Basic Validation (add more as needed)
    if (empty($category) || empty($product_name) || empty($price)) {
        $message = "Error: Category, Product Name, and Price are required.";
    } else {
        try {
            if ($product_id) { // Update existing product
                $sql = "UPDATE products SET category = ?, product_name = ?, item_description = ?, packaging = ?, expiration = ?, price = ?, stock_quantity = ? WHERE product_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$category, $product_name, $item_description, $packaging, $expiration_string_to_save, $price, $stock_quantity, $product_id]);
                $message = "Product updated successfully!";
            } else { // Add new product
                $sql = "INSERT INTO products (category, product_name, item_description, packaging, expiration, price, stock_quantity) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$category, $product_name, $item_description, $packaging, $expiration_string_to_save, $price, $stock_quantity]);
                $message = "Product added successfully!";
            }
            // Clear form or redirect
            // header("Location: inventory.php?message=" . urlencode($message));
            // exit;
        } catch (\PDOException $e) {
            $message = "Database error: " . $e->getMessage();
        }
    }
    $action = 'list'; // Refresh list after submission
}


// --- Prepare for Edit Form ---
if ($action === 'edit' && $product_id_to_edit) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->execute([$product_id_to_edit]);
    $edit_product = $stmt->fetch();
    if (!$edit_product) {
        $message = "Product not found for editing.";
        $action = 'list'; // Go back to list
        $edit_product = null; // Clear it
    }
}

// --- Fetch Products for Display ---
$products = [];
try {
    $stmt = $pdo->query("SELECT product_id, category, product_name, item_description, packaging, expiration, price, stock_quantity FROM products ORDER BY product_id DESC");
    $products = $stmt->fetchAll();
} catch (\PDOException $e) {
    $message = "Error fetching products: " . $e->getMessage();
    // In a real app, log this and show a user-friendly error
}

// --- Prepare expiration values for the form if editing ---
$form_expiration_duration = '';
$form_expiration_unit = '';
if ($edit_product && !empty($edit_product['expiration'])) {
    $parts = explode(' ', $edit_product['expiration'], 2);
    if (count($parts) === 2 && is_numeric($parts[0])) {
        $form_expiration_duration = $parts[0];
        $unit_with_s = $parts[1]; // "Months", "Month", "Weeks", "Week"
        // Remove trailing 's' if present to get the singular form for the select value
        $form_expiration_unit = rtrim(strtolower($unit_with_s), 's'); // "month" or "week"
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Inventory Management</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .action-links a { margin-right: 10px; text-decoration: none; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-container { border: 1px solid #ccc; padding: 20px; margin-bottom: 20px; background-color: #f9f9f9; }
        .form-container h2 { margin-top: 0; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .form-group textarea { min-height: 80px; }
        .form-group button { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .form-group button:hover { background-color: #0056b3; }
        .btn { padding: 5px 10px; text-decoration: none; border-radius: 3px; display: inline-block; margin-bottom:15px;}
        .btn-add { background-color: #28a745; color: white; }
        .btn-edit { background-color: #ffc107; color: black; }
        .btn-delete { background-color: #dc3545; color: white; }
    </style>
</head>
<body>

    <h1>Product Inventory</h1>

    <?php if ($message): ?>
        <div class="message <?php echo strpos(strtolower($message), 'error') !== false || strpos(strtolower($message), 'required') !== false ? 'error' : 'success'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <a href="?action=show_form" class="btn btn-add">Add New Product</a>

    <?php if ($action === 'show_form' || $edit_product): ?>
    <div class="form-container">
        <h2><?php echo $edit_product ? 'Edit Product' : 'Add New Product'; ?></h2>
        <form action="inventory.php" method="POST">
            <?php if ($edit_product): ?>
                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($edit_product['product_id']); ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="category">Category:</label>
                <input type="text" id="category" name="category" value="<?php echo htmlspecialchars($edit_product['category'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="product_name">Product Name:</label>
                <input type="text" id="product_name" name="product_name" value="<?php echo htmlspecialchars($edit_product['product_name'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="item_description">Item Description:</label>
                <textarea id="item_description" name="item_description"><?php echo htmlspecialchars($edit_product['item_description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="packaging">Packaging:</label>
                <input type="text" id="packaging" name="packaging" value="<?php echo htmlspecialchars($edit_product['packaging'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="expiration_duration">Expiration Duration:</label>
                <input type="number" id="expiration_duration" name="expiration_duration" min="1" placeholder="e.g., 2" value="<?php echo htmlspecialchars($form_expiration_duration); ?>">
            </div>

            <div class="form-group">
                <label for="expiration_unit">Expiration Unit:</label>
                <select id="expiration_unit" name="expiration_unit">
                    <option value="">-- Select Unit --</option>
                    <option value="Week" <?php if (strtolower($form_expiration_unit) == 'week') echo 'selected'; ?>>Week(s)</option>
                    <option value="Month" <?php if (strtolower($form_expiration_unit) == 'month') echo 'selected'; ?>>Month(s)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="price">Price:</label>
                <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($edit_product['price'] ?? '0.00'); ?>" required>
            </div>

            <div class="form-group">
                <label for="stock_quantity">Stock Quantity:</label>
                <input type="number" id="stock_quantity" name="stock_quantity" min="0" value="<?php echo htmlspecialchars($edit_product['stock_quantity'] ?? '0'); ?>" required>
            </div>

            <!-- Add other fields like additional_description, product_image, ingredients as needed -->

            <div class="form-group">
                <button type="submit"><?php echo $edit_product ? 'Update Product' : 'Add Product'; ?></button>
                <?php if ($edit_product || $action === 'show_form'): ?>
                    <a href="inventory.php" style="margin-left: 10px;">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php endif; ?>


    <h2>Product List</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Category</th>
                <th>Product Name</th>
                <th>Item Desc.</th>
                <th>Packaging</th>
                <th>Expiration</th> <!-- New Column -->
                <th>Price</th>
                <th>Stock</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr>
                    <td colspan="9">No products found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td><?php echo htmlspecialchars($product['product_id']); ?></td>
                    <td><?php echo htmlspecialchars($product['category']); ?></td>
                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                    <td><?php echo htmlspecialchars(substr($product['item_description'] ?? '', 0, 50)) . (strlen($product['item_description'] ?? '') > 50 ? '...' : ''); ?></td>
                    <td><?php echo htmlspecialchars($product['packaging'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($product['expiration'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars(number_format((float)($product['price'] ?? 0), 2)); ?></td>
                    <td><?php echo htmlspecialchars($product['stock_quantity'] ?? '0'); ?></td>
                    <td class="action-links">
                        <a href="?action=edit&id=<?php echo $product['product_id']; ?>" class="btn btn-edit">Edit</a>
                        <a href="?action=delete&id=<?php echo $product['product_id']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
