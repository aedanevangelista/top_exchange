document.addEventListener('DOMContentLoaded', function () {
    const addCustomerBtn = document.getElementById('add-customer-btn');
    const customerModal = document.getElementById('customer-modal');
    const closeModal = document.getElementsByClassName('close')[0];
    const customerForm = document.getElementById('customer-form');
    const modalTitle = document.getElementById('modal-title');
    const formType = document.getElementById('formType');
    const customerId = document.getElementById('customer_id');
    const customerName = document.getElementById('customer_name');
    const contactNumber = document.getElementById('contact_number');
    const email = document.getElementById('email');
    const address = document.getElementById('address');
    const cancelBtn = document.querySelector('.cancel-btn');

    addCustomerBtn.onclick = function () {
        modalTitle.textContent = 'Add Customer';
        formType.value = 'add';
        customerForm.reset();
        customerModal.style.display = 'flex';
    };

    if (closeModal) {
        closeModal.onclick = function () {
            customerModal.style.display = 'none';
        };
    }

    if (cancelBtn) {
        cancelBtn.onclick = function () {
            customerModal.style.display = 'none';
        };
    }

    window.onclick = function (event) {
        if (event.target == customerModal) {
            customerModal.style.display = 'none';
        }
    };

    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function () {
            modalTitle.textContent = 'Edit Customer';
            formType.value = 'edit';
            customerId.value = this.dataset.id;
            fetch(`/top_exchange/backend/get_customer.php?id=${this.dataset.id}`)
                .then(response => response.json())
                .then(data => {
                    customerName.value = data.customer_name;
                    contactNumber.value = data.contact_number;
                    email.value = data.email;
                    address.value = data.address;
                    customerModal.style.display = 'flex';
                });
        });
    });

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

    customerForm.addEventListener('submit', function (event) {
        event.preventDefault();
        const formData = new FormData(customerForm);
        formData.append('ajax', true);

        fetch(`/top_exchange/backend/save_customer.php`, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Customer saved successfully.');
                    location.reload();
                } else {
                    alert('Failed to save customer.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    });
});