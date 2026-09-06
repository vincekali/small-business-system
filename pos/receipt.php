<?php

require_once "../config/database.php";
require_once "../auth.php";

requireLogin();


// ==========================================
// 1. VALIDATE SALE ID
// ==========================================

if (
    !isset($_GET["id"]) ||
    !ctype_digit((string) $_GET["id"])
) {
    die("Invalid receipt.");
}

$sale_id = (int) $_GET["id"];

if ($sale_id <= 0) {
    die("Invalid receipt.");
}


// ==========================================
// 2. GET SALE INFORMATION
// ==========================================

$stmt = $conn->prepare("
    SELECT
        sales.id,
        sales.invoice_number,
        sales.user_id,
        sales.sale_date,
        sales.subtotal,
        sales.discount,
        sales.total_amount,
        sales.payment,
        sales.change_amount,
        users.full_name
    FROM sales
    LEFT JOIN users
        ON sales.user_id = users.id
    WHERE sales.id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Unable to load receipt.");
}

$stmt->bind_param(
    "i",
    $sale_id
);

if (!$stmt->execute()) {
    $stmt->close();
    die("Unable to load receipt.");
}

$sale_result = $stmt->get_result();

if ($sale_result->num_rows === 0) {
    $stmt->close();
    die("Sale not found.");
}

$sale = $sale_result->fetch_assoc();

$stmt->close();


// ==========================================
// 3. GET SALE ITEMS
// ==========================================

$stmt = $conn->prepare("
    SELECT
        sale_items.product_id,
        sale_items.quantity,
        sale_items.unit_price,
        sale_items.subtotal,
        products.product_name,
        products.product_code
    FROM sale_items
    INNER JOIN products
        ON sale_items.product_id = products.id
    WHERE sale_items.sale_id = ?
    ORDER BY sale_items.id ASC
");

if (!$stmt) {
    die("Unable to load receipt items.");
}

$stmt->bind_param(
    "i",
    $sale_id
);

if (!$stmt->execute()) {
    $stmt->close();
    die("Unable to load receipt items.");
}

$items = $stmt->get_result();

if ($items->num_rows === 0) {
    $stmt->close();
    die("This sale has no items.");
}

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
        Receipt -
        <?= htmlspecialchars(
            $sale["invoice_number"],
            ENT_QUOTES,
            "UTF-8"
        ) ?>
    </title>


    <style>

        /* ==========================================
           GENERAL
        ========================================== */

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            padding: 20px;

            background: #f3f4f6;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            color: #111827;

        }


        /* ==========================================
           RECEIPT
        ========================================== */

        .receipt {

            width: 380px;

            max-width: 100%;

            margin: 0 auto;

            background: white;

            padding: 25px;

            border-radius: 8px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.10);

        }


        /* ==========================================
           BUSINESS HEADER
        ========================================== */

        .business {

            text-align: center;

            padding-bottom: 15px;

            margin-bottom: 15px;

            border-bottom:
                1px dashed #9ca3af;

        }


        .business h1 {

            margin: 0 0 5px;

            color: #065f46;

            font-size: 23px;

        }


        .business p {

            margin: 3px 0;

            color: #6b7280;

            font-size: 12px;

        }


        .receipt-title {

            margin-top: 8px !important;

            color: #111827 !important;

            font-weight: bold;

        }


        /* ==========================================
           SALE INFORMATION
        ========================================== */

        .info {

            margin-bottom: 15px;

            font-size: 13px;

        }


        .info-row {

            display: flex;

            justify-content: space-between;

            align-items: flex-start;

            gap: 15px;

            margin: 6px 0;

        }


        .info-row strong,
        .info-row span:last-child {

            text-align: right;

            word-break: break-word;

        }


        /* ==========================================
           ITEMS
        ========================================== */

        .items {

            padding: 12px 0;

            border-top:
                1px dashed #9ca3af;

            border-bottom:
                1px dashed #9ca3af;

        }


        .item {

            padding: 10px 0;

            border-bottom:
                1px solid #f3f4f6;

        }


        .item:first-child {

            padding-top: 0;

        }


        .item:last-child {

            padding-bottom: 0;

            border-bottom: none;

        }


        .item-name {

            margin-bottom: 3px;

            font-weight: bold;

            font-size: 14px;

        }


        .item-code {

            margin-bottom: 5px;

            color: #6b7280;

            font-size: 11px;

        }


        .item-details {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 10px;

            font-size: 13px;

            color: #4b5563;

        }


        .item-details strong {

            color: #111827;

            white-space: nowrap;

        }


        /* ==========================================
           SUMMARY
        ========================================== */

        .summary {

            margin-top: 15px;

        }


        .summary-row {

            display: flex;

            justify-content: space-between;

            gap: 15px;

            margin: 8px 0;

            font-size: 13px;

        }


        .summary-row span:last-child,
        .summary-row strong:last-child {

            white-space: nowrap;

        }


        /* ==========================================
           TOTAL
        ========================================== */

        .total {

            margin-top: 12px;

            padding-top: 10px;

            border-top:
                2px solid #065f46;

            font-size: 20px;

            font-weight: bold;

            color: #065f46;

        }


        /* ==========================================
           CHANGE
        ========================================== */

        .change-row {

            font-weight: bold;

        }


        .change-amount {

            color: #059669;

        }


        /* ==========================================
           THANK YOU
        ========================================== */

        .thank-you {

            margin-top: 20px;

            padding-top: 15px;

            border-top:
                1px dashed #9ca3af;

            text-align: center;

            color: #6b7280;

            font-size: 12px;

        }


        /* ==========================================
           ACTION BUTTONS
        ========================================== */

        .actions {

            width: 380px;

            max-width: 100%;

            margin: 15px auto 0;

            display: flex;

            gap: 10px;

        }


        .actions button {

            flex: 1;

            padding: 12px;

            border: none;

            border-radius: 7px;

            font-weight: bold;

            cursor: pointer;

        }


        .print {

            background: #065f46;

            color: white;

        }


        .print:hover {

            background: #047857;

        }


        .pos {

            background: #e5e7eb;

            color: #111827;

        }


        .pos:hover {

            background: #d1d5db;

        }


        /* ==========================================
           MOBILE
        ========================================== */

        @media (max-width: 450px) {

            body {

                padding: 10px;

            }


            .receipt {

                width: 100%;

                padding: 20px;

                border-radius: 7px;

            }


            .actions {

                width: 100%;

                flex-direction: column;

            }


            .actions button {

                width: 100%;

            }

        }


        /* ==========================================
           PRINT
        ========================================== */

        @media print {

            @page {

                margin: 0;

            }


            body {

                padding: 0;

                background: white;

            }


            .receipt {

                width: 100%;

                max-width: none;

                margin: 0;

                padding: 10px;

                border-radius: 0;

                box-shadow: none;

            }


            .actions {

                display: none;

            }

        }

    </style>

</head>


<body>


<!-- ==========================================
     RECEIPT
========================================== -->

<div class="receipt">


    <!-- ======================================
         BUSINESS HEADER
    ======================================= -->

    <div class="business">

        <h1>
            Small Business
        </h1>

        <p>
            Financial Tracking &amp; POS
        </p>

        <p class="receipt-title">
            OFFICIAL SALES RECEIPT
        </p>

    </div>


    <!-- ======================================
         SALE INFORMATION
    ======================================= -->

    <div class="info">


        <div class="info-row">

            <span>
                Invoice:
            </span>

            <strong>

                <?= htmlspecialchars(
                    $sale["invoice_number"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </strong>

        </div>


        <div class="info-row">

            <span>
                Date:
            </span>

            <span>

                <?= htmlspecialchars(
                    date(
                        "M d, Y h:i A",
                        strtotime(
                            $sale["sale_date"]
                        )
                    ),
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </span>

        </div>


        <div class="info-row">

            <span>
                Cashier:
            </span>

            <span>

                <?= htmlspecialchars(
                    $sale["full_name"]
                    ?? "Cashier",
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </span>

        </div>


    </div>


    <!-- ======================================
         SALE ITEMS
    ======================================= -->

    <div class="items">


        <?php while (
            $item = $items->fetch_assoc()
        ): ?>


            <div class="item">


                <div class="item-name">

                    <?= htmlspecialchars(
                        $item["product_name"],
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                </div>


                <div class="item-code">

                    Code:
                    <?= htmlspecialchars(
                        $item["product_code"],
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                </div>


                <div class="item-details">


                    <span>

                        <?= (int) $item["quantity"] ?>

                        ×

                        ₱<?= number_format(
                            (float) $item["unit_price"],
                            2
                        ) ?>

                    </span>


                    <strong>

                        ₱<?= number_format(
                            (float) $item["subtotal"],
                            2
                        ) ?>

                    </strong>


                </div>


            </div>


        <?php endwhile; ?>


    </div>


    <!-- ======================================
         SUMMARY
    ======================================= -->

    <div class="summary">


        <!-- SUBTOTAL -->

        <div class="summary-row">

            <span>
                Subtotal
            </span>

            <span>

                ₱<?= number_format(
                    (float) $sale["subtotal"],
                    2
                ) ?>

            </span>

        </div>


        <!-- DISCOUNT -->

        <div class="summary-row">

            <span>
                Discount
            </span>

            <span>

                ₱<?= number_format(
                    (float) $sale["discount"],
                    2
                ) ?>

            </span>

        </div>


        <!-- TOTAL -->

        <div class="summary-row total">

            <span>
                TOTAL
            </span>

            <span>

                ₱<?= number_format(
                    (float) $sale["total_amount"],
                    2
                ) ?>

            </span>

        </div>


        <!-- PAYMENT -->

        <div class="summary-row">

            <span>
                Payment
            </span>

            <span>

                ₱<?= number_format(
                    (float) $sale["payment"],
                    2
                ) ?>

            </span>

        </div>


        <!-- CHANGE -->

        <div class="summary-row change-row">

            <span>
                Change
            </span>

            <strong class="change-amount">

                ₱<?= number_format(
                    (float) $sale["change_amount"],
                    2
                ) ?>

            </strong>

        </div>


    </div>


    <!-- ======================================
         THANK YOU
    ======================================= -->

    <div class="thank-you">

        Thank you for your purchase!

    </div>


</div>


<!-- ==========================================
     ACTIONS
========================================== -->

<div class="actions">


    <button
        type="button"
        class="print"
        onclick="window.print()"
    >
        Print Receipt
    </button>


    <button
        type="button"
        class="pos"
        onclick="window.location.href='index.php'"
    >
        Back to POS
    </button>


</div>


</body>

</html>

<?php

$stmt->close();

?>