document.addEventListener('DOMContentLoaded', function() {
    fetchRolesAndPages();
    document.getElementById('addRoleForm').addEventListener('submit', saveRolePermissions);
});

function fetchRolesAndPages() {
    fetch('/top_exchange/backend/get_roles_and_pages.php')
        .then(response => response.json())
        .then(data => {
            const roleDropdown = document.getElementById('roleDropdown');
            const pagesCheckboxes = document.getElementById('pagesCheckboxes');

            // Populate roles dropdown
            data.roles.forEach(role => {
                const option = document.createElement('option');
                option.value = role.role_id;
                option.textContent = role.role_name;
                roleDropdown.appendChild(option);
            });

            // Populate pages checkboxes
            data.pages.forEach(page => {
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.value = page.page_id;
                checkbox.name = 'page_ids[]';
                checkbox.id = 'page_' + page.page_id;

                const label = document.createElement('label');
                label.htmlFor = 'page_' + page.page_id;
                label.textContent = page.page_name;

                const div = document.createElement('div');
                div.appendChild(checkbox);
                div.appendChild(label);
                pagesCheckboxes.appendChild(div);
            });
        });
}

function saveRolePermissions(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    fetch('/top_exchange/backend/save_role_permissions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            closeAddRoleModal();
        }
    });
}

function closeAddRoleModal() {
    document.getElementById('addRoleModal').style.display = 'none';
}