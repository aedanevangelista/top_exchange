// ----------------------------
// 🗑️ DELETE ACCOUNT LOGIC
// ----------------------------

let deleteAccountId = null;

// Open Delete Confirmation Modal
function openDeleteModal(accountId, username) {
    deleteAccountId = accountId;
    document.getElementById("deleteMessage").innerText = `Are you sure you want to delete the account '${username}'?`;
    document.getElementById("deleteModal").style.display = "flex";
}

// Close Delete Confirmation Modal
function closeDeleteModal() {
    document.getElementById("deleteModal").style.display = "none";
    deleteAccountId = null; // Reset
}

// Confirm Account Deletion with AJAX
function confirmDeletion() {
    if (!deleteAccountId) return;

    fetch("/top_exchange/backend/delete_account.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `id=${deleteAccountId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessPrompt('Account deleted successfully');
            setTimeout(() => window.location.reload(), 500);
        } else {
            alert("Error: " + data.message);
        }
        closeDeleteModal();
    })
    .catch(error => {
        console.error("Error:", error);
    });
}

// ----------------------------
// ➕ ADD ACCOUNT LOGIC
// ----------------------------

// Open Add Account Form Overlay
function openAddAccountForm() {
    document.getElementById("addAccountOverlay").style.display = "flex";
    document.getElementById("addAccountError").innerText = ""; // Clear error
}

// Close Add Account Form Overlay
function closeAddAccountForm() {
    document.getElementById("addAccountOverlay").style.display = "none";
    document.getElementById("addAccountForm").reset();
    document.getElementById("addAccountError").innerText = "";
}

// Submit Add Account Form with AJAX
document.querySelector(".account-form").addEventListener("submit", function(event) {
    event.preventDefault();
    const formData = new FormData(this);
    formData.append('ajax', 'true');

    fetch("/top_exchange/public/pages/accounts.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessPrompt('Account added successfully');
            setTimeout(() => window.location.reload(), 500);
        } else {
            document.getElementById("addAccountError").innerText = "Username already exists or an error occurred.";
        }
    })
    .catch(error => {
        console.error("Error:", error);
    });
});

// ----------------------------
// ✏️ EDIT ACCOUNT LOGIC (UPDATED)
// ----------------------------

let editAccountId = null;

// Open Edit Account Form Overlay
function openEditAccountForm(accountId, username, role) {
    editAccountId = accountId;
    document.getElementById("editAccountOverlay").style.display = "flex";

    // Pre-fill form fields with correct IDs
    document.getElementById("edit-username").value = username;
    document.getElementById("edit-role").value = role;
    document.getElementById("editAccountError").innerText = "";

    const editForm = document.getElementById("editAccountForm");
    if (editForm) {
        editForm.addEventListener("submit", submitEditAccountForm);
    }
}

// Close Edit Account Form Overlay
function closeEditAccountForm() {
    document.getElementById("editAccountOverlay").style.display = "none";
    document.getElementById("editAccountForm").reset();
    document.getElementById("editAccountError").innerText = "";
    editAccountId = null;

    const editForm = document.getElementById("editAccountForm");
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
            showSuccessPrompt('Account updated successfully');
            setTimeout(() => window.location.reload(), 500);
        } else {
            document.getElementById("editAccountError").innerText = data.message || "Failed to update account.";
        }
    })
    .catch(error => {
        console.error("Error:", error);
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