document.addEventListener('DOMContentLoaded', function() {
    // Populate users table
    fetch('/top_exchange/backend/get_users.php?status=active')
        .then(response => response.json())
        .then(data => populateUsers(data));

    document.getElementById('show-active').addEventListener('click', function() {
        fetch('/top_exchange/backend/get_users.php?status=active')
            .then(response => response.json())
            .then(data => populateUsers(data));
    });

    document.getElementById('show-inactive').addEventListener('click', function() {
        fetch('/top_exchange/backend/get_users.php?status=inactive')
            .then(response => response.json())
            .then(data => populateUsers(data));
    });

    // Event delegation for tabs
    document.querySelector('.tabs').addEventListener('click', function(event) {
        if (event.target.tagName === 'BUTTON') {
            const year = event.target.dataset.year;
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.getElementById(`tab-${year}`).classList.add('active');

            document.querySelectorAll('.tabs button').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
        }
    });
});

function populateUsers(users) {
    const userRecords = document.getElementById('user_records');
    userRecords.innerHTML = '';
    users.forEach(user => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${user.username}</td>
            <td>${user.status}</td>
            <td><button onclick="viewTransactionHistory(${user.id})">View Transaction History</button></td>
        `;
        userRecords.appendChild(row);
    });
}

function viewTransactionHistory(userId) {
    fetch(`/top_exchange/backend/get_transaction_history.php?user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
            const tabs = document.querySelector('.tabs');
            const tabContentContainer = document.querySelector('.tab-content');

            tabs.innerHTML = '';
            tabContentContainer.innerHTML = '';

            data.years.forEach(year => {
                // Create tab button
                const tabButton = document.createElement('button');
                tabButton.textContent = year.year;
                tabButton.dataset.year = year.year;
                tabs.appendChild(tabButton);

                // Create tab content
                const tabContent = document.createElement('div');
                tabContent.id = `tab-${year.year}`;
                tabContent.classList.add('tab-content');

                const table = document.createElement('table');
                table.classList.add('table-transactions');
                table.innerHTML = `
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${year.transactions.map(transaction => `
                            <tr>
                                <td>${transaction.month}</td>
                                <td>${transaction.total_amount}</td>
                                <td>${transaction.status}</td>
                                <td><button onclick="togglePaymentStatus(${transaction.id})">${transaction.status === 'Paid' ? 'Mark as Unpaid' : 'Mark as Paid'}</button></td>
                            </tr>
                        `).join('')}
                    </tbody>
                `;
                tabContent.appendChild(table);
                tabContentContainer.appendChild(tabContent);
            });

            // Activate the first tab
            if (tabs.querySelector('button')) {
                tabs.querySelector('button').classList.add('active');
                tabContentContainer.querySelector('.tab-content').classList.add('active');
            }

            openModal();
        });
}

function openModal() {
    document.getElementById('transactionModal').style.display = "block";
}

function closeModal() {
    document.getElementById('transactionModal').style.display = "none";
}

function togglePaymentStatus(transactionId) {
    // Update payment status in the backend (implement this in your backend PHP)
    fetch(`/top_exchange/backend/toggle_payment_status.php?transaction_id=${transactionId}`, { method: 'POST' })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Refresh the transaction history modal
                closeModal();
                viewTransactionHistory(data.userId);
            }
        });
}