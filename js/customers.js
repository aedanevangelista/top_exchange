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
    function showErrorPrompt(message) {
        const errorPrompt = document.getElementById("customerError");
        if (errorPrompt) {
            errorPrompt.textContent = message;
            setTimeout(() => {
                errorPrompt.textContent = "";
            }, 2000);
        }
    }

    // Convert created_at dates to relative time format
    function convertCreatedAtDates() {
        const createdAtElements = document.querySelectorAll('.created-at');
        createdAtElements.forEach(element => {
            const createdAt = element.getAttribute('data-created-at');
            const now = moment();
            const createdAtMoment = moment.utc(createdAt).local();
            
            if (now.isSame(createdAtMoment, 'day')) {
                element.textContent = 'Just now';
            } else {
                const daysAgo = now.diff(createdAtMoment, 'days');
                element.textContent = `${daysAgo} days ago`;
            }
        });
    }

    // Convert dates on page load
    convertCreatedAtDates();

    // ----------------------------
    // ➕ ADD CUSTOMER LOGIC
    // ----------------------------

    // Open Add Customer Form Overlay
    window.openAddCustomerForm = function() {
        document.getElementById("customer-modal").style.display = "flex";
        document.getElementById("formType").value = "add";
        document.getElementById("modal-title").innerHTML = '<i class="fas fa-user-plus"></i> Add Customer';
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
        const addModal = document.getElementById("customer-modal");
        const deleteModal = document.getElementById("delete-modal");
        if (event.target === addModal) {
            closeAddCustomerForm();
        }
        if (event.target === deleteModal) {
            closeDeleteCustomerForm();
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
                showCustomToast("add", "Customer is being added", 5000, () => {
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

    // Open Edit Customer Form Overlay
    window.openEditCustomerForm = function(customerId, customerName) {
        document.getElementById("customer-modal").style.display = "flex";
        document.getElementById("formType").value = "edit";
        document.getElementById("modal-title").innerHTML = '<i class="fas fa-user-edit"></i> Edit Customer';

        // Pre-fill form fields
        document.getElementById("customer_id").value = customerId;
        document.getElementById("customer_name").value = customerName;
        const customerError = document.getElementById("customerError");
        if (customerError) {
            customerError.innerText = ""; // Clear error
        }
    }

    // Close Edit Customer Form Overlay
    window.closeEditCustomerForm = function() {
        document.getElementById("customer-modal").style.display = "none";
        document.getElementById("customer-form").reset();
        const customerError = document.getElementById("customerError");
        if (customerError) {
            customerError.innerText = ""; // Clear error
        }
    }

    // Submit Edit Customer Form with AJAX
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
                closeEditCustomerForm();
                showCustomToast("edit", "Customer is being edited", 5000, () => {
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
    // 🗑️ DELETE CUSTOMER LOGIC
    // ----------------------------

    // Open Delete Customer Form Overlay
    window.openDeleteCustomerForm = function(customerId) {
        document.getElementById("delete-modal").style.display = "flex";
        document.getElementById("delete_customer_id").value = customerId;
    }

    // Close Delete Customer Form Overlay
    window.closeDeleteCustomerForm = function() {
        document.getElementById("delete-modal").style.display = "none";
        document.getElementById("delete-form").reset();
    }

    // Submit Delete Customer Form with AJAX
    document.getElementById("delete-form").addEventListener("submit", function(event) {
        event.preventDefault();
        var formData = new FormData(this);
        formData.append("ajax", true);
        formData.append("formType", "delete");

        fetch("/backend/delete_customer.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeDeleteCustomerForm();
                showCustomToast("delete", "Customer is being deleted", 5000, () => {
                    window.location.reload();
                });
            } else {
                showCustomToast("delete", "Failed to delete customer.", 5000);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showCustomToast("delete", "An unexpected error occurred.", 5000);
        });
    });

    // Bind edit button click event to openEditCustomerForm function
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function () {
            openEditCustomerForm(this.dataset.id, this.dataset.name);
        });
    });

    // Bind delete button click event to openDeleteCustomerForm function
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function () {
            openDeleteCustomerForm(this.dataset.id);
        });
    });
});