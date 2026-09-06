<?php

require_once "../config/database.php";
require_once "../auth.php";

requireAdmin();


// ==========================================
// CURRENT USER
// ==========================================

$current_user_id = intval($_SESSION['user_id']);


// ==========================================
// STOCK IN
// ==========================================

if (isset($_POST['stock_in'])) {

    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    if ($product_id <= 0 || $quantity <= 0) {

        $error = "Please enter a valid product and quantity.";

    } else {

        $conn->begin_transaction();

        try {

            $stmt = $conn->prepare("
                SELECT stock_quantity
                FROM products
                WHERE id = ?
                FOR UPDATE
            ");

            if (!$stmt) {
                throw new Exception("Unable to prepare product query.");
            }

            $stmt->bind_param("i", $product_id);

            if (!$stmt->execute()) {
                throw new Exception("Unable to check product stock.");
            }

            $product = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if (!$product) {
                throw new Exception("Product not found.");
            }

            $previous_stock = intval($product['stock_quantity']);

            $new_stock = $previous_stock + $quantity;


            // Update product stock
            $stmt = $conn->prepare("
                UPDATE products
                SET stock_quantity = ?
                WHERE id = ?
            ");

            if (!$stmt) {
                throw new Exception("Unable to prepare stock update.");
            }

            $stmt->bind_param(
                "ii",
                $new_stock,
                $product_id
            );

            if (!$stmt->execute()) {
                throw new Exception("Unable to update stock.");
            }

            $stmt->close();


            // Record inventory history
            $transaction_type = "stock_in";

            $stmt = $conn->prepare("
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
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                throw new Exception(
                    "Unable to prepare inventory history."
                );
            }

            $stmt->bind_param(
                "isiiisi",
                $product_id,
                $transaction_type,
                $quantity,
                $previous_stock,
                $new_stock,
                $remarks,
                $current_user_id
            );

            if (!$stmt->execute()) {
                throw new Exception(
                    "Unable to record inventory history."
                );
            }

            $stmt->close();

            $conn->commit();

            header("Location: index.php?success=stockin");
            exit;

        } catch (Throwable $e) {

            $conn->rollback();

            $error = $e->getMessage();
        }
    }
}


// ==========================================
// STOCK OUT
// ==========================================

if (isset($_POST['stock_out'])) {

    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    if ($product_id <= 0 || $quantity <= 0) {

        $error = "Please enter a valid product and quantity.";

    } else {

        $conn->begin_transaction();

        try {

            $stmt = $conn->prepare("
                SELECT stock_quantity
                FROM products
                WHERE id = ?
                FOR UPDATE
            ");

            if (!$stmt) {
                throw new Exception("Unable to prepare product query.");
            }

            $stmt->bind_param("i", $product_id);

            if (!$stmt->execute()) {
                throw new Exception("Unable to check product stock.");
            }

            $product = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if (!$product) {
                throw new Exception("Product not found.");
            }

            $previous_stock = intval($product['stock_quantity']);


            // Prevent negative stock
            if ($quantity > $previous_stock) {

                throw new Exception(
                    "Stock out quantity cannot be greater than current stock."
                );
            }


            $new_stock = $previous_stock - $quantity;


            // Update product stock
            $stmt = $conn->prepare("
                UPDATE products
                SET stock_quantity = ?
                WHERE id = ?
            ");

            if (!$stmt) {
                throw new Exception("Unable to prepare stock update.");
            }

            $stmt->bind_param(
                "ii",
                $new_stock,
                $product_id
            );

            if (!$stmt->execute()) {
                throw new Exception("Unable to update stock.");
            }

            $stmt->close();


            // Record inventory history
            $transaction_type = "stock_out";

            $stmt = $conn->prepare("
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
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                throw new Exception(
                    "Unable to prepare inventory history."
                );
            }

            $stmt->bind_param(
                "isiiisi",
                $product_id,
                $transaction_type,
                $quantity,
                $previous_stock,
                $new_stock,
                $remarks,
                $current_user_id
            );

            if (!$stmt->execute()) {
                throw new Exception(
                    "Unable to record inventory history."
                );
            }

            $stmt->close();

            $conn->commit();

            header("Location: index.php?success=stockout");
            exit;

        } catch (Throwable $e) {

            $conn->rollback();

            $error = $e->getMessage();
        }
    }
}


// ==========================================
// INVENTORY ADJUSTMENT
// ==========================================

if (isset($_POST['adjust_stock'])) {

    $product_id = intval($_POST['product_id'] ?? 0);
    $actual_stock = intval($_POST['actual_stock'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    if ($product_id <= 0 || $actual_stock < 0) {

        $error = "Please enter a valid stock quantity.";

    } else {

        $conn->begin_transaction();

        try {

            $stmt = $conn->prepare("
                SELECT stock_quantity
                FROM products
                WHERE id = ?
                FOR UPDATE
            ");

            if (!$stmt) {
                throw new Exception("Unable to prepare product query.");
            }

            $stmt->bind_param("i", $product_id);

            if (!$stmt->execute()) {
                throw new Exception("Unable to check product stock.");
            }

            $product = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if (!$product) {
                throw new Exception("Product not found.");
            }

            $previous_stock = intval($product['stock_quantity']);

            $new_stock = $actual_stock;

            $difference = $new_stock - $previous_stock;


            // No change
            if ($difference === 0) {

                throw new Exception(
                    "The actual stock is already the same as the system stock."
                );
            }


            // Update product stock
            $stmt = $conn->prepare("
                UPDATE products
                SET stock_quantity = ?
                WHERE id = ?
            ");

            if (!$stmt) {
                throw new Exception("Unable to prepare stock update.");
            }

            $stmt->bind_param(
                "ii",
                $new_stock,
                $product_id
            );

            if (!$stmt->execute()) {
                throw new Exception("Unable to update stock.");
            }

            $stmt->close();


            // Record adjustment
            $transaction_type = "adjustment";

            $adjustment_quantity = abs($difference);

            $stmt = $conn->prepare("
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
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                throw new Exception(
                    "Unable to prepare inventory history."
                );
            }

            $stmt->bind_param(
                "isiiisi",
                $product_id,
                $transaction_type,
                $adjustment_quantity,
                $previous_stock,
                $new_stock,
                $remarks,
                $current_user_id
            );

            if (!$stmt->execute()) {
                throw new Exception(
                    "Unable to record inventory history."
                );
            }

            $stmt->close();

            $conn->commit();

            header("Location: index.php?success=adjusted");
            exit;

        } catch (Throwable $e) {

            $conn->rollback();

            $error = $e->getMessage();
        }
    }
}


// ==========================================
// SEARCH
// ==========================================

$search = "";

if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}


// ==========================================
// PRODUCTS
// ==========================================

$stmt = $conn->prepare("
    SELECT
        products.*,
        categories.category_name
    FROM products
    LEFT JOIN categories
        ON products.category_id = categories.id
    WHERE
        products.product_name LIKE ?
        OR products.product_code LIKE ?
    ORDER BY products.product_name ASC
");

$searchTerm = "%" . $search . "%";

$stmt->bind_param(
    "ss",
    $searchTerm,
    $searchTerm
);

$stmt->execute();

$products = $stmt->get_result();

$stmt->close();


// ==========================================
// SUMMARY
// ==========================================

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM products
");

$total_products = $result->fetch_assoc()['total'];


$result = $conn->query("
    SELECT
        COALESCE(SUM(stock_quantity), 0) AS total
    FROM products
");

$total_stock = $result->fetch_assoc()['total'];


$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM products
    WHERE stock_quantity <= reorder_level
");

$low_stock = $result->fetch_assoc()['total'];


// ==========================================
// PRODUCT OPTIONS
// ==========================================

$product_options = $conn->query("
    SELECT
        id,
        product_name,
        product_code,
        stock_quantity
    FROM products
    ORDER BY product_name ASC
");


// ==========================================
// INVENTORY HISTORY
// ==========================================

$history = $conn->query("
    SELECT
        inventory_transactions.*,
        products.product_name,
        products.product_code,
        users.full_name AS performed_by
    FROM inventory_transactions
    INNER JOIN products
        ON inventory_transactions.product_id = products.id
    LEFT JOIN users
        ON inventory_transactions.user_id = users.id
    ORDER BY
        inventory_transactions.transaction_date DESC
    LIMIT 20
");

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Inventory Management</title>


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
    href="../assets/css/inventory.css"
>

</head>


<body>


<!-- ==========================================
     MOBILE MENU BUTTON
========================================== -->

<button
    type="button"
    class="mobile-menu-btn"
    id="mobileMenuBtn"
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

    <div class="sidebar-profile">

        <div class="profile-avatar">

            <?= htmlspecialchars(
                strtoupper(
                    substr(
                        $_SESSION['full_name'] ?? 'U',
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
                    $_SESSION['full_name'] ?? 'User',
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </strong>

            <span>

                <?= htmlspecialchars(
                    ucfirst(
                        $_SESSION['role'] ?? 'User'
                    ),
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </span>

        </div>


        <button
            type="button"
            class="sidebar-close-btn"
            id="sidebarCloseBtn"
            aria-label="Close navigation"
        >
            &times;
        </button>

    </div>


    <!-- ==========================================
         NAVIGATION
    =========================================== -->

    <nav class="sidebar-nav">

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

        <a
            href="index.php"
            class="active"
        >
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


    <!-- ==========================================
         HEADER
    =========================================== -->

    <div class="header">

        <h1>
            Inventory Management
        </h1>

        <p>
            Monitor, restock, remove, and adjust inventory
        </p>

    </div>


    <!-- ==========================================
         ERROR
    =========================================== -->

    <?php if (isset($error)): ?>

        <div class="error">

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </div>

    <?php endif; ?>


    <!-- ==========================================
         SUCCESS
    =========================================== -->

    <?php if (isset($_GET['success'])): ?>

        <div class="success">

            <?php

            if ($_GET['success'] === 'stockin') {

                echo "Stock successfully added.";

            } elseif ($_GET['success'] === 'stockout') {

                echo "Stock successfully removed.";

            } elseif ($_GET['success'] === 'adjusted') {

                echo "Inventory successfully adjusted.";

            }

            ?>

        </div>

    <?php endif; ?>


    <!-- ==========================================
         SUMMARY
    =========================================== -->

    <div class="cards">


        <div class="card">

            <div class="card-title">
                Total Products
            </div>

            <div class="card-value">

                <?= number_format(
                    $total_products
                ) ?>

            </div>

        </div>


        <div class="card">

            <div class="card-title">
                Total Items in Stock
            </div>

            <div class="card-value">

                <?= number_format(
                    $total_stock
                ) ?>

            </div>

        </div>


        <div class="card">

            <div class="card-title">
                Low Stock Products
            </div>

            <div class="card-value low">

                <?= number_format(
                    $low_stock
                ) ?>

            </div>

        </div>


    </div>


    <!-- ==========================================
         INVENTORY ACTIONS
    =========================================== -->

    <div class="action-box">

        <h2>
            Inventory Actions
        </h2>


        <div class="actions-grid">


            <!-- ==================================
                 STOCK IN
            =================================== -->

            <div class="action-card">

                <h3>
                    Stock In
                </h3>


                <form method="POST">

                    <select
                        name="product_id"
                        required
                    >

                        <option value="">
                            Select Product
                        </option>


                        <?php

                        if ($product_options) {

                            $product_options->data_seek(0);

                            while (
                                $product =
                                $product_options->fetch_assoc()
                            ) {

                        ?>

                        <option
                            value="<?= (int) $product['id'] ?>"
                        >

                            <?= htmlspecialchars(
                                $product['product_name'],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                            (<?= htmlspecialchars(
                                $product['product_code'],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>)

                            — <?= number_format(
                                $product['stock_quantity']
                            ) ?>

                        </option>

                        <?php

                            }

                        }

                        ?>

                    </select>


                    <input
                        type="number"
                        name="quantity"
                        min="1"
                        placeholder="Quantity to add"
                        required
                    >


                    <input
                        type="text"
                        name="remarks"
                        maxlength="255"
                        placeholder="Remarks"
                    >


                    <button
                        type="submit"
                        name="stock_in"
                        class="btn btn-in"
                    >
                        + Stock In
                    </button>

                </form>

            </div>


            <!-- ==================================
                 STOCK OUT
            =================================== -->

            <div class="action-card">

                <h3>
                    Stock Out
                </h3>


                <form method="POST">

                    <select
                        name="product_id"
                        required
                    >

                        <option value="">
                            Select Product
                        </option>


                        <?php

                        if ($product_options) {

                            $product_options->data_seek(0);

                            while (
                                $product =
                                $product_options->fetch_assoc()
                            ) {

                        ?>

                        <option
                            value="<?= (int) $product['id'] ?>"
                        >

                            <?= htmlspecialchars(
                                $product['product_name'],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                            (<?= htmlspecialchars(
                                $product['product_code'],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>)

                            — <?= number_format(
                                $product['stock_quantity']
                            ) ?>

                        </option>

                        <?php

                            }

                        }

                        ?>

                    </select>


                    <input
                        type="number"
                        name="quantity"
                        min="1"
                        placeholder="Quantity to remove"
                        required
                    >


                    <input
                        type="text"
                        name="remarks"
                        maxlength="255"
                        placeholder="e.g. Damaged / Expired"
                    >


                    <button
                        type="submit"
                        name="stock_out"
                        class="btn btn-out"
                    >
                        − Stock Out
                    </button>

                </form>

            </div>


            <!-- ==================================
                 ADJUSTMENT
            =================================== -->

            <div class="action-card">

                <h3>
                    Adjustment
                </h3>


                <form method="POST">

                    <select
                        name="product_id"
                        required
                    >

                        <option value="">
                            Select Product
                        </option>


                        <?php

                        if ($product_options) {

                            $product_options->data_seek(0);

                            while (
                                $product =
                                $product_options->fetch_assoc()
                            ) {

                        ?>

                        <option
                            value="<?= (int) $product['id'] ?>"
                        >

                            <?= htmlspecialchars(
                                $product['product_name'],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                            (<?= htmlspecialchars(
                                $product['product_code'],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>)

                            — <?= number_format(
                                $product['stock_quantity']
                            ) ?>

                        </option>

                        <?php

                            }

                        }

                        ?>

                    </select>


                    <input
                        type="number"
                        name="actual_stock"
                        min="0"
                        placeholder="Actual physical stock"
                        required
                    >


                    <input
                        type="text"
                        name="remarks"
                        maxlength="255"
                        placeholder="e.g. Physical count"
                    >


                    <button
                        type="submit"
                        name="adjust_stock"
                        class="btn btn-adjust"
                    >
                        Adjust Stock
                    </button>

                </form>

            </div>


        </div>

    </div>


    <!-- ==========================================
         CURRENT INVENTORY
    =========================================== -->

    <div class="table-box">


        <div class="table-header">

            <h2>
                Current Inventory
            </h2>


            <form
                method="GET"
                class="search"
            >

                <input
                    type="text"
                    name="search"
                    placeholder="Search product..."
                    value="<?= htmlspecialchars(
                        $search,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                >

                <button type="submit">
                    Search
                </button>

            </form>

        </div>


        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>
                            Code
                        </th>

                        <th>
                            Product
                        </th>

                        <th>
                            Category
                        </th>

                        <th>
                            Cost
                        </th>

                        <th>
                            Selling Price
                        </th>

                        <th>
                            Stock
                        </th>

                        <th>
                            Reorder
                        </th>

                        <th>
                            Status
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (
                    $products &&
                    $products->num_rows > 0
                ): ?>


                    <?php while (
                        $product =
                        $products->fetch_assoc()
                    ): ?>


                        <tr>


                            <td>

                                <?= htmlspecialchars(
                                    $product['product_code'],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $product['product_name'],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $product['category_name']
                                    ?? 'Uncategorized',
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </td>


                            <td>

                                ₱<?= number_format(
                                    $product['cost_price'],
                                    2
                                ) ?>

                            </td>


                            <td>

                                ₱<?= number_format(
                                    $product['selling_price'],
                                    2
                                ) ?>

                            </td>


                            <td>

                                <?= number_format(
                                    $product['stock_quantity']
                                ) ?>

                            </td>


                            <td>

                                <?= number_format(
                                    $product['reorder_level']
                                ) ?>

                            </td>


                            <td>

                                <?php if (
                                    $product['stock_quantity']
                                    <=
                                    $product['reorder_level']
                                ): ?>

                                    <span class="stock-low">
                                        LOW STOCK
                                    </span>

                                <?php else: ?>

                                    <span class="stock-good">
                                        IN STOCK
                                    </span>

                                <?php endif; ?>

                            </td>


                        </tr>


                    <?php endwhile; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="8"
                            class="empty"
                        >

                            No products found.

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>


    <!-- ==========================================
         INVENTORY HISTORY
    =========================================== -->

    <div class="table-box">


        <h2 class="history-title">
            Inventory History
        </h2>


        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>
                            Product
                        </th>

                        <th>
                            Type
                        </th>

                        <th>
                            Quantity
                        </th>

                        <th>
                            Previous
                        </th>

                        <th>
                            New Stock
                        </th>

                        <th>
                            Remarks
                        </th>

                        <th>
                            Performed By
                        </th>

                        <th>
                            Date
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (
                    $history &&
                    $history->num_rows > 0
                ): ?>


                    <?php while (
                        $transaction =
                        $history->fetch_assoc()
                    ): ?>


                        <tr>


                            <!-- PRODUCT -->

                            <td>

                                <?= htmlspecialchars(
                                    $transaction['product_name'],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                                <br>

                                <small>

                                    <?= htmlspecialchars(
                                        $transaction['product_code'],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </small>

                            </td>


                            <!-- TYPE -->

                            <td>

                                <?php

                                $type =
                                    $transaction['transaction_type'];

                                ?>


                                <?php if (
                                    $type === "stock_in"
                                ): ?>

                                    <span class="stock-in">
                                        STOCK IN
                                    </span>


                                <?php elseif (
                                    $type === "stock_out"
                                ): ?>

                                    <span class="stock-out">
                                        STOCK OUT
                                    </span>


                                <?php else: ?>

                                    <span class="adjustment">
                                        ADJUSTMENT
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- QUANTITY -->

                            <td>

                                <?= number_format(
                                    $transaction['quantity']
                                ) ?>

                            </td>


                            <!-- PREVIOUS -->

                            <td>

                                <?= number_format(
                                    $transaction['previous_stock']
                                ) ?>

                            </td>


                            <!-- NEW STOCK -->

                            <td>

                                <?= number_format(
                                    $transaction['new_stock']
                                ) ?>

                            </td>


                            <!-- REMARKS -->

                            <td>

                                <?= htmlspecialchars(
                                    $transaction['remarks']
                                    ?? '-',
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </td>


                            <!-- PERFORMED BY -->

                            <td>

                                <?= htmlspecialchars(
                                    $transaction['performed_by']
                                    ?? 'Unknown',
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </td>


                            <!-- DATE -->

                            <td>

                                <?= date(
                                    "M d, Y h:i A",
                                    strtotime(
                                        $transaction['transaction_date']
                                    )
                                ) ?>

                            </td>


                        </tr>


                    <?php endwhile; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="8"
                            class="empty"
                        >

                            No inventory transactions yet.

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>


</div>


<!-- ==========================================
     NAVIGATION SCRIPT
========================================== -->

<script src="../assets/js/navigation.js"></script>


</body>

</html>