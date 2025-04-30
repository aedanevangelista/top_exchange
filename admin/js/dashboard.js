// Wait for the DOM to fully load
document.addEventListener("DOMContentLoaded", function () {

    /*** ===========================
     *  CLIENT ORDERS PIE CHART
     *  =========================== ***/

    // Professional color array with 30 colors
    const chartColors = [
        'rgba(69, 160, 73, 0.85)',    // Green
        'rgba(71, 120, 209, 0.85)',   // Royal Blue
        'rgba(235, 137, 49, 0.85)',   // Orange
        'rgba(165, 84, 184, 0.85)',   // Purple
        'rgba(214, 68, 68, 0.85)',    // Red
        'rgba(60, 179, 163, 0.85)',   // Turquoise
        'rgba(201, 151, 63, 0.85)',   // Golden Brown
        'rgba(86, 120, 141, 0.85)',   // Steel Blue
        'rgba(182, 73, 141, 0.85)',   // Magenta
        'rgba(110, 146, 64, 0.85)',   // Olive Green
        'rgba(149, 82, 81, 0.85)',    // Rust
        'rgba(95, 61, 150, 0.85)',    // Deep Purple
        'rgba(170, 166, 57, 0.85)',   // Mustard
        'rgba(87, 144, 176, 0.85)',   // Sky Blue
        'rgba(192, 88, 116, 0.85)',   // Rose
        'rgba(85, 156, 110, 0.85)',   // Sea Green
        'rgba(161, 88, 192, 0.85)',   // Orchid
        'rgba(169, 106, 76, 0.85)',   // Brown
        'rgba(78, 130, 162, 0.85)',   // Blue Gray
        'rgba(190, 117, 50, 0.85)',   // Bronze
        'rgba(111, 83, 150, 0.85)',   // Lavender
        'rgba(158, 126, 74, 0.85)',   // Sand
        'rgba(92, 153, 123, 0.85)',   // Sage
        'rgba(173, 76, 104, 0.85)',   // Berry
        'rgba(67, 134, 147, 0.85)',   // Ocean Blue
        'rgba(187, 96, 68, 0.85)',    // Terra Cotta
        'rgba(124, 110, 163, 0.85)',  // Dusty Purple
        'rgba(146, 134, 54, 0.85)',   // Dark Yellow
        'rgba(82, 137, 110, 0.85)',   // Forest
        'rgba(155, 89, 136, 0.85)'    // Plum
    ];

    let clientOrdersChart = null;

    // Function to initialize the chart
    function initializeClientOrdersChart(data) {
        const ctx = document.getElementById('clientOrdersChart');
        if (!ctx) return;

        const chartContext = ctx.getContext('2d');
        
        const labels = data.map(item => item.username);
        const values = data.map(item => item.count);
        const colors = data.map((_, index) => chartColors[index % chartColors.length]);

        if (clientOrdersChart) {
            clientOrdersChart.destroy();
        }

        clientOrdersChart = new Chart(chartContext, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderColor: colors.map(color => color.replace('0.8)', '1)')),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 15,
                            padding: 15
                        }
                    },
                    title: {
                        display: true,
                        text: `Completed Orders Distribution - ${document.getElementById('year-select')?.value || new Date().getFullYear()}`,
                        font: {
                            size: 16
                        }
                    }
                }
            }
        });
    }

    // Function to load years and populate dropdown
    function loadYears() {
        // Updated path to include /admin prefix
        fetch('/admin/admin/backend/get_available_years_dashboard.php')
            .then(response => response.json())
            .then(years => {
                const yearSelect = document.getElementById('year-select');
                if (!yearSelect) return;

                yearSelect.innerHTML = years.map(year => 
                    `<option value="${year}">${year}</option>`
                ).join('');
                
                if (years.length > 0) {
                    loadClientOrders(years[0]);
                }
            })
            .catch(error => console.error('Error loading years:', error));
    }

    // Function to load client orders for a specific year
    function loadClientOrders(year) {
        // Updated path to include /admin prefix
        fetch(`/admin/admin/backend/get_client_orders.php?year=${year}`)
            .then(response => response.json())
            .then(data => {
                initializeClientOrdersChart(data);
            })
            .catch(error => console.error('Error loading client orders:', error));
    }

    // Initialize the year select event listener
    const yearSelect = document.getElementById('year-select');
    if (yearSelect) {
        yearSelect.addEventListener('change', function() {
            loadClientOrders(this.value);
        });
    }

    // Load the years when the page loads
    loadYears();

        /*** ===========================
     *  ORDERS SOLD SECTION
     *  =========================== ***/
    const ordersSoldYear = document.getElementById("packs-sold-year");
    const ordersSoldCompareYear = document.getElementById("packs-sold-compare-year");
    const ordersSoldCount = document.getElementById("packs-sold-count");
    const ordersSoldPercentage = document.getElementById("packs-sold-percentage");

    // Function to get order counts from database
    function getOrderCounts(year) {
        // Updated path to include /admin prefix
        return fetch(`/admin/admin/backend/get_order_counts.php?year=${year}`)
            .then(response => response.json())
            .catch(error => {
                console.error('Error fetching order counts:', error);
                return 0;
            });
    }

    // Function to Update Orders Sold Display
    async function updateOrdersSold() {
        const selectedYear = ordersSoldYear?.value;
        const compareYear = ordersSoldCompareYear?.value;

        // Get order counts for both years
        const currentOrders = await getOrderCounts(selectedYear);
        const previousOrders = await getOrderCounts(compareYear);

        // Update Orders Count
        if (ordersSoldCount) {
            ordersSoldCount.textContent = `${currentOrders} Orders`;
        }

        // Update Percentage Change
        if (previousOrders > 0) {
            const percentageChange = (((currentOrders - previousOrders) / previousOrders) * 100).toFixed(2);
            if (ordersSoldPercentage) {
                ordersSoldPercentage.textContent = `${percentageChange > 0 ? "+" : ""}${percentageChange}% since`;
                ordersSoldPercentage.style.color = percentageChange >= 0 ? "#45A049" : "#FF4444";
            }
        } else {
            if (ordersSoldPercentage) {
                ordersSoldPercentage.textContent = "N/A since";
                ordersSoldPercentage.style.color = "#666";
            }
        }
    }

    // Function to populate year dropdowns
    function populateYearDropdowns() {
        // Updated path to include /admin prefix
        fetch('/admin/admin/backend/get_available_years_dashboard.php')
            .then(response => response.json())
            .then(years => {
                if (years.length > 0) {
                    // Sort years in descending order
                    years.sort((a, b) => b - a);
                    
                    // Populate main year dropdown
                    if (ordersSoldYear) {
                        ordersSoldYear.innerHTML = years.map(year => 
                            `<option value="${year}">${year}</option>`
                        ).join('');
                    }

                    // Populate comparison year dropdown
                    if (ordersSoldCompareYear) {
                        ordersSoldCompareYear.innerHTML = years.map(year => 
                            `<option value="${year}">${year}</option>`
                        ).join('');
                        
                        // Select the second year by default for comparison
                        if (years.length > 1) {
                            ordersSoldCompareYear.selectedIndex = 1;
                        }
                    }

                    // Initial update
                    updateOrdersSold();
                }
            })
            .catch(error => console.error('Error loading years:', error));
    }

    // Add Event Listeners to Dropdowns
    ordersSoldYear?.addEventListener("change", updateOrdersSold);
    ordersSoldCompareYear?.addEventListener("change", updateOrdersSold);

    // Initialize when page loads
    populateYearDropdowns();


    /*** ===========================
 *  SALES PER DEPARTMENT BAR CHART
 *  =========================== ***/
const ctxSalesPerDepartment = document.getElementById("salesPerDepartmentChart")?.getContext("2d");
let salesPerDepartmentChart = null; // Define the chart variable globally

// Function to load sales data by category
function loadSalesByCategory() {
    // Updated path to include /admin prefix
    fetch('/admin/admin/backend/get_sales_by_category.php')
        .then(response => response.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.error) {
                    console.error('Server Error:', data.message);
                    return;
                }

                // Destroy existing chart if it exists
                if (salesPerDepartmentChart) {
                    salesPerDepartmentChart.destroy();
                }

                // Create Sales per Department Chart (Bar)
                salesPerDepartmentChart = new Chart(ctxSalesPerDepartment, {
                    type: "bar",
                    data: {
                        labels: data.categories,
                        datasets: [
                            {
                                label: `${data.currentYear.year} Sales`,
                                data: data.currentYear.data,
                                backgroundColor: "#28a745", // Green
                                borderWidth: 1,
                                borderRadius: 5
                            },
                            {
                                label: `${data.lastYear.year} Sales`,
                                data: data.lastYear.data,
                                backgroundColor: "#999999", // Gray
                                borderWidth: 1,
                                borderRadius: 5
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            title: {
                                display: true,
                                text: 'Sales by Department Comparison',
                                font: {
                                    size: 16
                                }
                            }
                        },
                        scales: {
                             y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of Orders',
                                    font: {
                                        size: 14,
                                        weight: 'bold'
                                    }
                                },
                                ticks: {
                                    callback: function(value) {
                                        const values = this.chart.data.datasets.flatMap(d => d.data);
                                        const max = Math.max(...values);
                                        // Only return values for 0 and max
                                        return value === 0 || value === max ? value : '';
                                    },
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            x: {
                                ticks: {
                                    autoSkip: false,
                                }
                            }
                        }
                    }
                });
            } catch (e) {
                console.error('Error parsing response:', text);
                console.error('Parse error:', e);
            }
        })
        .catch(error => console.error('Error loading sales data:', error));
}

// Call the function when the document loads
if (ctxSalesPerDepartment) {
    loadSalesByCategory();
}

});