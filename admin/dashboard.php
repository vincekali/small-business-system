<?php

require_once "../config/database.php";
require_once "../auth.php";

requireAdmin();


/*
|--------------------------------------------------------------------------
| TODAY'S FINANCIAL DATA
|--------------------------------------------------------------------------
*/

// Today's sales
$result = $conn->query("
    SELECT COALESCE(SUM(total_amount), 0) AS total_sales
    FROM sales
    WHERE DATE(sale_date) = CURDATE()
");

$today_sales = (float) $result->fetch_assoc()["total_sales"];


// Today's expenses
$result = $conn->query("
    SELECT COALESCE(SUM(amount), 0) AS total_expenses
    FROM expenses
    WHERE DATE(expense_date) = CURDATE()
");

$today_expenses = (float) $result->fetch_assoc()["total_expenses"];


// Today's product cost
$result = $conn->query("
    SELECT COALESCE(
        SUM(sale_items.cost_price * sale_items.quantity),
        0
    ) AS total_cost
    FROM sale_items
    INNER JOIN sales
        ON sale_items.sale_id = sales.id
    WHERE DATE(sales.sale_date) = CURDATE()
");

$today_cost = (float) $result->fetch_assoc()["total_cost"];


// Gross profit
$gross_profit = $today_sales - $today_cost;


// Net profit
$today_profit = $gross_profit - $today_expenses;


// Today's transaction count
$result = $conn->query("
    SELECT COUNT(*) AS total_transactions
    FROM sales
    WHERE DATE(sale_date) = CURDATE()
");

$today_transactions = (int) $result->fetch_assoc()["total_transactions"];


// Average sale
if ($today_transactions > 0) {
    $average_sale = $today_sales / $today_transactions;
} else {
    $average_sale = 0;
}


/*
|--------------------------------------------------------------------------
| GROSS MARGIN
|--------------------------------------------------------------------------
*/

if ($today_sales > 0) {
    $gross_margin = ($gross_profit / $today_sales) * 100;
} else {
    $gross_margin = 0;
}


/*
|--------------------------------------------------------------------------
| INVENTORY DATA
|--------------------------------------------------------------------------
*/

// Total products
$result = $conn->query("
    SELECT COUNT(*) AS total_products
    FROM products
");

$total_products = (int) $result->fetch_assoc()["total_products"];


// In-stock products
$result = $conn->query("
    SELECT COUNT(*) AS in_stock
    FROM products
    WHERE stock_quantity > reorder_level
");

$in_stock = (int) $result->fetch_assoc()["in_stock"];


// Low-stock products
$result = $conn->query("
    SELECT COUNT(*) AS low_stock
    FROM products
    WHERE stock_quantity > 0
      AND stock_quantity <= reorder_level
");

$low_stock = (int) $result->fetch_assoc()["low_stock"];


// Out-of-stock products
$result = $conn->query("
    SELECT COUNT(*) AS out_of_stock
    FROM products
    WHERE stock_quantity = 0
");

$out_of_stock = (int) $result->fetch_assoc()["out_of_stock"];


/*
|--------------------------------------------------------------------------
| LOW STOCK ALERTS
|--------------------------------------------------------------------------
*/

$low_stock_alerts = $conn->query("
    SELECT
        id,
        product_code,
        product_name,
        stock_quantity,
        reorder_level
    FROM products
    WHERE stock_quantity <= reorder_level
    ORDER BY
        stock_quantity ASC,
        product_name ASC
    LIMIT 6
");


/*
|--------------------------------------------------------------------------
| TOP-SELLING PRODUCTS
|--------------------------------------------------------------------------
*/

$top_products = $conn->query("
    SELECT
        p.product_name,
        p.product_code,
        SUM(si.quantity) AS units_sold,
        SUM(si.subtotal) AS revenue
    FROM sale_items si
    INNER JOIN products p
        ON si.product_id = p.id
    INNER JOIN sales s
        ON si.sale_id = s.id
    GROUP BY
        p.id,
        p.product_name,
        p.product_code
    ORDER BY
        units_sold DESC,
        revenue DESC
    LIMIT 5
");


/*
|--------------------------------------------------------------------------
| LAST 7 DAYS SALES AND PROFIT
|--------------------------------------------------------------------------
*/

$trend_labels = [];
$trend_sales = [];
$trend_profit = [];

$result = $conn->query("
    SELECT
        DATE(s.sale_date) AS sale_day,

        COALESCE(SUM(s.total_amount), 0) AS sales,

        COALESCE(
            (
                SELECT SUM(
                    si.cost_price * si.quantity
                )
                FROM sale_items si
                WHERE si.sale_id = s.id
            ),
            0
        ) AS product_cost

    FROM sales s

    WHERE s.sale_date >= CURDATE() - INTERVAL 6 DAY
      AND s.sale_date < CURDATE() + INTERVAL 1 DAY

    GROUP BY DATE(s.sale_date)

    ORDER BY sale_day ASC
");


// Store database results by date
$daily_data = [];

while ($row = $result->fetch_assoc()) {

    $date = $row["sale_day"];

    $sales = (float) $row["sales"];
    $cost = (float) $row["product_cost"];

    $daily_data[$date] = [
        "sales" => $sales,
        "profit" => $sales - $cost
    ];
}


// Create all 7 days, including days with no sales
for ($i = 6; $i >= 0; $i--) {

    $date = date(
        "Y-m-d",
        strtotime("-" . $i . " days")
    );

    $trend_labels[] = date(
        "M d",
        strtotime($date)
    );

    if (isset($daily_data[$date])) {

        $trend_sales[] = $daily_data[$date]["sales"];
        $trend_profit[] = $daily_data[$date]["profit"];

    } else {

        $trend_sales[] = 0;
        $trend_profit[] = 0;
    }
}


/*
|--------------------------------------------------------------------------
| RECENT SALES
|--------------------------------------------------------------------------
*/

$recent_sales = $conn->query("
    SELECT
        sales.id,
        sales.invoice_number,
        sales.sale_date,
        sales.total_amount,
        users.full_name

    FROM sales

    LEFT JOIN users
        ON sales.user_id = users.id

    ORDER BY sales.id DESC

    LIMIT 8
");


/*
|--------------------------------------------------------------------------
| PREPARE DATA FOR JAVASCRIPT
|--------------------------------------------------------------------------
*/

$trend_labels_json = json_encode(
    $trend_labels,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
);

$trend_sales_json = json_encode(
    $trend_sales,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
);

$trend_profit_json = json_encode(
    $trend_profit,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Admin Dashboard
    </title>


    <!-- GLOBAL CSS -->

    <link
        rel="stylesheet"
        href="../assets/css/global.css"
    >


    <!-- SIDEBAR CSS -->

    <link
        rel="stylesheet"
        href="../assets/css/sidebar.css"
    >


    <!-- COMPONENT CSS -->

    <link
        rel="stylesheet"
        href="../assets/css/components.css"
    >


    <!-- DASHBOARD CSS -->

    <link
        rel="stylesheet"
        href="../assets/css/dashboard.css"
    >

</head>


<body>


<!-- ========================================================
     SIDEBAR
======================================================== -->

<div class="sidebar" id="sidebar">

    <div class="sidebar-profile">

        <div class="profile-avatar">
            <?= strtoupper(
                substr(
                    $_SESSION["full_name"] ?? "U",
                    0,
                    1
                )
            ) ?>
        </div>

        <div class="profile-info">

            <strong>
                <?= htmlspecialchars(
                    $_SESSION["full_name"] ?? "User",
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </strong>

            <span>
                <?= htmlspecialchars(
                    ucfirst($_SESSION["role"] ?? "User"),
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </span>

        </div>


        <!-- MOBILE CLOSE BUTTON -->

        <button
            type="button"
            class="sidebar-close-btn"
            id="sidebarCloseBtn"
            aria-label="Close menu"
        >
            ×
        </button>

    </div>


    <nav class="sidebar-nav">

        <a
            href="dashboard.php"
            class="active"
        >
            Dashboard
        </a>


        <a href="products.php">
            Products
        </a>


        <a href="categories.php">
            Categories
        </a>


        <a href="users.php">
            User Management
        </a>


        <a href="../pos/index.php">
            Point of Sale
        </a>


        <a href="../inventory/index.php">
            Inventory
        </a>


        <a href="../reports/sales.php">
            Sales Reports
        </a>


        <a href="../expenses/index.php">
            Expenses
        </a>

    </nav>

</div>


<!-- ========================================================
     MOBILE OVERLAY
======================================================== -->

<div
    class="mobile-overlay"
    id="mobileOverlay">
</div>

<!-- ========================================================
     MOBILE OVERLAY
======================================================== -->

<div
    class="mobile-overlay"
    id="mobileOverlay">
</div>


<!-- ========================================================
     MAIN CONTENT
======================================================== -->

<div class="main">


    <!-- MOBILE MENU BUTTON -->

    <button
        type="button"
        class="mobile-menu-btn"
        id="mobileMenuBtn"
        aria-label="Open menu"
        aria-expanded="false"
    >

        <span></span>
        <span></span>
        <span></span>

    </button>


    <!-- ====================================================
         HEADER
    ==================================================== -->

    <div class="header">

        <h1>
            Dashboard
        </h1>

        <p>
            Business overview and financial performance
        </p>

    </div>


    <!-- ====================================================
         KPI CARDS
    ==================================================== -->

    <div class="cards">


        <!-- TODAY'S SALES -->

        <div class="card">

            <div class="card-title">
                Today's Sales
            </div>

            <div class="card-value">

                ₱<?= number_format(
                    $today_sales,
                    2
                ) ?>

            </div>

        </div>


        <!-- GROSS PROFIT -->

        <div class="card">

            <div class="card-title">
                Gross Profit
            </div>

            <div class="card-value profit">

                ₱<?= number_format(
                    $gross_profit,
                    2
                ) ?>

            </div>

        </div>


        <!-- EXPENSES -->

        <div class="card">

            <div class="card-title">
                Today's Expenses
            </div>

            <div class="card-value expense">

                ₱<?= number_format(
                    $today_expenses,
                    2
                ) ?>

            </div>

        </div>


        <!-- NET PROFIT -->

        <div class="card">

            <div class="card-title">
                Net Profit
            </div>

            <div class="card-value profit">

                ₱<?= number_format(
                    $today_profit,
                    2
                ) ?>

            </div>

        </div>


        <!-- TRANSACTIONS -->

        <div class="card">

            <div class="card-title">
                Transactions
            </div>

            <div class="card-value">

                <?= $today_transactions ?>

            </div>

        </div>

    </div>


    <!-- ====================================================
         MAIN DASHBOARD GRID
    ==================================================== -->

    <div class="dashboard-grid">


        <!-- ==================================================
             SALES & PROFIT TREND
        ================================================== -->

        <div class="dashboard-panel trend-panel">


            <div class="panel-header">


                <div>

                    <h2>
                        Sales &amp; Profit Trend
                    </h2>

                    <p id="trendDescription">
                        Financial performance over the last 7 days
                    </p>

                </div>


                <!-- PERIOD FILTERS -->

                <div class="period-filters">


                    <button
                        type="button"
                        class="period-btn active"
                        data-period="7"
                    >
                        7 Days
                    </button>


                    <button
                        type="button"
                        class="period-btn"
                        data-period="30"
                    >
                        30 Days
                    </button>


                    <button
                        type="button"
                        class="period-btn"
                        data-period="month"
                    >
                        This Month
                    </button>

                </div>

            </div>


            <div class="chart-container">

                <canvas
                    id="salesProfitChart"
                ></canvas>

            </div>

        </div>


        <!-- ==================================================
             INVENTORY STATUS
        ================================================== -->

        <div class="dashboard-panel inventory-panel">


            <div class="panel-header">


                <div>

                    <h2>
                        Inventory Status
                    </h2>

                    <p>
                        Current product stock condition
                    </p>

                </div>


                <a
                    href="../inventory/index.php"
                    class="inventory-link"
                >
                    View Inventory
                </a>

            </div>


            <div class="inventory-summary">


                <div class="inventory-item">

                    <span class="inventory-label">
                        In Stock
                    </span>

                    <strong class="inventory-number">
                        <?= $in_stock ?>
                    </strong>

                </div>


                <div class="inventory-item">

                    <span class="inventory-label">
                        Low Stock
                    </span>

                    <strong class="inventory-number warning">
                        <?= $low_stock ?>
                    </strong>

                </div>


                <div class="inventory-item">

                    <span class="inventory-label">
                        Out of Stock
                    </span>

                    <strong class="inventory-number danger">
                        <?= $out_of_stock ?>
                    </strong>

                </div>


                <div class="inventory-item">

                    <span class="inventory-label">
                        Total Products
                    </span>

                    <strong class="inventory-number">
                        <?= $total_products ?>
                    </strong>

                </div>

            </div>


            <div class="inventory-chart-container">

                <canvas
                    id="inventoryChart"
                ></canvas>

            </div>

        </div>

    </div>


    <!-- ====================================================
         INVENTORY ALERTS
    ==================================================== -->

    <div class="dashboard-panel stock-alert-panel">


        <div class="panel-header">


            <div>

                <h2>
                    Inventory Alerts
                </h2>

                <p>
                    Products that need stock attention
                </p>

            </div>


            <a
                href="../inventory/index.php"
                class="inventory-link"
            >
                Manage Inventory
            </a>

        </div>


        <?php if (
            $low_stock_alerts &&
            $low_stock_alerts->num_rows > 0
        ): ?>


            <div class="stock-alert-list">


                <?php while (
                    $product =
                    $low_stock_alerts->fetch_assoc()
                ): ?>


                    <?php

                    $isOutOfStock =
                        $product["stock_quantity"] <= 0;

                    ?>


                    <div class="stock-alert-item">


                        <div class="stock-product">

                            <strong>

                                <?= htmlspecialchars(
                                    $product["product_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </strong>

                            <span>

                                <?= htmlspecialchars(
                                    $product["product_code"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </span>

                        </div>


                        <div class="stock-info">


                            <span>

                                Stock:

                                <strong>
                                    <?= (int)
                                        $product["stock_quantity"] ?>
                                </strong>

                            </span>


                            <span>

                                Reorder Level:

                                <strong>
                                    <?= (int)
                                        $product["reorder_level"] ?>
                                </strong>

                            </span>

                        </div>


                        <div>


                            <?php if (
                                $isOutOfStock
                            ): ?>

                                <span class="stock-status out">
                                    Out of Stock
                                </span>

                            <?php else: ?>

                                <span class="stock-status low">
                                    Low Stock
                                </span>

                            <?php endif; ?>


                        </div>

                    </div>


                <?php endwhile; ?>


            </div>


        <?php else: ?>


            <div class="stock-alert-empty">

                <strong>
                    Inventory looks good
                </strong>

                <p>
                    No products are currently below their reorder level.
                </p>

            </div>


        <?php endif; ?>


    </div>


    <!-- ====================================================
         FINANCIAL SUMMARY + SALES PERFORMANCE
    ==================================================== -->

    <div class="dashboard-grid bottom-grid">


        <!-- FINANCIAL SUMMARY -->

        <div class="dashboard-panel">


            <div class="panel-header">

                <div>

                    <h2>
                        Financial Summary
                    </h2>

                    <p>
                        Today's business performance
                    </p>

                </div>

            </div>


            <div class="financial-list">


                <div class="financial-row">

                    <span>
                        Sales
                    </span>

                    <strong>

                        ₱<?= number_format(
                            $today_sales,
                            2
                        ) ?>

                    </strong>

                </div>


                <div class="financial-row">

                    <span>
                        Product Cost
                    </span>

                    <strong>

                        ₱<?= number_format(
                            $today_cost,
                            2
                        ) ?>

                    </strong>

                </div>


                <div class="financial-row">

                    <span>
                        Gross Profit
                    </span>

                    <strong class="profit">

                        ₱<?= number_format(
                            $gross_profit,
                            2
                        ) ?>

                    </strong>

                </div>


                <div class="financial-row">

                    <span>
                        Expenses
                    </span>

                    <strong class="expense">

                        ₱<?= number_format(
                            $today_expenses,
                            2
                        ) ?>

                    </strong>

                </div>


                <div class="financial-row total-row">

                    <span>
                        Net Profit
                    </span>

                    <strong class="profit">

                        ₱<?= number_format(
                            $today_profit,
                            2
                        ) ?>

                    </strong>

                </div>


            </div>

        </div>


        <!-- SALES PERFORMANCE -->

        <div class="dashboard-panel">


            <div class="panel-header">

                <div>

                    <h2>
                        Sales Performance
                    </h2>

                    <p>
                        Today's transaction statistics
                    </p>

                </div>

            </div>


            <div class="performance-list">


                <div class="performance-item">

                    <span>
                        Transactions
                    </span>

                    <strong>
                        <?= $today_transactions ?>
                    </strong>

                </div>


                <div class="performance-item">

                    <span>
                        Average Sale
                    </span>

                    <strong>

                        ₱<?= number_format(
                            $average_sale,
                            2
                        ) ?>

                    </strong>

                </div>


                <div class="performance-item">

                    <span>
                        Product Cost
                    </span>

                    <strong>

                        ₱<?= number_format(
                            $today_cost,
                            2
                        ) ?>

                    </strong>

                </div>


                <div class="performance-item">

                    <span>
                        Gross Margin
                    </span>

                    <strong class="profit">

                        <?= number_format(
                            $gross_margin,
                            1
                        ) ?>%

                    </strong>

                </div>


            </div>

        </div>

    </div>


    <!-- ====================================================
         TOP-SELLING PRODUCTS
    ==================================================== -->

    <div class="dashboard-panel top-products-panel">


        <div class="panel-header">


            <div>

                <h2>
                    Top-Selling Products
                </h2>

                <p>
                    Products ranked by units sold
                </p>

            </div>


            <a
                href="../reports/sales.php"
                class="inventory-link"
            >
                View Sales Reports
            </a>

        </div>


        <?php if (
            $top_products &&
            $top_products->num_rows > 0
        ): ?>


            <div class="top-products-list">


                <?php $rank = 1; ?>


                <?php while (
                    $product =
                    $top_products->fetch_assoc()
                ): ?>


                    <div class="top-product-item">


                        <div class="product-rank">

                            <?= $rank ?>

                        </div>


                        <div class="top-product-info">


                            <strong>

                                <?= htmlspecialchars(
                                    $product["product_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </strong>


                            <span>

                                <?= htmlspecialchars(
                                    $product["product_code"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </span>


                        </div>


                        <div class="units-sold">


                            <strong>

                                <?= (int)
                                    $product["units_sold"] ?>

                            </strong>


                            <span>
                                units sold
                            </span>


                        </div>


                        <div class="product-revenue">

                            ₱<?= number_format(
                                (float)
                                $product["revenue"],
                                2
                            ) ?>

                        </div>


                    </div>


                    <?php $rank++; ?>


                <?php endwhile; ?>


            </div>


        <?php else: ?>


            <div class="stock-alert-empty">

                <strong>
                    No sales yet
                </strong>

                <p>
                    Product sales will appear here after transactions are recorded.
                </p>

            </div>


        <?php endif; ?>


    </div>


    <!-- ====================================================
         RECENT TRANSACTIONS
    ==================================================== -->

    <div class="section">


        <h2>
            Recent Transactions
        </h2>


        <table>


            <thead>

                <tr>

                    <th>
                        Invoice
                    </th>

                    <th>
                        Date
                    </th>

                    <th>
                        Cashier
                    </th>

                    <th>
                        Total
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>


            <tbody>


                <?php if (
                    $recent_sales &&
                    $recent_sales->num_rows > 0
                ): ?>


                    <?php while (
                        $sale =
                        $recent_sales->fetch_assoc()
                    ): ?>


                        <tr>


                            <td>

                                <?= htmlspecialchars(
                                    $sale["invoice_number"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </td>


                            <td>

                                <?= date(
                                    "M d, Y h:i A",
                                    strtotime(
                                        $sale["sale_date"]
                                    )
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $sale["full_name"]
                                    ?? "Cashier",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </td>


                            <td>

                                ₱<?= number_format(
                                    (float)
                                    $sale["total_amount"],
                                    2
                                ) ?>

                            </td>


                            <td>

                                <a
                                    class="view-btn"
                                    href="../pos/receipt.php?id=<?= (int) $sale["id"] ?>"
                                >
                                    View
                                </a>

                            </td>


                        </tr>


                    <?php endwhile; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="5"
                            style="text-align:center;"
                        >
                            No transactions yet.
                        </td>

                    </tr>


                <?php endif; ?>


            </tbody>


        </table>


    </div>


</div>


<!-- ========================================================
     DASHBOARD DATA
======================================================== -->

<script>

window.dashboardData = {

    trendLabels:
        <?= $trend_labels_json ?>,

    trendSales:
        <?= $trend_sales_json ?>,

    trendProfit:
        <?= $trend_profit_json ?>,

    inventory: [

        <?= $in_stock ?>,

        <?= $low_stock ?>,

        <?= $out_of_stock ?>

    ]

};

</script>


<!-- ========================================================
     JAVASCRIPT
======================================================== -->

<script
    src="https://cdn.jsdelivr.net/npm/chart.js"
></script>

<script
    src="../assets/js/navigation.js"
></script>

<script
    src="../assets/js/dashboard.js"
></script>


</body>

</html>