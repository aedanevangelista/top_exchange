// Wait for the DOM to fully load
document.addEventListener("DOMContentLoaded", function () {

    /*** ===========================
     *  CLIENT ORDERS PIE CHART
     *  =========================== ***/
    const ctxClientOrders = document.getElementById("clientOrdersChart")?.getContext("2d");

    // Monthly and Yearly Data for Client Orders
    const monthlyData = {
        labels: ["Solaire", "City of Dreams", "STI"],
        datasets: [{
            data: [5000, 3000, 2000],
            backgroundColor: ["#FF6384", "#36A2EB", "#FFCE56"],
            hoverOffset: 4
        }]
    };

    const yearlyData = {
        labels: ["Solaire", "City of Dreams", "STI"],
        datasets: [{
            data: [25000, 18000, 12000],
            backgroundColor: ["#FF6384", "#36A2EB", "#FFCE56"],
            hoverOffset: 4
        }]
    };

    // Create Client Orders Chart (Pie)
    let clientOrdersChart = new Chart(ctxClientOrders, {
        type: 'pie',
        data: monthlyData
    });

    // Update Chart on Dropdown Change
    const clientOrdersFilter = document.getElementById("client-orders-filter");
    if (clientOrdersFilter) {
        clientOrdersFilter.addEventListener("change", function () {
            clientOrdersChart.data = this.value === "month" ? monthlyData : yearlyData;
            clientOrdersChart.update();
        });
    }

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
