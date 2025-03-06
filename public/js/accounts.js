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
        const errorPrompt = isEdit ? document.getElementById("editAccountError") : document.getElementById("addAccountError");
        if (errorPrompt) {
            console.log("Error prompt element found:", errorPrompt);
            errorPrompt.textContent = message;
            setTimeout(() => {
                errorPrompt.textContent = "";
            }, 2000);
        } else {
            console.log("Error prompt element not found.");
        }
    }

    // ----------------------------
    // ➕ ADD ACCOUNT LOGIC
    // ----------------------------

    // Open Add Account Form Overlay
    window.openAddAccountForm = function() {
        document.getElementById("addAccountOverlay").style.display = "flex";
        document.getElementById("addAccountError").innerText = ""; // Clear error
    }

    // Close Add Account Form Overlay
    window.closeAddAccountForm = function() {
        document.getElementById("addAccountOverlay").style.display = "none";
        document.getElementById("addAccountForm").reset();
        document.getElementById("addAccountError").innerText = "";
    }

    // Submit Add Account Form with AJAX
    const addAccountForm = document.getElementById("addAccountForm");
    if (addAccountForm) {
        addAccountForm.addEventListener("submit", function(event) {
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
                    closeAddAccountForm();
                    showCustomToast("add", "Adding account", 5000, () => {
                        window.location.reload();
                    });
                } else {
                    showErrorPrompt(data.message || "Username already exists.");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                showErrorPrompt("An unexpected error occurred.");
            });
        });
    }

    // ----------------------------
    // ✏️ EDIT ACCOUNT LOGIC
    // ----------------------------

    let editAccountId = null;

    // Open Edit Account Form Overlay
    function openEditAccountForm(id, username, role) {
        $('#edit-id').val(id);
        $('#edit-username').val(username);
        $('#edit-role').val(role);
        $('#editAccountOverlay').show();
    }
    
    function closeEditAccountForm() {
        $('#editAccountOverlay').hide();
    }
    
    $('#editAccountForm').on('submit', function (e) {
        e.preventDefault();
    
        var formData = $(this).serialize();
        $.post('/path/to/your/php/file', formData, function (response) {
            if (response.success) {
                toastr.success('Account updated successfully!');
                if (response.reload) {
                    location.reload();
                }
            } else {
                toastr.error(response.message || 'Failed to update account.');
            }
        }, 'json').fail(function (xhr, status, error) {
            toastr.error('An error occurred: ' + error);
        });
    });

    // Submit Edit Account Form with AJAX
    function submitEditAccountForm(event) {
        event.preventDefault();

        const formData = new FormData(event.target);
        formData.append('account_id', editAccountId);  // Correct key
        formData.append('ajax', 'true');    

        fetch("/top_exchange/backend/update_account.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log("Response data:", data); // Debugging line
            if (data.success) {
                closeEditAccountForm();
                showCustomToast("edit", `Editing account ${document.getElementById("edit-username").value}`, 5000, () => {
                    window.location.reload();
                });
            } else {
                showErrorPrompt(data.message || "An unexpected error occurred.", true);
            }
        })
        .catch(error => {
            console.error("Error:", error);
            showErrorPrompt("An unexpected error occurred.", true);
        });
    }

    // ----------------------------
    // 🗑️ DELETE ACCOUNT LOGIC
    // ----------------------------

    let deleteAccountId = null;

    // Open Delete Confirmation Modal
    window.openDeleteModal = function(accountId, username) {
        deleteAccountId = accountId;
        document.getElementById("deleteMessage").innerText = `Are you sure you want to delete the account '${username}'?`;
        document.getElementById("deleteModal").style.display = "flex";
    }

    // Close Delete Confirmation Modal
    window.closeDeleteModal = function() {
        document.getElementById("deleteModal").style.display = "none";
        deleteAccountId = null; // Reset
    }

    // Confirm Account Deletion with AJAX
    window.confirmDeletion = function() {
        if (!deleteAccountId) return;

        fetch("/top_exchange/backend/delete_account.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `id=${deleteAccountId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeDeleteModal();
                const usernameElement = document.querySelector(`[data-id='${deleteAccountId}'] .username`);
                const username = usernameElement ? usernameElement.textContent : 'unknown';
                showCustomToast("delete", `Deleting account ${username}`, 5000, () => {
                    window.location.reload();
                });
            } else {
                showErrorPrompt(data.message || "Failed to delete account.");
            }
        })
        .catch(error => {
            console.error("Error:", error);
            showErrorPrompt("An unexpected error occurred.");
        });
    }

    // ----------------------------
    // 🟢 SUCCESS PROMPT LOGIC
    // ----------------------------

    function showSuccessPrompt(message) {
        const prompt = document.createElement('div');
        prompt.className = 'success-prompt';
        prompt.innerText = message;

        document.body.appendChild(prompt);
        setTimeout(() => {
            prompt.classList.add('visible');
            setTimeout(() => {
                prompt.classList.remove('visible');
                setTimeout(() => prompt.remove(), 300);
            }, 2500);
        }, 100);
    }

    // ----------------------------
    // 🟡 OVERLAY CLOSING ON OUTSIDE CLICK
    // ----------------------------

    window.onclick = function(event) {
        if (event.target === document.getElementById("addAccountOverlay")) {
            closeAddAccountForm();
        }
        if (event.target === document.getElementById("editAccountOverlay")) {
            closeEditAccountForm();
        }
        if (event.target === document.getElementById("deleteModal")) {
            closeDeleteModal();
        }
    };

    // ----------------------------
    // 📋 ROLE MANAGEMENT LOGIC
    // ----------------------------

    window.openAddRoleForm = function() {
        document.getElementById("addRoleForm").reset();
        document.getElementById("addRoleOverlay").style.display = "flex";
    }

    window.closeAddRoleForm = function() {
        document.getElementById("addRoleOverlay").style.display = "none";
    }

    window.openEditRoleForm = function(role_id) {
        document.getElementById("editRoleForm").reset();
        document.getElementById("editRoleId").value = role_id;
        $.ajax({
            url: '/top_exchange/backend/get_role_permissions.php',
            method: 'GET',
            data: { role_id: role_id },
            success: function(data) {
                const roleData = JSON.parse(data);
                $('#editRoleName').val(roleData.role_name);
                $('input[type="checkbox"]').prop('checked', false);
                roleData.pages.forEach(function(page) {
                    $('#edit_page_' + page).prop('checked', true);
                });
                document.getElementById('editRoleOverlay').style.display = 'flex';
            },
            error: function(xhr, status, error) {
                console.error("Error fetching role permissions:", status, error);
                showErrorPrompt("An error occurred while fetching role permissions.");
            }
        });
    }

    window.closeEditRoleForm = function() {
        document.getElementById("editRoleOverlay").style.display = "none";
    }

    // Submit Add Role Form with AJAX
    const addRoleForm = document.getElementById("addRoleForm");
    if (addRoleForm) {
        addRoleForm.addEventListener("submit", function(event) {
            event.preventDefault();
            var formData = new FormData(this);

            fetch("/top_exchange/backend/update_role.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeAddRoleForm();
                    showCustomToast("add", "Adding role", 5000, () => {
                        window.location.reload();
                    });
                } else {
                    showErrorPrompt(data.message || "An unexpected error occurred.");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                showErrorPrompt("An unexpected error occurred.");
            });
        });
    }

    // Submit Edit Role Form with AJAX
    const editRoleForm = document.getElementById("editRoleForm");
    if (editRoleForm) {
        editRoleForm.addEventListener("submit", function(event) {
            event.preventDefault();
            var formData = new FormData(this);

            fetch("/top_exchange/backend/update_role.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeEditRoleForm();
                    showCustomToast("edit", "Editing role", 5000, () => {
                        window.location.reload();
                    });
                } else {
                    showErrorPrompt(data.message || "An unexpected error occurred.");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                showErrorPrompt("An unexpected error occurred.");
            });
        });
    }

    var roleEditButtons = document.querySelectorAll(".edit-btn");
    roleEditButtons.forEach(function(btn) {
        btn.addEventListener("click", function() {
            var roleId = btn.getAttribute("data-role-id");
            openEditRoleForm(roleId);
        });
    });
});