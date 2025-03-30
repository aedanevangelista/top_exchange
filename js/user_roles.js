function saveRolePermissions(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const roleName = formData.get('role_name').trim().toLowerCase();

    fetch('/backend/get_roles_and_pages.php')
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
    fetch('/backend/save_role_permissions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            showErrorMessage(data.message);
        } else {
            alert(data.message);
            hideRoleForm();
            location.reload();
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

function showRoleForm(roleId = '', roleName = '', pages = '') {
    document.getElementById("roleFormTitle").innerHTML = roleId ? '<i class="fas fa-edit"></i> Edit Role' : '<i class="fas fa-user-plus"></i> Add Role';
    document.getElementById("actionType").value = roleId ? 'edit' : 'add';
    document.getElementById("roleId").value = roleId;
    document.getElementById("roleName").value = roleName;
    document.getElementById("roleError").style.display = "none";
    
    document.querySelectorAll("input[name='page_ids[]']").forEach(checkbox => {
        checkbox.checked = false;
    });
    if (pages) {
        let pageArray = pages.split(", ");
        document.querySelectorAll("input[name='page_ids[]']").forEach(checkbox => {
            if (pageArray.includes(checkbox.value)) {
                checkbox.checked = true;
            }
        });
    }
    document.getElementById("roleOverlay").style.display = "block";
}

function hideRoleForm() {
    document.getElementById("roleOverlay").style.display = "none";
}