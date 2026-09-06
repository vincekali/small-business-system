/*
|--------------------------------------------------------------------------
| SALES & PROFIT TREND
|--------------------------------------------------------------------------
*/

const trendLabels = window.dashboardData.trendLabels;
const trendSales = window.dashboardData.trendSales;
const trendProfit = window.dashboardData.trendProfit;

const salesProfitCanvas =
    document.getElementById("salesProfitChart");

const trendDescription =
    document.getElementById("trendDescription");

let salesProfitChart = null;


/*
|--------------------------------------------------------------------------
| CREATE SALES & PROFIT CHART
|--------------------------------------------------------------------------
*/

function createSalesProfitChart(
    labels,
    sales,
    profit
) {

    if (!salesProfitCanvas) {
        return;
    }

    salesProfitChart = new Chart(
        salesProfitCanvas,
        {

            type: "line",

            data: {

                labels: labels,

                datasets: [

                    {
                        label: "Sales",
                        data: sales,

                        borderColor: "#10b981",
                        backgroundColor:
                            "rgba(16, 185, 129, 0.10)",

                        borderWidth: 3,
                        tension: 0.35,

                        fill: true,

                        pointRadius: 4,
                        pointHoverRadius: 6
                    },

                    {
                        label: "Profit",
                        data: profit,

                        borderColor: "#065f46",
                        backgroundColor:
                            "rgba(6, 95, 70, 0.08)",

                        borderWidth: 3,
                        tension: 0.35,

                        fill: true,

                        pointRadius: 4,
                        pointHoverRadius: 6
                    }

                ]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                interaction: {
                    mode: "index",
                    intersect: false
                },

                plugins: {

                    legend: {
                        display: true,
                        position: "top"
                    },

                    tooltip: {

                        callbacks: {

                            label: function(context) {

                                return context.dataset.label +
                                    ": ₱" +
                                    Number(context.raw)
                                        .toLocaleString(
                                            "en-PH",
                                            {
                                                minimumFractionDigits: 2,
                                                maximumFractionDigits: 2
                                            }
                                        );

                            }

                        }

                    }

                },

                scales: {

                    y: {

                        beginAtZero: true,

                        ticks: {

                            callback: function(value) {

                                return "₱" +
                                    Number(value)
                                        .toLocaleString(
                                            "en-PH"
                                        );

                            }

                        }

                    }

                }

            }

        }
    );

}


/*
|--------------------------------------------------------------------------
| LOAD INITIAL CHART
|--------------------------------------------------------------------------
*/

createSalesProfitChart(
    trendLabels,
    trendSales,
    trendProfit
);


/*
|--------------------------------------------------------------------------
| PERIOD FILTERS
|--------------------------------------------------------------------------
*/

const periodButtons =
    document.querySelectorAll(".period-btn");


periodButtons.forEach(function(button) {

    button.addEventListener(
        "click",
        async function() {

            const period =
                this.dataset.period;


            /*
            |--------------------------------------------------------------
            | UPDATE ACTIVE BUTTON
            |--------------------------------------------------------------
            */

            periodButtons.forEach(function(btn) {

                btn.classList.remove("active");

            });

            this.classList.add("active");


            /*
            |--------------------------------------------------------------
            | UPDATE DESCRIPTION
            |--------------------------------------------------------------
            */

            if (period === "7") {

                trendDescription.textContent =
                    "Financial performance over the last 7 days";

            } else if (period === "30") {

                trendDescription.textContent =
                    "Financial performance over the last 30 days";

            } else {

                trendDescription.textContent =
                    "Financial performance for the current month";

            }


            /*
            |--------------------------------------------------------------
            | LOAD DATA
            |--------------------------------------------------------------
            */

            try {

                const response =
                    await fetch(
                        "dashboard_data.php?period=" +
                        encodeURIComponent(period)
                    );


                if (!response.ok) {

                    throw new Error(
                        "Failed to load dashboard data."
                    );

                }


                const data =
                    await response.json();


                if (!data.success) {

                    throw new Error(
                        data.message ||
                        "Unable to load chart data."
                    );

                }


                /*
                |----------------------------------------------------------
                | UPDATE CHART
                |----------------------------------------------------------
                */

                salesProfitChart.data.labels =
                    data.labels;

                salesProfitChart.data.datasets[0].data =
                    data.sales;

                salesProfitChart.data.datasets[1].data =
                    data.profit;

                salesProfitChart.update();

            }

            catch (error) {

                console.error(
                    "Dashboard trend error:",
                    error
                );

            }

        }
    );

});


/*
|--------------------------------------------------------------------------
| INVENTORY STATUS
|--------------------------------------------------------------------------
*/

const inventoryCanvas =
    document.getElementById("inventoryChart");


if (inventoryCanvas) {

    new Chart(inventoryCanvas, {

        type: "doughnut",

        data: {

            labels: [
                "In Stock",
                "Low Stock",
                "Out of Stock"
            ],

            datasets: [

                {

                    data: window.dashboardData.inventory,

                    backgroundColor: [
                        "#10b981",
                        "#f59e0b",
                        "#dc2626"
                    ],

                    borderWidth: 2,

                    borderColor: "#ffffff"

                }

            ]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            cutout: "65%",

            plugins: {

                legend: {

                    position: "bottom"

                },

                tooltip: {

                    callbacks: {

                        label: function(context) {

                            const value =
                                context.raw;

                            const total =
                                context.dataset.data.reduce(
                                    (sum, number) =>
                                        sum + number,
                                    0
                                );

                            const percentage =
                                total > 0
                                    ? (value / total) * 100
                                    : 0;

                            return context.label +
                                ": " +
                                value +
                                " (" +
                                percentage.toFixed(1) +
                                "%)";

                        }

                    }

                }

            }

        }

    });

}