<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Inventory');

if (!isset($_SESSION['admin_user_id'])) {
    header("Location: /admin/public/login.php");
    exit();
}

// Fetch raw materials
$sql = "SELECT material_id, name, stock_quantity FROM raw_materials ORDER BY name";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raw Materials</title>
    <link rel="stylesheet" href="/admin/css/inventory.css">
    <link rel="stylesheet" href="/admin/css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .overlay-content {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        
        .overlay-content h2 {
            margin-top: 0;
            color: #333;
            font-size: 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .overlay-content h2 i {
            margin-right: 10px;
            color: #4CAF50;
        }
        
        .overlay-content label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .overlay-content input {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .overlay-content input:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 5px rgba(76, 175, 80, 0.3);
        }
        
        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .save-btn, .cancel-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .save-btn {
            background-color: #4CAF50;
            color: white;
        }
        
        .save-btn:hover {
            background-color: #45a049;
        }
        
        .cancel-btn {
            background-color: #f44336;
            color: white;
        }
        
        .cancel-btn:hover {
            background-color: #d32f2f;
        }
        
        .error-message {
            color: #f44336;
            margin-bottom: 20px;
            font-size: 14px;
            background-color: rgba(244, 67, 54, 0.1);
            padding: 10px;
            border-radius: 4px;
            display: none;
        }
        
        .error-message:not(:empty) {
            display: block;
        }
        
        .adjust-stock {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .adjust-stock input {
            width: 60px;
            text-align: center;
            margin-bottom: 0;
        }
        
        .adjust-stock select {
            width: 50px;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .add-btn, .remove-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .add-btn {
            background-color: #4CAF50;
            color: white;
        }
        
        .remove-btn {
            background-color: #f44336;
            color: white;
        }
        
        .quantity-input-group {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .quantity-input-group input {
            flex: 1;
            margin-bottom: 0;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        
        .quantity-input-group select {
            width: 70px;
            padding: 10px;
            border: 1px solid #ddd;
            border-left: none;
            border-top-right-radius: 4px;
            border-bottom-right-radius: 4px;
            background-color: #f5f5f5;
            font-size: 16px;
        }
    </style>
</head>

<body>
    <?php include '../sidebar.php'; ?>

    <div class="main-content">
        <div class="inventory-header">
            <h1>Raw Materials</h1>
            <div class="search-filter-container">
                <div class="search-container">
                    <input type="text" id="search-input" placeholder="Search materials..." onkeyup="searchMaterials()">
                    <button class="search-btn"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <button onclick="openAddMaterialForm()" class="add-product-btn">
                <i class="fas fa-plus-circle"></i> Add New Material
            </button>
        </div>

        <div class="inventory-table-container">
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Material Name</th>
                        <th>Stock Quantity</th>
                        <th>Adjust Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="materials-table">
                    <?php
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr data-material-id='{$row['material_id']}' data-name='{$row['name']}'>
                                    <td>{$row['name']}</td>
                                    <td id='stock-{$row['material_id']}'>" . formatWeight($row['stock_quantity']) . "</td>
                                    <td class='adjust-stock'>
                                        <button class='add-btn' onclick='updateStock({$row['material_id']}, \"add\")'>Add</button>
                                        <input type='number' id='adjust-{$row['material_id']}' min='0.001' step='0.001' value='100'>
                                        <select id='unit-{$row['material_id']}'>
                                            <option value='g'>g</option>
                                            <option value='kg'>kg</option>
                                        </select>
                                        <button class='remove-btn' onclick='updateStock({$row['material_id']}, \"remove\")'>Remove</button>
                                    </td>
                                    <td>
                                        <button class='edit-btn' onclick='editMaterial({$row['material_id']})'>
                                            <i class='fas fa-edit'></i> Edit
                                        </button>
                                    </td>
                                </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4'>No materials found</td></tr>";
                    }
                    
                    // Function to format weight (grams to kg if applicable)
                    function formatWeight($weightInGrams) {
                        if ($weightInGrams >= 1000) {
                            return number_format($weightInGrams / 1000, 2) . " kg";
                        } else {
                            return number_format($weightInGrams, 2) . " g";
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Add Material Modal -->
        <div id="addMaterialModal" class="overlay" style="display: none;">
            <div class="overlay-content">
                <h2><i class="fas fa-plus-circle"></i> Add New Material</h2>
                <div id="addMaterialError" class="error-message"></div>
                <form id="add-material-form" method="POST">
                    <label for="name">Material Name:</label>
                    <input type="text" id="name" name="name" required placeholder="Enter material name">
                    
                    <label for="stock_quantity">Initial Stock Quantity:</label>
                    <div class="quantity-input-group">
                        <input type="number" id="stock_quantity" name="stock_quantity" min="0" step="0.001" value="0" required>
                        <select id="stock_unit" name="stock_unit">
                            <option value="g">g</option>
                            <option value="kg">kg</option>
                        </select>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="cancel-btn" onclick="closeAddMaterialModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Material Modal -->
        <div id="editMaterialModal" class="overlay" style="display: none;">
            <div class="overlay-content">
                <h2><i class="fas fa-edit"></i> Edit Material</h2>
                <div id="editMaterialError" class="error-message"></div>
                <form id="edit-material-form" method="POST">
                    <input type="hidden" id="edit_material_id" name="material_id">
                    
                    <label for="edit_name">Material Name:</label>
                    <input type="text" id="edit_name" name="name" required placeholder="Enter material name">
                    
                    <label for="edit_stock_quantity">Stock Quantity:</label>
                    <div class="quantity-input-group">
                        <input type="number" id="edit_stock_quantity" name="stock_quantity" min="0" step="0.001" required>
                        <select id="edit_stock_unit" name="stock_unit">
                            <option value="g">g</option>
                            <option value="kg">kg</option>
                        </select>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="cancel-btn" onclick="closeEditMaterialModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        toastr.options = {
            "positionClass": "toast-bottom-right",
            "opacity": 1
        };

        // Helper function to format weight (g to kg if applicable)
        function formatWeight(weightInGrams) {
            if (weightInGrams >= 1000) {
                return (weightInGrams / 1000).toFixed(2) + " kg";
            } else {
                return weightInGrams.toFixed(2) + " g";
            }
        }

        function searchMaterials() {
            const searchValue = document.getElementById('search-input').value.toLowerCase();
            const rows = document.querySelectorAll('#materials-table tr');

            rows.forEach(row => {
                const name = row.getAttribute('data-name').toLowerCase();
                
                if (name.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function openAddMaterialForm() {
            document.getElementById('addMaterialModal').style.display = 'flex';
            document.getElementById('add-material-form').reset();
            document.getElementById('addMaterialError').textContent = '';
        }

        function closeAddMaterialModal() {
            document.getElementById('addMaterialModal').style.display = 'none';
        }

        function editMaterial(materialId) {
            fetch(`../pages/api/get_material.php?id=${materialId}`)
                .then(response => {
                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error("Invalid JSON response:", text);
                            throw new Error("Server returned invalid response");
                        }
                    });
                })
                .then(material => {
                    document.getElementById('edit_material_id').value = material.material_id;
                    document.getElementById('edit_name').value = material.name;
                    
                    // Determine if we should display in kg or g
                    if (material.stock_quantity >= 1000) {
                        document.getElementById('edit_stock_quantity').value = (material.stock_quantity / 1000).toFixed(3);
                        document.getElementById('edit_stock_unit').value = 'kg';
                    } else {
                        document.getElementById('edit_stock_quantity').value = material.stock_quantity;
                        document.getElementById('edit_stock_unit').value = 'g';
                    }
                    
                    document.getElementById('editMaterialModal').style.display = 'flex';
                    document.getElementById('editMaterialError').textContent = '';
                })
                .catch(error => {
                    toastr.error("Error fetching material details: " + error.message, { timeOut: 3000, closeButton: true });
                    console.error("Error fetching material details:", error);
                });
        }

        function closeEditMaterialModal() {
            document.getElementById('editMaterialModal').style.display = 'none';
        }

        function validateQuantityInput(value) {
            return !isNaN(value) && value > 0;
        }

        function updateStock(materialId, action) {
            const amountElement = document.getElementById(`adjust-${materialId}`);
            const amount = parseFloat(amountElement.value);
            const unit = document.getElementById(`unit-${materialId}`).value;
            
            if (!validateQuantityInput(amount)) {
                toastr.error("Please enter a valid positive number", { timeOut: 3000, closeButton: true });
                return;
            }
            
            // Convert to grams if kg is selected
            const amountInGrams = unit === 'kg' ? amount * 1000 : amount;
            
            fetch("../pages/api/update_material_stock.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ 
                    material_id: materialId, 
                    action: action, 
                    amount: amountInGrams
                })
            })
            .then(response => {
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error("Invalid JSON response:", text);
                        throw new Error("Server returned invalid response");
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    document.getElementById(`stock-${materialId}`).textContent = formatWeight(Number(data.new_stock));
                    toastr.success(data.message, { timeOut: 3000, closeButton: true });
                } else {
                    toastr.error(data.message, { timeOut: 3000, closeButton: true });
                }
            })
            .catch(error => {
                toastr.error("Error updating stock: " + error.message, { timeOut: 3000, closeButton: true });
                console.error("Error updating stock:", error);
            });
        }

        document.getElementById('add-material-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const name = document.getElementById('name').value;
            let stockQuantity = parseFloat(document.getElementById('stock_quantity').value);
            const stockUnit = document.getElementById('stock_unit').value;
            
            if (!name.trim()) {
                document.getElementById('addMaterialError').textContent = 'Please enter a material name.';
                return;
            }
            
            if (!validateQuantityInput(stockQuantity)) {
                document.getElementById('addMaterialError').textContent = 'Please enter a valid positive number for stock quantity.';
                return;
            }
            
            // Convert to grams if kg is selected
            if (stockUnit === 'kg') {
                stockQuantity *= 1000;
            }
            
            const formData = new FormData();
            formData.append('name', name);
            formData.append('stock_quantity', stockQuantity);
            
            document.getElementById('addMaterialError').textContent = '';
            
            fetch("../../backend/add_material.php", {
                method: "POST",
                body: formData
            })
            .then(response => {
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error("Invalid JSON response:", text);
                        throw new Error("Server returned invalid response: " + text.substring(0, 50) + "...");
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    toastr.success(data.message, { timeOut: 3000, closeButton: true });
                    closeAddMaterialModal();
                    window.location.reload();
                } else {
                    document.getElementById('addMaterialError').textContent = data.message || 'An unknown error occurred';
                }
            })
            .catch(error => {
                toastr.error(error.message, { timeOut: 3000, closeButton: true });
                document.getElementById('addMaterialError').textContent = error.message;
                console.error("Error:", error);
            });
        });

        document.getElementById('edit-material-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const materialId = document.getElementById('edit_material_id').value;
            const name = document.getElementById('edit_name').value;
            let stockQuantity = parseFloat(document.getElementById('edit_stock_quantity').value);
            const stockUnit = document.getElementById('edit_stock_unit').value;
            
            if (!name.trim()) {
                document.getElementById('editMaterialError').textContent = 'Please enter a material name.';
                return;
            }
            
            if (!validateQuantityInput(stockQuantity)) {
                document.getElementById('editMaterialError').textContent = 'Please enter a valid positive number for stock quantity.';
                return;
            }
            
            // Convert to grams if kg is selected
            if (stockUnit === 'kg') {
                stockQuantity *= 1000;
            }
            
            const formData = new FormData();
            formData.append('material_id', materialId);
            formData.append('name', name);
            formData.append('stock_quantity', stockQuantity);
            
            document.getElementById('editMaterialError').textContent = '';
            
            fetch("../../backend/edit_material.php", {
                method: "POST",
                body: formData
            })
            .then(response => {
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error("Invalid JSON response:", text);
                        throw new Error("Server returned invalid response: " + text.substring(0, 50) + "...");
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    toastr.success(data.message, { timeOut: 3000, closeButton: true });
                    closeEditMaterialModal();
                    window.location.reload();
                } else {
                    document.getElementById('editMaterialError').textContent = data.message || 'An unknown error occurred';
                }
            })
            .catch(error => {
                toastr.error(error.message, { timeOut: 3000, closeButton: true });
                document.getElementById('editMaterialError').textContent = error.message;
                console.error("Error:", error);
            });
        });

        window.addEventListener('click', function(e) {
            const addMaterialModal = document.getElementById('addMaterialModal');
            const editMaterialModal = document.getElementById('editMaterialModal');
            
            if (e.target === addMaterialModal) {
                closeAddMaterialModal();
            }
            
            if (e.target === editMaterialModal) {
                closeEditMaterialModal();
            }
        });
    </script>
</body>
</html>