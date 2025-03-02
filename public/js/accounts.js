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
        document.getElementById("accountOverlay").style.display = "flex";
        document.getElementById("addAccountError").innerText = ""; // Clear error
    }

    // Close Add Account Form Overlay
    window.closeAddAccountForm = function() {
        document.getElementById("accountOverlay").style.display = "none";
        document.getElementById("accountForm").reset();
        document.getElementById("addAccountError").innerText = "";
    }

    // Submit Add Account Form with AJAX
    const addAccountForm = document.getElementById("accountForm");
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
    window.openEditAccountForm = function(accountId, username, role_id) {
        editAccountId = accountId;
        document.getElementById("accountOverlay").style.display = "flex";

        // Pre-fill form fields with correct IDs
        document.getElementById("accountId").value = accountId;
        document.getElementById("username").value = username;
        document.getElementById("role_id").value = role_id;
        document.getElementById("editAccountError").innerText = "";

        const editForm = document.getElementById("accountForm");
        if (editForm) {
            editForm.addEventListener("submit", submitEditAccountForm);
        }
    }

    // Close Edit Account Form Overlay
    window.closeEditAccountForm = function() {
        document.getElementById("accountOverlay").style.display = "none";
        document.getElementById("accountForm").reset();
        document.getElementById("editAccountError").innerText = "";
        editAccountId = null;

        const editForm = document.getElementById("accountForm");
        if (editForm) {
            editForm.removeEventListener("submit", submitEditAccountForm);
        }
    }

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
                showCustomToast("edit", `Editing account ${document.getElementById("username").value}`, 5000, () => {
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
        if (event.target === document.getElementById("accountOverlay")) {
            closeAddAccountForm();
            closeEditAccountForm();
        }
        if (event.target === document.getElementById("deleteModal")) {
            closeDeleteModal();
        }
    };
});