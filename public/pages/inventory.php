<?php
session_start();
include "../../backend/db_connection.php";

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: http://localhost/top_exchange/public/login.php");
    exit();
}

// Fetch inventory data from `products`
$sql = "SELECT product_id, category, item_description, packaging, price, stock_quantity FROM products ORDER BY category, item_description";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory</title>
    <link rel="stylesheet" href="../css/inventory.css">
    <link rel="stylesheet" href="../css/sidebar.css"> <!-- Add this line -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>

<body>

    <!-- Sidebar -->
    <?php include '../sidebar.php'; ?>

    <div class="main-content">
        <h1>Inventory</h1>

        <table>
            <thead>
                <tr>
                    <th>Product ID</th>
                    <th>Category</th>
                    <th>Item Description</th>
                    <th>Packaging</th>
                    <th>Price</th>
                    <th>Stock Level</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                                <td>{$row['product_id']}</td>
                                <td>{$row['category']}</td>
                                <td>{$row['item_description']}</td>
                                <td>{$row['packaging']}</td>
                                <td>₱" . number_format($row['price'], 2) . "</td>
                                <td>{$row['stock_quantity']}</td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='6'>No products found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

</body>
</html>
