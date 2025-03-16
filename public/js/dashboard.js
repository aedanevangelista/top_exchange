// Wait for the DOM to fully load
document.addEventListener("DOMContentLoaded", function () {

/*** ===========================
 *  CLIENT ORDERS PIE CHART
 *  =========================== ***/

// Pastel colors array
const pastelColors = [
    'rgba(190, 227, 219, 0.7)',   // Soft Mint
    'rgba(255, 214, 214, 0.7)',   // Soft Pink
    'rgba(214, 229, 250, 0.7)',   // Soft Blue
    'rgba(255, 241, 214, 0.7)',   // Soft Orange
    'rgba(233, 214, 250, 0.7)',   // Soft Purple
    'rgba(214, 250, 219, 0.7)',   // Soft Green
    'rgba(250, 229, 214, 0.7)',   // Soft Peach
    'rgba(229, 214, 250, 0.7)',   // Soft Lavender
    'rgba(214, 250, 250, 0.7)',   // Soft Cyan
    'rgba(250, 214, 236, 0.7)'    // Soft Rose
];

let clientOrdersChart = null;

// Function to initialize the chart
function initializeClientOrdersChart(data) {
    console.log('Initializing chart with data:', data); // Debug log

    const ctx = document.getElementById('clientOrdersChart');
    if (!ctx) {
        console.error('Canvas element not found!');
        return;
    }

    const chartContext = ctx.getContext('2d');
    
    const labels = data.map(item => item.username);
    const values = data.map(item => item.count);
    const colors = data.map((_, index) => pastelColors[index % pastelColors.length]);

    console.log('Chart data:', { labels, values, colors }); // Debug log

    if (clientOrdersChart) {
        console.log('Destroying existing chart');
        clientOrdersChart.destroy();
    }

    clientOrdersChart = new Chart(chartContext, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: colors,
                borderColor: colors.map(color => color.replace('0.7)', '1)')),
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

    console.log('Chart initialized!'); // Debug log
}

// Function to load years and populate dropdown
function loadYears() {
    console.log('Loading years...'); // Debug log
    
    fetch('/top_exchange/backend/get_available_years.php')
        .then(response => response.json())
        .then(years => {
            console.log('Years loaded:', years); // Debug log
            
            const yearSelect = document.getElementById('year-select');
            if (!yearSelect) {
                console.error('Year select element not found!');
                return;
            }

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
    console.log('Loading orders for year:', year); // Debug log
    
    fetch(`/top_exchange/backend/get_client_orders.php?year=${year}`)
        .then(response => response.json())
        .then(data => {
            console.log('Orders data received:', data); // Debug log
            initializeClientOrdersChart(data);
        })
        .catch(error => console.error('Error loading client orders:', error));
}

// Initialize everything when the document is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded'); // Debug log
    
    // Initialize the year select event listener
    const yearSelect = document.getElementById('year-select');
    if (yearSelect) {
        yearSelect.addEventListener('change', function() {
            console.log('Year changed to:', this.value); // Debug log
            loadClientOrders(this.value);
        });
    }

    // Load the years
    loadYears();
});

    /*** ===========================
     *  PACKS SOLD SINCE SECTION
     *  =========================== ***/
    const packsSoldYear = document.getElementById("packs-sold-year");
    const packsSoldCompareYear = document.getElementById("packs-sold-compare-year");
    const packsSoldCount = document.getElementById("packs-sold-count");
    const packsSoldPercentage = document.getElementById("packs-sold-percentage");

    // Dummy Sales Data
    const salesData = {
        2025: 4000,
        2024: 3600,
        2023: 3200
    };

    // Function to Update Packs Sold Display
    function updatePacksSold() {
        const selectedYear = packsSoldYear?.value;
        const compareYear = packsSoldCompareYear?.value;

        const currentSales = salesData[selectedYear] || 0;
        const previousSales = salesData[compareYear] || 0;

        // Update Packs Count
        if (packsSoldCount) {
            packsSoldCount.textContent = `${currentSales} packs`;
        }

        // Update Percentage Change
        if (previousSales > 0) {
            const percentageChange = (((currentSales - previousSales) / previousSales) * 100).toFixed(2);
            if (packsSoldPercentage) {
                packsSoldPercentage.textContent = `${percentageChange > 0 ? "+" : ""}${percentageChange}% Since`;
                packsSoldPercentage.style.color = percentageChange >= 0 ? "green" : "red";
            }
        } else {
            if (packsSoldPercentage) {
                packsSoldPercentage.textContent = "N/A Since";
                packsSoldPercentage.style.color = "black";
            }
        }
    }

    // Add Event Listeners to Dropdowns
    packsSoldYear?.addEventListener("change", updatePacksSold);
    packsSoldCompareYear?.addEventListener("change", updatePacksSold);

    // Initialize Display
    updatePacksSold();


    /*** ===========================
     *  SALES PER DEPARTMENT BAR CHART
     *  =========================== ***/
    const ctxSalesPerDepartment = document.getElementById("salesPerDepartmentChart")?.getContext("2d");

    // Dummy Data for Sales by Department
    const salesDepartmentData = {
        week: [1000, 800, 600, 500],
        month: [4000, 3500, 3200, 2800],
        year: [50000, 45000, 40000, 35000]
    };

    // Create Sales per Department Chart (Bar)
    let salesPerDepartmentChart = new Chart(ctxSalesPerDepartment, {
        type: "bar",
        data: {
            labels: ["Dimsum", "Siopao", "Noodles", "Ham"],
            datasets: [{
                label: "Packs Sold",
                data: salesDepartmentData.week, // Default to weekly
                backgroundColor: ["#4CAF50", "#FF9800", "#2196F3", "#9C27B0"],
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    grid: { display: false }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 5000
                    }
                }
            }
        }
    });

    // Update Chart on Dropdown Change
    const salesPerDepartmentFilter = document.getElementById("sales-per-department-filter");
    if (salesPerDepartmentFilter) {
        salesPerDepartmentFilter.addEventListener("change", function () {
            salesPerDepartmentChart.data.datasets[0].data = salesDepartmentData[this.value];
            salesPerDepartmentChart.update();
        });
    }

});
