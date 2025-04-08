<?php
session_start();
include "../../backend/db_connection.php";
include "../../backend/check_role.php";
checkRole('Inventory');

if (!isset($_SESSION['admin_user_id'])) {
    header("Location: /public/login.php");
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
    <title>Raw Materials Inventory</title>
    <link rel="stylesheet" href="/css/inventory.css">
    <link rel="stylesheet" href="/css/sidebar.css">
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
            width: 80px;
            text-align: center;
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .add-btn, .remove-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .add-btn {
            background-color: #4CAF50;
            color: white;
        }
        
        .add-btn:hover {
            background-color: #45a049;
        }
        
        .remove-btn {
            background-color: #f44336;
            color: white;
        }
        
        .remove-btn:hover {
            background-color: #d32f2f;
        }
        
        .edit-btn {
            background-color: #2196F3;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .edit-btn:hover {
            background-color: #0b7dda;
        }
        
        .inventory-table th, .inventory-table td {
            padding: 12px 15px;
        }
        
        .inventory-table th {
            background-color: #f2f2f2;
        }
        
        .inventory-header {
            margin-bottom: 30px;
        }
        
        .add-product-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background-color: #4CAF50;
            padding: 10px 15px;
            font-size: 16px;
        }
        
        .add-product-btn i {
            font-size: 18px;
        }
    </style>
</head>

<body>
    <?php include '../sidebar.php'; ?>

    <div class="main-content">
        <div class="inventory-header">
            <h1>Raw Materials Inventory</h1>
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
                        <th>Stock Quantity (grams)</th>
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
                                    <td id='stock-{$row['material_id']}'>" . number_format($row['stock_quantity'], 2) . "</td>
                                    <td class='adjust-stock'>
                                        <button class='add-btn' onclick='updateStock({$row['material_id']}, \"add\")'>Add</button>
                                        <input type='number' id='adjust-{$row['material_id']}' min='1' value='100'>
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
                    
                    <label for="stock_quantity">Initial Stock Quantity (grams):</label>
                    <input type="number" id="stock_quantity" name="stock_quantity" min="0" step="0.01" value="0" required>
                    
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
                    
                    <label for="edit_stock_quantity">Stock Quantity (grams):</label>
                    <input type="number" id="edit_stock_quantity" name="stock_quantity" min="0" step="0.01" required>
                    
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
            "opacity": 1,
            "timeOut": 3000,
            "closeButton": true
        };

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
                    document.getElementById('edit_stock_quantity').value = material.stock_quantity;
                    
                    document.getElementById('editMaterialModal').style.display = 'flex';
                    document.getElementById('editMaterialError').textContent = '';
                })
                .catch(error => {
                    toastr.error("Error fetching material details: " + error.message);
                    console.error("Error fetching material details:", error);
                });
        }

        function closeEditMaterialModal() {
            document.getElementById('editMaterialModal').style.display = 'none';
        }

        function updateStock(materialId, action) {
            const amount = document.getElementById(`adjust-${materialId}`).value;

            fetch("../pages/api/update_material_stock.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ material_id: materialId, action: action, amount: amount })
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
                    document.getElementById(`stock-${materialId}`).textContent = Number(data.new_stock).toFixed(2);
                    toastr.success(data.message);
                } else {
                    toastr.error(data.message);
                }
            })
            .catch(error => {
                toastr.error("Error updating stock: " + error.message);
                console.error("Error updating stock:", error);
            });
        }

        document.getElementById('add-material-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const name = document.getElementById('name').value;
            const stockQuantity = document.getElementById('stock_quantity').value;
            
            if (!name.trim()) {
                document.getElementById('addMaterialError').textContent = 'Please enter a material name.';
                return;
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
                    toastr.success(data.message);
                    closeAddMaterialModal();
                    window.location.reload();
                } else {
                    document.getElementById('addMaterialError').textContent = data.message || 'An unknown error occurred';
                }
            })
            .catch(error => {
                toastr.error(error.message);
                document.getElementById('addMaterialError').textContent = error.message;
                console.error("Error:", error);
            });
        });

        document.getElementById('edit-material-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const materialId = document.getElementById('edit_material_id').value;
            const name = document.getElementById('edit_name').value;
            const stockQuantity = document.getElementById('edit_stock_quantity').value;
            
            if (!name.trim()) {
                document.getElementById('editMaterialError').textContent = 'Please enter a material name.';
                return;
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
                    toastr.success(data.message);
                    closeEditMaterialModal();
                    window.location.reload();
                } else {
                    document.getElementById('editMaterialError').textContent = data.message || 'An unknown error occurred';
                }
            })
            .catch(error => {
                toastr.error(error.message);
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