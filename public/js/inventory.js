document.addEventListener("DOMContentLoaded", function () {
    fetchInventory();

    document.getElementById('edit-stock-form').addEventListener('submit', function (e) {
        e.preventDefault();
        updateStockDirectly();
    });

    // Close modal when clicking outside of it
    document.getElementById('editStockModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeEditModal();
        }
    });
    
    document.getElementById('addProductModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeAddProductModal();
        }
    });
});

function fetchInventory() {
    fetch("../pages/api/get_inventory.php") // Adjust path as needed
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            const tableBody = document.getElementById("inventory-table");
            tableBody.innerHTML = "";

            data.forEach(product => {
                const price = parseFloat(product.price) || 0; // Ensure price is a number

                const row = document.createElement("tr");
                row.setAttribute("data-category", product.category);
                row.setAttribute("data-product-id", product.product_id);
                row.setAttribute("data-item-description", product.item_description);
                row.setAttribute("data-packaging", product.packaging);
                
                row.innerHTML = `
                    <!-- Product ID column removed -->
                    <td>${product.category}</td>
                    <td>${product.item_description}</td>
                    <td>${product.packaging}</td>
                    <td>₱${price.toFixed(2)}</td>
                    <td id="stock-${product.product_id}">${product.stock_quantity}</td>
                    <td class="adjust-stock">
                        <button class="add-btn" onclick="updateStock(${product.product_id}, 'add')">Add</button>
                        <input type="number" id="adjust-${product.product_id}" min="1" value="1">
                        <button class="remove-btn" onclick="updateStock(${product.product_id}, 'remove')">Remove</button>
                    </td>
                    <td class="edit-stock">
                        <button class="edit-btn" onclick="editStock(${product.product_id})">Edit</button>
                    </td>
                `;
                tableBody.appendChild(row);
            });
            
            // Apply current search and filter
            if (document.getElementById('search-input').value.trim() !== '') {
                searchProducts();
            }
            filterByCategory();
        })
        .catch(error => {
            console.error("Error fetching inventory:", error);
        });
}

function updateStock(productId, action) {
    const amount = document.getElementById(`adjust-${productId}`).value;

    fetch("../pages/api/update_stock.php", { // Adjust path as needed
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ product_id: productId, action: action, amount: amount })
    })
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            toastr.success(data.message, { timeOut: 3000, closeButton: true, positionClass: 'toast-bottom-right' });
            fetchInventory();
        })
        .catch(error => {
            toastr.error("Error updating stock", { timeOut: 3000, closeButton: true, positionClass: 'toast-bottom-right' });
            console.error("Error updating stock:", error);
        });
}

function editStock(productId) {
    const stockQuantity = document.getElementById(`stock-${productId}`).innerText;
    document.getElementById('edit_product_id').value = productId;
    document.getElementById('edit_stock_quantity').value = stockQuantity;

    document.getElementById('editStockModal').style.display = 'flex';
}

function updateStockDirectly() {
    const productId = document.getElementById('edit_product_id').value;
    const stockQuantity = document.getElementById('edit_stock_quantity').value;

    fetch("../pages/api/update_stock_direct.php", { // Adjust path as needed
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ product_id: productId, stock_quantity: stockQuantity })
    })
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            toastr.success(data.message, { timeOut: 3000, closeButton: true, positionClass: 'toast-bottom-right' });
            fetchInventory();
            closeEditModal();
        })
        .catch(error => {
            toastr.error("Error updating stock", { timeOut: 3000, closeButton: true, positionClass: 'toast-bottom-right' });
            console.error("Error updating stock:", error);
        });
}

function closeEditModal() {
    document.getElementById('editStockModal').style.display = 'none';
}

function closeAddProductModal() {
    document.getElementById('addProductModal').style.display = 'none';
}

function openAddProductForm() {
    document.getElementById('addProductModal').style.display = 'flex';
    document.getElementById('add-product-form').reset();
    document.getElementById('addProductError').textContent = '';
    document.getElementById('new-category-container').style.display = 'none';
}

function filterByCategory() {
    const filterValue = document.getElementById('category-filter').value;
    const rows = document.querySelectorAll('#inventory-table tr');

    rows.forEach(row => {
        if (filterValue === 'all' || row.getAttribute('data-category') === filterValue) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function searchProducts() {
    const searchValue = document.getElementById('search-input').value.toLowerCase();
    const rows = document.querySelectorAll('#inventory-table tr');

    rows.forEach(row => {
        const itemDescription = (row.getAttribute('data-item-description') || '').toLowerCase();
        const category = (row.getAttribute('data-category') || '').toLowerCase();
        const packaging = (row.getAttribute('data-packaging') || '').toLowerCase();
        
        if (itemDescription.includes(searchValue) || 
            category.includes(searchValue) || 
            packaging.includes(searchValue)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}