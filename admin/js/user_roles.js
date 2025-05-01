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
    
    // Uncheck all checkboxes first
    document.querySelectorAll("input[name='page_ids[]']").forEach(checkbox => {
        checkbox.checked = false;
    });
    document.querySelectorAll(".module-checkbox").forEach(checkbox => {
        checkbox.checked = false;
    });
    
    // If editing, check the appropriate boxes
    if (pages) {
        let pageArray = pages.split(", ");
        document.querySelectorAll("input[name='page_ids[]']").forEach(checkbox => {
            if (pageArray.includes(checkbox.value)) {
                checkbox.checked = true;
            }
        });
        
        // Check if all pages in a module are selected
        updateModuleCheckboxes();
    }
    
    document.getElementById("roleOverlay").style.display = "block";
}

function hideRoleForm() {
    document.getElementById("roleOverlay").style.display = "none";
}

// New function to toggle all pages in a module when the module checkbox is clicked
function toggleModulePages(moduleId) {
    const moduleCheckbox = document.getElementById(`module_${moduleId}`);
    const pageCheckboxes = document.querySelectorAll(`.module-${moduleId}-checkbox`);
    
    pageCheckboxes.forEach(checkbox => {
        checkbox.checked = moduleCheckbox.checked;
    });
}

// Function to update module checkboxes based on page selection
function updateModuleCheckboxes() {
    const modules = document.querySelectorAll('.module-checkbox');
    
    modules.forEach(moduleCheckbox => {
        const moduleId = moduleCheckbox.value;
        const modulePages = document.querySelectorAll(`.module-${moduleId}-checkbox`);
        const checkedPages = document.querySelectorAll(`.module-${moduleId}-checkbox:checked`);
        
        // If all pages of this module are checked, also check the module checkbox
        if (modulePages.length > 0 && checkedPages.length === modulePages.length) {
            moduleCheckbox.checked = true;
        }
        // If some pages are checked, but not all, make module checkbox indeterminate
        else if (checkedPages.length > 0) {
            moduleCheckbox.indeterminate = true;
        }
        // If no pages are checked, uncheck the module checkbox
        else {
            moduleCheckbox.checked = false;
            moduleCheckbox.indeterminate = false;
        }
    });
}

// Add event listeners to page checkboxes to update module status
document.addEventListener('DOMContentLoaded', function() {
    const pageCheckboxes = document.querySelectorAll('.page-checkbox');
    pageCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateModuleCheckboxes();
        });
    });
});