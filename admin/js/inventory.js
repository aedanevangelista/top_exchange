document.addEventListener("DOMContentLoaded", function () {
    fetchInventory();

    document.getElementById('edit-product-form').addEventListener('submit', function (e) {
        e.preventDefault();
        updateProductDetails();
    });

    // Close modal when clicking outside of it
    document.getElementById('editProductModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeEditProductModal();
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
                row.setAttribute("data-additional-description", product.additional_description || '');
                
                // Prepare product image HTML
                let imageHtml = '';
                if (product.product_image) {
                    imageHtml = `<img src="${product.product_image}" alt="Product Image" class="product-img" onclick="openModal(this)">`;
                } else {
                    imageHtml = `<div class="no-image">No image</div>`;
                }
                
                row.innerHTML = `
                    <td>${product.category}</td>
                    <td>${product.item_description}</td>
                    <td>${product.packaging}</td>
                    <td>₱${price.toFixed(2)}</td>
                    <td id="stock-${product.product_id}">${product.stock_quantity}</td>
                    <td class="product-image-cell">${imageHtml}</td>
                    <td class="additional-desc">${product.additional_description || ''}</td>
                    <td class="adjust-stock">
                        <button class="add-btn" onclick="updateStock(${product.product_id}, 'add')">Add</button>
                        <input type="number" id="adjust-${product.product_id}" min="1" value="1">
                        <button class="remove-btn" onclick="updateStock(${product.product_id}, 'remove')">Remove</button>
                    </td>
                    <td class="action-buttons">
                        <button class="edit-btn" onclick="editProduct(${product.product_id})">
                            <i class="fas fa-edit"></i> Edit
                        </button>
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

function editProduct(productId) {
    // Fetch the product details to populate the form
    fetch(`../pages/api/get_product.php?id=${productId}`)
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
            return response.json();
        })
        .then(product => {
            // Populate the edit form with product details
            document.getElementById('edit_product_id').value = product.product_id;
            document.getElementById('edit_category').value = product.category;
            document.getElementById('edit_item_description').value = product.item_description;
            document.getElementById('edit_packaging').value = product.packaging;
            document.getElementById('edit_price').value = product.price;
            document.getElementById('edit_stock_quantity').value = product.stock_quantity;
            document.getElementById('edit_additional_description').value = product.additional_description || '';
            
            // Clear previous image
            document.getElementById('current-image-container').innerHTML = '';
            
            // Show current image if it exists
            if (product.product_image) {
                const imgContainer = document.getElementById('current-image-container');
                imgContainer.innerHTML = `
                    <p>Current Image:</p>
                    <img src="${product.product_image}" alt="Current product image" style="max-width: 200px; max-height: 200px; margin-bottom: 10px; border-radius: 4px;">
                `;
            }
            
            // Show the modal
            document.getElementById('editProductModal').style.display = 'flex';
            document.getElementById('editProductError').textContent = '';
        })
        .catch(error => {
            toastr.error("Error fetching product details", { timeOut: 3000, closeButton: true });
            console.error("Error fetching product details:", error);
        });
}

function updateProductDetails() {
    const formData = new FormData(document.getElementById('edit-product-form'));
    
    fetch("../pages/api/update_product.php", {
        method: "POST",
        body: formData
    })
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (data.success) {
                toastr.success(data.message, { timeOut: 3000, closeButton: true });
                fetchInventory();
                closeEditProductModal();
            } else {
                document.getElementById('editProductError').textContent = data.message;
            }
        })
        .catch(error => {
            toastr.error("Error updating product", { timeOut: 3000, closeButton: true });
            console.error("Error updating product:", error);
        });
}

function closeEditProductModal() {
    document.getElementById('editProductModal').style.display = 'none';
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
        const additionalDescription = (row.getAttribute('data-additional-description') || '').toLowerCase();
        
        if (itemDescription.includes(searchValue) || 
            category.includes(searchValue) || 
            packaging.includes(searchValue) ||
            additionalDescription.includes(searchValue)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function openModal(imgElement) {
    var modal = document.getElementById("myModal");
    var modalImg = document.getElementById("img01");
    var captionText = document.getElementById("caption");
    modal.style.display = "block";
    modalImg.src = imgElement.src;
    captionText.innerHTML = imgElement.alt;
}

function closeModal() {
    var modal = document.getElementById("myModal");
    modal.style.display = "none";
}