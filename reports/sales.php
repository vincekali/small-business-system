<?php

require_once "../config/database.php";
require_once "../auth.php";

requireAdmin();


// =====================================================
// HELPER: SAFE MONEY VALUE
// =====================================================

function getMoneyValue($value): float
{
    if (
        !is_numeric($value) ||
        !is_finite((float) $value)
    ) {
        return 0.00;
    }

    return (float) $value;
}


// =====================================================
// DATE FILTER
// =====================================================

$start_date =
    $_GET["start_date"] ??
    date("Y-m-01");

$end_date =
    $_GET["end_date"] ??
    date("Y-m-d");


// =====================================================
// VALIDATE DATE FORMAT
// =====================================================

$start_object =
    DateTime::createFromFormat(
        "Y-m-d",
        $start_date
    );

$end_object =
    DateTime::createFromFormat(
        "Y-m-d",
        $end_date
    );


if (
    !$start_object ||
    !$end_object ||
    $start_object->format("Y-m-d") !== $start_date ||
    $end_object->format("Y-m-d") !== $end_date
) {

    die("Invalid date range.");

}


// =====================================================
// VALIDATE DATE ORDER
// =====================================================

if ($start_date > $end_date) {

    die(
        "Start date cannot be later than end date."
    );

}


// =====================================================
// DATE RANGE
// =====================================================

$start_datetime =
    $start_date . " 00:00:00";


$end_object->modify("+1 day");


$end_datetime =
    $end_object->format("Y-m-d")
    . " 00:00:00";


// =====================================================
// SALES SUMMARY
// =====================================================

$stmt = $conn->prepare("
    SELECT
        COALESCE(
            SUM(total_amount),
            0
        ) AS total_sales,

        COALESCE(
            SUM(discount),
            0
        ) AS total_discount,

        COUNT(*) AS transaction_count

    FROM sales

    WHERE sale_date >= ?
      AND sale_date < ?
");


if (!$stmt) {

    die(
        "Unable to generate sales summary."
    );

}


$stmt->bind_param(
    "ss",
    $start_datetime,
    $end_datetime
);


if (!$stmt->execute()) {

    $stmt->close();

    die(
        "Unable to generate sales summary."
    );

}


$summary_result =
    $stmt->get_result();


$summary =
    $summary_result->fetch_assoc();


$stmt->close();


$total_sales =
    getMoneyValue(
        $summary["total_sales"] ?? 0
    );


$total_discount =
    getMoneyValue(
        $summary["total_discount"] ?? 0
    );


$transaction_count =
    (int) (
        $summary["transaction_count"] ?? 0
    );


// =====================================================
// COST OF GOODS SOLD
// =====================================================

$stmt = $conn->prepare("
    SELECT
        COALESCE(
            SUM(
                sale_items.cost_price
                * sale_items.quantity
            ),
            0
        ) AS total_cost

    FROM sale_items

    INNER JOIN sales
        ON sale_items.sale_id =
           sales.id

    WHERE sales.sale_date >= ?
      AND sales.sale_date < ?
");


if (!$stmt) {

    die(
        "Unable to calculate product cost."
    );

}


$stmt->bind_param(
    "ss",
    $start_datetime,
    $end_datetime
);


if (!$stmt->execute()) {

    $stmt->close();

    die(
        "Unable to calculate product cost."
    );

}


$cost_result =
    $stmt->get_result();


$cost_data =
    $cost_result->fetch_assoc();


$stmt->close();


$total_cost =
    getMoneyValue(
        $cost_data["total_cost"] ?? 0
    );


// =====================================================
// GROSS PROFIT
// =====================================================

$gross_profit =
    $total_sales -
    $total_cost;


// =====================================================
// GROSS MARGIN
// =====================================================

$gross_margin = 0;

if ($total_sales > 0) {

    $gross_margin =
        (
            $gross_profit /
            $total_sales
        ) * 100;

}


// =====================================================
// EXPENSES
// =====================================================

$stmt = $conn->prepare("
    SELECT
        COALESCE(
            SUM(amount),
            0
        ) AS total_expenses

    FROM expenses

    WHERE expense_date >= ?
      AND expense_date < ?
");


if (!$stmt) {

    die(
        "Unable to calculate expenses."
    );

}


$stmt->bind_param(
    "ss",
    $start_datetime,
    $end_datetime
);


if (!$stmt->execute()) {

    $stmt->close();

    die(
        "Unable to calculate expenses."
    );

}


$expense_result =
    $stmt->get_result();


$expense_data =
    $expense_result->fetch_assoc();


$stmt->close();


$total_expenses =
    getMoneyValue(
        $expense_data["total_expenses"] ?? 0
    );


// =====================================================
// NET PROFIT
// =====================================================

$net_profit =
    $gross_profit -
    $total_expenses;


// =====================================================
// AVERAGE SALE
// =====================================================

$average_sale = 0;

if ($transaction_count > 0) {

    $average_sale =
        $total_sales /
        $transaction_count;

}


// =====================================================
// SALES LIST
// =====================================================

$stmt = $conn->prepare("
    SELECT
        sales.id,
        sales.invoice_number,
        sales.sale_date,
        sales.subtotal,
        sales.discount,
        sales.total_amount,
        sales.payment,
        sales.change_amount,
        users.full_name

    FROM sales

    LEFT JOIN users
        ON sales.user_id =
           users.id

    WHERE sales.sale_date >= ?
      AND sales.sale_date < ?

    ORDER BY sales.sale_date DESC
");


if (!$stmt) {

    die(
        "Unable to load sales."
    );

}


$stmt->bind_param(
    "ss",
    $start_datetime,
    $end_datetime
);


if (!$stmt->execute()) {

    $stmt->close();

    die(
        "Unable to load sales."
    );

}


$sales =
    $stmt->get_result();

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
        Sales Reports
    </title>


    <link
        rel="stylesheet"
        href="../assets/css/global.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/sidebar.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/components.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/reports.css"
    >

</head>


<body>


<!-- ==========================================
     MOBILE MENU BUTTON
========================================== -->

<button
    type="button"
    id="mobileMenuBtn"
    class="mobile-menu-btn"
    aria-label="Open navigation"
    aria-expanded="false"
>

    <span></span>
    <span></span>
    <span></span>

</button>


<!-- ==========================================
     SIDEBAR
========================================== -->

<div
    class="sidebar"
    id="sidebar"
>


    <!-- PROFILE -->

    <div class="sidebar-profile">


        <div class="profile-avatar">

            <?= htmlspecialchars(
                strtoupper(
                    substr(
                        $_SESSION["full_name"] ?? "U",
                        0,
                        1
                    )
                ),
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </div>


        <div class="profile-info">

            <strong>

                <?= htmlspecialchars(
                    $_SESSION["full_name"] ??
                    "User",
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </strong>


            <span>

                <?= htmlspecialchars(
                    ucfirst(
                        $_SESSION["role"] ??
                        "User"
                    ),
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </span>

        </div>


        <!-- MOBILE CLOSE -->

        <button
            type="button"
            id="sidebarCloseBtn"
            class="sidebar-close-btn"
            aria-label="Close navigation"
        >
            ×
        </button>


    </div>


    <!-- NAVIGATION -->

    <div class="sidebar-nav">


        <a href="../admin/dashboard.php">
            Dashboard
        </a>


        <a href="../admin/products.php">
            Products
        </a>


        <a href="../admin/categories.php">
            Categories
        </a>


        <a href="../admin/users.php">
            User Management
        </a>


        <a href="../pos/index.php">
            Point of Sale
        </a>


        <a href="../inventory/index.php">
            Inventory
        </a>


        <a
            href="sales.php"
            class="active"
        >
            Sales Reports
        </a>


        <a href="../expenses/index.php">
            Expenses
        </a>


    </div>


</div>


<!-- ==========================================
     MOBILE OVERLAY
========================================== -->

<div
    class="mobile-overlay"
    id="mobileOverlay"
></div>


<!-- ==========================================
     MAIN
========================================== -->

<div class="main">


    <!-- ======================================
         HEADER
    ======================================= -->

    <div class="header">

        <h1>
            Sales Reports
        </h1>

        <p>
            View sales and financial performance
        </p>

    </div>


    <!-- ======================================
         DATE FILTER
    ======================================= -->

    <div class="filter-box">


        <form
            method="GET"
            class="filter-form"
        >


            <div class="filter-group">

                <label for="start_date">
                    Start Date
                </label>

                <input
                    type="date"
                    id="start_date"
                    name="start_date"
                    value="<?= htmlspecialchars(
                        $start_date,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                    required
                >

            </div>


            <div class="filter-group">

                <label for="end_date">
                    End Date
                </label>

                <input
                    type="date"
                    id="end_date"
                    name="end_date"
                    value="<?= htmlspecialchars(
                        $end_date,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                    required
                >

            </div>


            <button
                type="submit"
                class="filter-btn"
            >
                Generate Report
            </button>


        </form>


    </div>


    <!-- ======================================
         REPORT PERIOD
    ======================================= -->

    <div class="report-period">

        Report Period:

        <strong>

            <?= htmlspecialchars(
                date(
                    "M d, Y",
                    strtotime($start_date)
                ),
                ENT_QUOTES,
                "UTF-8"
            ) ?>

            -

            <?= htmlspecialchars(
                date(
                    "M d, Y",
                    strtotime($end_date)
                ),
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </strong>

    </div>


    <!-- ======================================
         FINANCIAL SUMMARY
    ======================================= -->

    <div class="cards">


        <!-- TOTAL SALES -->

        <div class="card">

            <div class="card-title">
                Total Sales
            </div>

            <div class="card-value">

                ₱<?= number_format(
                    $total_sales,
                    2
                ) ?>

            </div>

        </div>


        <!-- PRODUCT COST -->

        <div class="card">

            <div class="card-title">
                Product Cost
            </div>

            <div class="card-value">

                ₱<?= number_format(
                    $total_cost,
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
                Expenses
            </div>

            <div class="card-value expense">

                ₱<?= number_format(
                    $total_expenses,
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
                    $net_profit,
                    2
                ) ?>

            </div>

        </div>


        <!-- GROSS MARGIN -->

        <div class="card">

            <div class="card-title">
                Gross Margin
            </div>

            <div class="card-value profit">

                <?= number_format(
                    $gross_margin,
                    2
                ) ?>%

            </div>

        </div>


    </div>


    <!-- ======================================
         ADDITIONAL METRICS
    ======================================= -->

    <div class="metrics">


        <div class="metric">

            <span>
                Total Transactions
            </span>

            <strong>
                <?= number_format(
                    $transaction_count
                ) ?>
            </strong>

        </div>


        <div class="metric">

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


        <div class="metric">

            <span>
                Total Discounts
            </span>

            <strong>

                ₱<?= number_format(
                    $total_discount,
                    2
                ) ?>

            </strong>

        </div>


    </div>


    <!-- ======================================
         TRANSACTIONS
    ======================================= -->

    <div class="table-box">


        <div class="table-header">

            <div>

                <h2>
                    Transactions
                </h2>

                <p>
                    Sales recorded during the selected period
                </p>

            </div>


            <div class="transaction-count">

                <?= number_format(
                    $transaction_count
                ) ?>

                transaction(s)

            </div>

        </div>


        <div class="table-container">


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
                            Subtotal
                        </th>

                        <th>
                            Discount
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
                        $sales->num_rows > 0
                    ): ?>


                        <?php while (
                            $sale =
                            $sales->fetch_assoc()
                        ): ?>


                            <tr>


                                <!-- INVOICE -->

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $sale[
                                                "invoice_number"
                                            ],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <?= htmlspecialchars(
                                        date(
                                            "M d, Y h:i A",
                                            strtotime(
                                                $sale[
                                                    "sale_date"
                                                ]
                                            )
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </td>


                                <!-- CASHIER -->

                                <td>

                                    <?= htmlspecialchars(
                                        $sale[
                                            "full_name"
                                        ] ??
                                        "Cashier",
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </td>


                                <!-- SUBTOTAL -->

                                <td>

                                    ₱<?= number_format(
                                        getMoneyValue(
                                            $sale[
                                                "subtotal"
                                            ]
                                        ),
                                        2
                                    ) ?>

                                </td>


                                <!-- DISCOUNT -->

                                <td>

                                    ₱<?= number_format(
                                        getMoneyValue(
                                            $sale[
                                                "discount"
                                            ]
                                        ),
                                        2
                                    ) ?>

                                </td>


                                <!-- TOTAL -->

                                <td>

                                    <strong>

                                        ₱<?= number_format(
                                            getMoneyValue(
                                                $sale[
                                                    "total_amount"
                                                ]
                                            ),
                                            2
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- ACTION -->

                                <td>

                                    <a
                                        href="../pos/receipt.php?id=<?= (int) $sale["id"] ?>"
                                        class="view"
                                    >
                                        View Receipt
                                    </a>

                                </td>


                            </tr>


                        <?php endwhile; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="7"
                                class="empty"
                            >

                                No transactions found
                                for this date range.

                            </td>

                        </tr>


                    <?php endif; ?>


                </tbody>


            </table>


        </div>


    </div>


</div>


<script
    src="../assets/js/navigation.js"
></script>


</body>

</html>

<?php

$stmt->close();

?>