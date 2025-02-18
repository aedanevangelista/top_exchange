document.addEventListener("DOMContentLoaded", function() {
    // Function to show custom toast messages with countdown
    function showCustomToast(action, message, duration, callback) {
        let remainingTime = duration / 1000; // Convert to seconds
        const toast = document.createElement('div');
        toast.className = `custom-toast ${action}`;
        const icon = document.createElement('i');
        icon.className = `fas ${action === 'add' ? 'fa-check-circle' : action === 'edit' ? 'fa-edit' : 'fa-trash'}`;
        const text = document.createElement('span');
        text.textContent = `${message} in ${remainingTime} seconds`;
        toast.appendChild(icon);
        toast.appendChild(text);
        document.body.appendChild(toast);

        const interval = setInterval(() => {
            remainingTime -= 1;
            text.textContent = `${message} in ${remainingTime} seconds`;
            if (remainingTime <= 0) {
                clearInterval(interval);
                document.body.removeChild(toast);
                if (callback) callback();
            }
        }, 1000);
    }

    // Show error prompt
    function showErrorPrompt(message, isEdit = false) {
        const errorPrompt = isEdit ? document.getElementById("editCustomerError") : document.getElementById("customerError");
        if (errorPrompt) {
            errorPrompt.textContent = message;
            setTimeout(() => {
                errorPrompt.textContent = "";
            }, 2000);
        }
    }

    // ----------------------------
    // ➕ ADD CUSTOMER LOGIC
    // ----------------------------

    // Open Add Customer Form Overlay
    window.openAddCustomerForm = function() {
        document.getElementById("customer-modal").style.display = "flex";
        document.getElementById("formType").value = "add";
        document.getElementById("modal-title").textContent = "Add Customer";
        const customerError = document.getElementById("customerError");
        if (customerError) {
            customerError.innerText = ""; // Clear error
        }
    }

    // Close Add Customer Form Overlay
    window.closeAddCustomerForm = function() {
        document.getElementById("customer-modal").style.display = "none";
        document.getElementById("customer-form").reset();
        const customerError = document.getElementById("customerError");
        if (customerError) {
            customerError.innerText = ""; // Clear error
        }
    }

    // Close modal when clicking outside of it
    window.onclick = function(event) {
        const modal = document.getElementById("customer-modal");
        if (event.target === modal) {
            closeAddCustomerForm();
        }
    }

    // Submit Add Customer Form with AJAX
    document.getElementById("customer-form").addEventListener("submit", function(event) {
        event.preventDefault();
        var formData = new FormData(this);
        formData.append("ajax", true);

        fetch(this.action, {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeAddCustomerForm();
                showCustomToast("add", "Adding customer", 5000, () => {
                    window.location.reload();
                });
            } else {
                showErrorPrompt(data.message || "An error occurred.");
            }
        })
        .catch(error => {
            console.error("Error:", error);
            showErrorPrompt("An unexpected error occurred.");
        });
    });

    // ----------------------------
    // ✏️ EDIT CUSTOMER LOGIC
    // ----------------------------

    let editCustomerId = null;

    // Open Edit Customer Form Overlay
    window.openEditCustomerForm = function(customerId, customerName) {
        editCustomerId = customerId;
        document.getElementById("customer-modal").style.display = "flex";
        document.getElementById("formType").value = "edit";
        document.getElementById("modal-title").innerHTML = '<i class="fas fa-user"></i> Edit Customer';

        // Pre-fill form fields
        document.getElementById("customer_id").value = customerId;
        document.getElementById("customer_name").value = customerName;
        const customerError = document.getElementById("customerError");
        if (customerError) {
            customerError.innerText = ""; // Clear error
        }
    }

    // Submit Edit Customer Form with AJAX
    function submitEditCustomerForm(event) {
        event.preventDefault();
        var formData = new FormData(this);
        formData.append("ajax", true);

        fetch(this.action, {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeAddCustomerForm();
                showCustomToast("edit", "Editing customer", 5000, () => {
                    window.location.reload();
                });
            } else {
                showErrorPrompt(data.message || "An error occurred.");
            }
        })
        .catch(error => {
            console.error("Error:", error);
            showErrorPrompt("An unexpected error occurred.");
        });
    }

    // ----------------------------
    // 🗑️ DELETE CUSTOMER LOGIC
    // ----------------------------

    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function () {
            if (confirm('Are you sure you want to delete this customer?')) {
                fetch(`/top_exchange/backend/delete_customer.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        ajax: true,
                        formType: 'delete',
                        customer_id: this.dataset.id
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Customer deleted successfully.');
                            location.reload();
                        } else {
                            alert('Failed to delete customer.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }
        });
    });
});