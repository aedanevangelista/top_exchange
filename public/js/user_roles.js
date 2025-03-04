function saveRolePermissions(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const roleName = formData.get('role_name').trim().toLowerCase();

    // Fetch existing roles to check for duplicates
    fetch('/top_exchange/backend/get_roles_and_pages.php')
        .then(response => response.json())
        .then(data => {
            const existingRoles = data.roles.map(role => role.role_name.toLowerCase());

            if (existingRoles.includes(roleName)) {
                showErrorMessage('Role name already exists. Please choose a different name.');
            } else {
                submitRoleForm(formData);
            }
        });
}

function submitRoleForm(formData) {
    fetch('/top_exchange/backend/save_role_permissions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            showErrorMessage(data.message);
        } else {
            alert(data.message);
            closeAddRoleModal();
            location.reload(); // Refresh to update roles
        }
    });
}

function showErrorMessage(message) {
    let errorDiv = document.getElementById('roleError');
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.id = 'roleError';
        errorDiv.style.color = 'red';
        document.getElementById('roleFormTitle').after(errorDiv);
    }
    errorDiv.textContent = message;
}
