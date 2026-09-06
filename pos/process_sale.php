<?php

require_once "../config/database.php";
require_once "../auth.php";

requireLogin();

header("Content-Type: application/json; charset=UTF-8");


// =====================================================
// HELPER: SEND JSON RESPONSE
// =====================================================

function sendResponse(
    bool $success,
    string $message,
    array $data = []
): void {

    echo json_encode(
        array_merge(
            [
                "success" => $success,
                "message" => $message
            ],
            $data
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


// =====================================================
// HELPER: CONVERT MONEY TO CENTS
// =====================================================

function moneyToCents($value): ?int
{
    if (
        !is_int($value) &&
        !is_float($value) &&
        !is_string($value)
    ) {
        return null;
    }

    if (
        is_string($value) &&
        trim($value) === ""
    ) {
        return null;
    }

    if (!is_numeric($value)) {
        return null;
    }

    $number = (float) $value;

    if (!is_finite($number)) {
        return null;
    }

    /*
     * Convert once to cents.
     * All calculations after this use integers
     * to avoid floating-point money errors.
     */
    return (int) round(
        $number * 100
    );
}


// =====================================================
// HELPER: CENTS TO DATABASE DECIMAL
// =====================================================

function centsToDecimal(int $cents): string
{
    return number_format(
        $cents / 100,
        2,
        ".",
        ""
    );
}


// =====================================================
// HELPER: CENTS TO JSON NUMBER
// =====================================================

function centsToNumber(int $cents): float
{
    return $cents / 100;
}


// =====================================================
// HELPER: VALID POSITIVE INTEGER
// =====================================================

function positiveInteger($value): ?int
{
    if (is_int($value)) {

        $number = $value;

    } elseif (
        is_string($value) &&
        preg_match('/^[0-9]+$/', $value)
    ) {

        $number = (int) $value;

    } else {

        return null;
    }

    if ($number <= 0) {
        return null;
    }

    return $number;
}


// =====================================================
// 1. CHECK REQUEST METHOD
// =====================================================

if (
    $_SERVER["REQUEST_METHOD"] !== "POST"
) {

    sendResponse(
        false,
        "Invalid request."
    );
}


// =====================================================
// 2. VERIFY SESSION INFORMATION
// =====================================================

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"])
) {

    sendResponse(
        false,
        "User session expired. Please login again."
    );
}


$user_id =
    intval($_SESSION["user_id"]);

$user_role =
    $_SESSION["role"];


if ($user_id <= 0) {

    sendResponse(
        false,
        "User session expired. Please login again."
    );
}


if (
    !in_array(
        $user_role,
        ["admin", "cashier"],
        true
    )
) {

    sendResponse(
        false,
        "Invalid user account."
    );
}


// =====================================================
// 3. READ JSON REQUEST
// =====================================================

$raw_data =
    file_get_contents("php://input");


if (
    $raw_data === false ||
    trim($raw_data) === ""
) {

    sendResponse(
        false,
        "Invalid sale data."
    );
}


$data =
    json_decode(
        $raw_data,
        true
    );


if (
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($data)
) {

    sendResponse(
        false,
        "Invalid sale data."
    );
}


// =====================================================
// 4. VALIDATE CART
// =====================================================

if (
    !isset($data["cart"]) ||
    !is_array($data["cart"]) ||
    count($data["cart"]) === 0
) {

    sendResponse(
        false,
        "Cart is empty."
    );
}


/*
 * Protect server from extremely large,
 * unreasonable cart requests.
 */
if (
    count($data["cart"]) > 500
) {

    sendResponse(
        false,
        "Cart contains too many products."
    );
}


$submitted_cart = [];


// =====================================================
// 5. BASIC CART ITEM VALIDATION
// =====================================================

foreach (
    $data["cart"] as $item
) {

    if (
        !is_array($item) ||
        !array_key_exists("id", $item) ||
        !array_key_exists("quantity", $item)
    ) {

        sendResponse(
            false,
            "Invalid cart item."
        );
    }


    $product_id =
        positiveInteger(
            $item["id"]
        );


    $quantity =
        positiveInteger(
            $item["quantity"]
        );


    if ($product_id === null) {

        sendResponse(
            false,
            "Invalid product."
        );
    }


    if ($quantity === null) {

        sendResponse(
            false,
            "Invalid quantity."
        );
    }


    /*
     * Prevent unrealistic or malicious
     * quantity submissions.
     */
    if ($quantity > 1000000) {

        sendResponse(
            false,
            "Invalid quantity."
        );
    }


    /*
     * Reject the same product appearing
     * more than once in the submitted cart.
     */
    if (
        isset(
            $submitted_cart[$product_id]
        )
    ) {

        sendResponse(
            false,
            "Duplicate product in cart."
        );
    }


    $submitted_cart[$product_id] = [
        "product_id" => $product_id,
        "quantity" => $quantity
    ];
}


// =====================================================
// 6. VALIDATE DISCOUNT / PAYMENT
// =====================================================

$discount_cents =
    moneyToCents(
        $data["discount"] ?? 0
    );


$payment_cents =
    moneyToCents(
        $data["payment"] ?? 0
    );


if ($discount_cents === null) {

    sendResponse(
        false,
        "Invalid discount."
    );
}


if ($payment_cents === null) {

    sendResponse(
        false,
        "Invalid payment."
    );
}


if ($discount_cents < 0) {

    sendResponse(
        false,
        "Discount cannot be negative."
    );
}


if ($payment_cents < 0) {

    sendResponse(
        false,
        "Payment cannot be negative."
    );
}


// =====================================================
// 7. SORT PRODUCT IDS
// =====================================================

/*
 * Always lock products in the same ID order.
 *
 * This reduces the chance of database deadlocks
 * when multiple cashiers process sales at once.
 */
$product_ids =
    array_keys(
        $submitted_cart
    );


sort(
    $product_ids,
    SORT_NUMERIC
);


// =====================================================
// 8. START DATABASE TRANSACTION
// =====================================================

try {

    if (!$conn->begin_transaction()) {

        throw new Exception(
            "DATABASE_ERROR"
        );
    }


    $validated_cart = [];

    $subtotal_cents = 0;


    // =================================================
    // 9. LOCK AND VALIDATE PRODUCTS
    // =================================================

    $product_stmt =
        $conn->prepare("
            SELECT
                id,
                product_name,
                selling_price,
                cost_price,
                stock_quantity
            FROM products
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");


    if (!$product_stmt) {

        throw new Exception(
            "DATABASE_ERROR"
        );
    }


    foreach (
        $product_ids as $product_id
    ) {

        $quantity =
            $submitted_cart[$product_id]["quantity"];


        $product_stmt->bind_param(
            "i",
            $product_id
        );


        if (
            !$product_stmt->execute()
        ) {

            throw new Exception(
                "DATABASE_ERROR"
            );
        }


        $result =
            $product_stmt->get_result();


        $product =
            $result->fetch_assoc();


        if (!$product) {

            throw new Exception(
                "Product not found."
            );
        }


        // ---------------------------------------------
        // CURRENT STOCK
        // ---------------------------------------------

        $current_stock =
            intval(
                $product["stock_quantity"]
            );


        if (
            $current_stock < $quantity
        ) {

            throw new Exception(
                "Not enough stock for " .
                $product["product_name"] .
                "."
            );
        }


        // ---------------------------------------------
        // DATABASE SELLING PRICE
        // ---------------------------------------------

        $unit_price_cents =
            moneyToCents(
                $product["selling_price"]
            );


        if (
            $unit_price_cents === null ||
            $unit_price_cents < 0
        ) {

            throw new Exception(
                "DATABASE_ERROR"
            );
        }


        // ---------------------------------------------
        // DATABASE COST PRICE
        // ---------------------------------------------

        $cost_price_cents =
            moneyToCents(
                $product["cost_price"]
            );


        if (
            $cost_price_cents === null ||
            $cost_price_cents < 0
        ) {

            throw new Exception(
                "DATABASE_ERROR"
            );
        }


        // ---------------------------------------------
        // CALCULATE LINE SUBTOTAL
        // ---------------------------------------------

        $item_subtotal_cents =
            $unit_price_cents *
            $quantity;


        /*
         * Basic overflow protection.
         */
        if (
            $item_subtotal_cents < 0 ||
            $subtotal_cents >
                PHP_INT_MAX -
                $item_subtotal_cents
        ) {

            throw new Exception(
                "Invalid sale amount."
            );
        }


        $subtotal_cents +=
            $item_subtotal_cents;


        // ---------------------------------------------
        // STORE AUTHORITATIVE DB VALUES
        // ---------------------------------------------

        $validated_cart[$product_id] = [

            "product_id" =>
                $product_id,

            "product_name" =>
                $product["product_name"],

            "quantity" =>
                $quantity,

            "unit_price_cents" =>
                $unit_price_cents,

            "cost_price_cents" =>
                $cost_price_cents,

            "item_subtotal_cents" =>
                $item_subtotal_cents,

            "current_stock" =>
                $current_stock

        ];
    }


    $product_stmt->close();


    // =================================================
    // 10. VALIDATE SUBTOTAL
    // =================================================

    if ($subtotal_cents <= 0) {

        throw new Exception(
            "Invalid sale amount."
        );
    }


    // =================================================
    // 11. VALIDATE DISCOUNT
    // =================================================

    /*
     * Do NOT silently reduce an invalid discount.
     * Reject it instead.
     */
    if (
        $discount_cents >
        $subtotal_cents
    ) {

        throw new Exception(
            "Discount cannot exceed subtotal."
        );
    }


    // =================================================
    // 12. CASHIER DISCOUNT LIMIT
    // =================================================

    if (
        $user_role === "cashier"
    ) {

        /*
         * Maximum cashier discount = 10%.
         * Integer cents are used so money stays exact.
         */
        $max_discount_cents =
            intdiv(
                $subtotal_cents * 10,
                100
            );


        if (
            $discount_cents >
            $max_discount_cents
        ) {

            throw new Exception(
                "Cashier discount cannot exceed 10%."
            );
        }
    }


    // =================================================
    // 13. CALCULATE TOTAL
    // =================================================

    $total_cents =
        $subtotal_cents -
        $discount_cents;


    if ($total_cents < 0) {

        throw new Exception(
            "Invalid sale amount."
        );
    }


    // =================================================
    // 14. CHECK PAYMENT
    // =================================================

    if (
        $payment_cents <
        $total_cents
    ) {

        throw new Exception(
            "Payment is not enough."
        );
    }


    $change_cents =
        $payment_cents -
        $total_cents;


    // =================================================
    // 15. GENERATE UNIQUE INVOICE NUMBER
    // =================================================

    $invoice_number = null;


    $invoice_check_stmt =
        $conn->prepare("
            SELECT id
            FROM sales
            WHERE invoice_number = ?
            LIMIT 1
        ");


    if (!$invoice_check_stmt) {

        throw new Exception(
            "DATABASE_ERROR"
        );
    }


    for (
        $attempt = 1;
        $attempt <= 10;
        $attempt++
    ) {

        $candidate =
            "INV-" .
            date("YmdHis") .
            "-" .
            random_int(
                100000,
                999999
            );


        $invoice_check_stmt->bind_param(
            "s",
            $candidate
        );


        if (
            !$invoice_check_stmt->execute()
        ) {

            throw new Exception(
                "DATABASE_ERROR"
            );
        }


        $result =
            $invoice_check_stmt->get_result();


        if (
            $result->num_rows === 0
        ) {

            $invoice_number =
                $candidate;

            break;
        }
    }


    $invoice_check_stmt->close();


    if ($invoice_number === null) {

        throw new Exception(
            "Unable to generate a unique invoice number."
        );
    }


    // =================================================
    // 16. CONVERT TOTALS FOR DATABASE
    // =================================================

    $subtotal_db =
        centsToDecimal(
            $subtotal_cents
        );


    $discount_db =
        centsToDecimal(
            $discount_cents
        );


    $total_db =
        centsToDecimal(
            $total_cents
        );


    $payment_db =
        centsToDecimal(
            $payment_cents
        );


    $change_db =
        centsToDecimal(
            $change_cents
        );


    // =================================================
    // 17. SAVE SALE
    // =================================================

    $sale_stmt =
        $conn->prepare("
            INSERT INTO sales
            (
                invoice_number,
                user_id,
                subtotal,
                discount,
                total_amount,
                payment,
                change_amount
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");


    if (!$sale_stmt) {

        throw new Exception(
            "DATABASE_ERROR"
        );
    }


    /*
     * Decimal money values are supplied as strings
     * such as "125.50" to preserve exact cent values.
     */
    $sale_stmt->bind_param(
        "sisssss",
        $invoice_number,
        $user_id,
        $subtotal_db,
        $discount_db,
        $total_db,
        $payment_db,
        $change_db
    );


    if (
        !$sale_stmt->execute()
    ) {

        $sale_stmt->close();

        throw new Exception(
            "DATABASE_ERROR"
        );
    }


    $sale_id =
        intval(
            $conn->insert_id
        );


    $sale_stmt->close();


    if ($sale_id <= 0) {

        throw new Exception(
            "DATABASE_ERROR"
        );
    }


    // =================================================
    // 18. PREPARE SALE ITEM INSERT
    // =================================================

    $sale_item_stmt =
        $conn->prepare("
            INSERT INTO sale_items
            (
                sale_id,
                product_id,
                quantity,
                unit_price,
                cost_price,
                subtotal
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");


    if (!$sale_item_stmt) {

        throw new Exception(
            "DATABASE_ERROR"
        );
    }


    // =================================================
    // 19. PREPARE STOCK UPDATE
    // =================================================

    $stock_stmt =
        $conn->prepare("
            UPDATE products
            SET stock_quantity = ?
            WHERE id = ?
            AND stock_quantity >= ?
        ");


    if (!$stock_stmt) {

        $sale_item_stmt->close();

        throw new Exception(
            "DATABASE_ERROR"
        );
    }


    // =================================================
    // 20. PREPARE INVENTORY HISTORY
    // =================================================

    $inventory_stmt =
        $conn->prepare("
            INSERT INTO inventory_transactions
            (
                product_id,
                transaction_type,
                quantity,
                previous_stock,
                new_stock,
                remarks,
                user_id
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");


    if (!$inventory_stmt) {

        $sale_item_stmt->close();
        $stock_stmt->close();

        throw new Exception(
            "DATABASE_ERROR"
        );
    }


    // =================================================
    // 21. SAVE ITEMS + UPDATE INVENTORY
    // =================================================

    foreach (
        $validated_cart as $item
    ) {

        $product_id =
            $item["product_id"];


        $quantity =
            $item["quantity"];


        $previous_stock =
            $item["current_stock"];


        $new_stock =
            $previous_stock -
            $quantity;


        // ---------------------------------------------
        // MONEY VALUES
        // ---------------------------------------------

        $unit_price_db =
            centsToDecimal(
                $item["unit_price_cents"]
            );


        $cost_price_db =
            centsToDecimal(
                $item["cost_price_cents"]
            );


        $item_subtotal_db =
            centsToDecimal(
                $item["item_subtotal_cents"]
            );


        // ---------------------------------------------
        // SAVE SALE ITEM
        // ---------------------------------------------

        $sale_item_stmt->bind_param(
            "iiisss",
            $sale_id,
            $product_id,
            $quantity,
            $unit_price_db,
            $cost_price_db,
            $item_subtotal_db
        );


        if (
            !$sale_item_stmt->execute()
        ) {

            throw new Exception(
                "DATABASE_ERROR"
            );
        }


        // ---------------------------------------------
        // DEDUCT PRODUCT STOCK
        // ---------------------------------------------

        $stock_stmt->bind_param(
            "iii",
            $new_stock,
            $product_id,
            $quantity
        );


        if (
            !$stock_stmt->execute()
        ) {

            throw new Exception(
                "DATABASE_ERROR"
            );
        }


        /*
         * Safety check.
         * Because the product was already locked,
         * exactly one product row should be updated.
         */
        if (
            $stock_stmt->affected_rows !== 1
        ) {

            throw new Exception(
                "Not enough stock for " .
                $item["product_name"] .
                "."
            );
        }


        // ---------------------------------------------
        // RECORD INVENTORY TRANSACTION
        // ---------------------------------------------

        $transaction_type =
            "stock_out";


        $remarks =
            "POS Sale - " .
            $invoice_number;


        $inventory_stmt->bind_param(
            "isiiisi",
            $product_id,
            $transaction_type,
            $quantity,
            $previous_stock,
            $new_stock,
            $remarks,
            $user_id
        );


        if (
            !$inventory_stmt->execute()
        ) {

            throw new Exception(
                "DATABASE_ERROR"
            );
        }
    }


    $sale_item_stmt->close();
    $stock_stmt->close();
    $inventory_stmt->close();


    // =================================================
    // 22. COMMIT EVERYTHING
    // =================================================

    if (!$conn->commit()) {

        throw new Exception(
            "DATABASE_ERROR"
        );
    }


    // =================================================
    // 23. SUCCESS RESPONSE
    // =================================================

    sendResponse(
        true,
        "Sale completed successfully.",
        [

            "sale_id" =>
                $sale_id,

            "invoice_number" =>
                $invoice_number,

            "subtotal" =>
                centsToNumber(
                    $subtotal_cents
                ),

            "discount" =>
                centsToNumber(
                    $discount_cents
                ),

            "total" =>
                centsToNumber(
                    $total_cents
                ),

            "payment" =>
                centsToNumber(
                    $payment_cents
                ),

            "change" =>
                centsToNumber(
                    $change_cents
                )

        ]
    );


} catch (Throwable $e) {


    // =================================================
    // 24. ROLLBACK ON ANY FAILURE
    // =================================================

    try {

        $conn->rollback();

    } catch (Throwable $rollback_error) {

        // Do not expose rollback/database errors.
    }


    $message =
        $e->getMessage();


    // =================================================
    // 25. SAFE USER-FACING ERRORS
    // =================================================

    $safe_exact_messages = [

        "Product not found.",

        "Invalid sale amount.",

        "Discount cannot exceed subtotal.",

        "Payment is not enough.",

        "Cashier discount cannot exceed 10%.",

        "Unable to generate a unique invoice number."

    ];


    if (
        in_array(
            $message,
            $safe_exact_messages,
            true
        )
    ) {

        sendResponse(
            false,
            $message
        );
    }


    /*
     * Product name is appended to this message,
     * so check only the beginning.
     */
    if (
        strpos(
            $message,
            "Not enough stock for "
        ) === 0
    ) {

        sendResponse(
            false,
            $message
        );
    }


    // =================================================
    // UNEXPECTED SERVER / DATABASE ERROR
    // =================================================

    sendResponse(
        false,
        "Unable to complete the sale. Please try again."
    );
}

?>