document.addEventListener("DOMContentLoaded", function () {
    fetchInventory();
});

function fetchInventory() {
    fetch("get_inventory.php") 
        .then(response => response.json())
        .then(data => {
            const tableBody = document.getElementById("inventory-table");
            tableBody.innerHTML = ""; 

            data.forEach(product => {
                const row = document.createElement("tr");
                row.innerHTML = `
                    <td>${product.product_name}</td>
                    <td>${product.stock_quantity}</td>
                    <td>
                        <input type="number" id="adjust-${product.product_id}" min="1" value="1">
                    </td>
                    <td>
                        <button class="add-btn" onclick="updateStock(${product.product_id}, 'add')">➕ Add</button>
                        <button class="remove-btn" onclick="updateStock(${product.product_id}, 'remove')">➖ Remove</button>
                    </td>
                `;
                tableBody.appendChild(row);
            });
        })
        .catch(error => console.error("Error fetching inventory:", error));
}

function updateStock(productId, action) {
    const amount = document.getElementById(`adjust-${productId}`).value;

    fetch("update_stock.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ product_id: productId, action: action, amount: amount })
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        fetchInventory(); 
    })
    .catch(error => console.error("Error updating stock:", error));
}
