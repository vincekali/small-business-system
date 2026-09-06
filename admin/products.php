<?php

require_once "../config/database.php";
require_once "../auth.php";

requireAdmin();


// ==========================================
// CURRENT USER
// ==========================================

$current_user_id = intval($_SESSION['user_id']);


// ==========================================
// HELPER FUNCTIONS
// ==========================================

function isValidNonNegativeNumber($value)
{
    return is_numeric($value) && (float)$value >= 0;
}


function isValidNonNegativeInteger($value)
{
    return ctype_digit((string)$value);
}


function categoryExists($conn, $category_id)
{
    if ($category_id === null) {
        return true;
    }

    $stmt = $conn->prepare("
        SELECT id
        FROM categories
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        "i",
        $category_id
    );

    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }

    $result = $stmt->get_result();

    $exists = $result->num_rows > 0;

    $stmt->close();

    return $exists;
}


// ==========================================
// ADD PRODUCT
// ==========================================

if (isset($_POST['add_product'])) {

    $product_code =
        trim($_POST['product_code'] ?? '');

    $product_name =
        trim($_POST['product_name'] ?? '');

    $category_input =
        trim($_POST['category_id'] ?? '');

    $selling_price_input =
        trim($_POST['selling_price'] ?? '');

    $cost_price_input =
        trim($_POST['cost_price'] ?? '');

    $stock_quantity_input =
        trim($_POST['stock_quantity'] ?? '');

    $reorder_level_input =
        trim($_POST['reorder_level'] ?? '');


    // ------------------------------------------
    // CATEGORY
    // ------------------------------------------

    $category_id =
        $category_input === ''
            ? null
            : (
                ctype_digit($category_input)
                    ? intval($category_input)
                    : -1
            );


    // ------------------------------------------
    // VALIDATION
    // ------------------------------------------

    if (
        $product_code === '' ||
        $product_name === ''
    ) {

        $error =
            "Product code and product name are required.";

    } elseif (
        strlen($product_code) > 50
    ) {

        $error =
            "Product code must not exceed 50 characters.";

    } elseif (
        strlen($product_name) > 150
    ) {

        $error =
            "Product name must not exceed 150 characters.";

    } elseif (
        !isValidNonNegativeNumber($selling_price_input) ||
        !isValidNonNegativeNumber($cost_price_input)
    ) {

        $error =
            "Selling price and cost price must be valid non-negative numbers.";

    } elseif (
        !isValidNonNegativeInteger($stock_quantity_input) ||
        !isValidNonNegativeInteger($reorder_level_input)
    ) {

        $error =
            "Stock quantity and reorder level must be valid non-negative whole numbers.";

    } elseif (
        $category_id !== null &&
        $category_id <= 0
    ) {

        $error =
            "Invalid category.";

    } else {

        $selling_price =
            (float)$selling_price_input;

        $cost_price =
            (float)$cost_price_input;

        $stock_quantity =
            (int)$stock_quantity_input;

        $reorder_level =
            (int)$reorder_level_input;


        // ------------------------------------------
        // CHECK CATEGORY
        // ------------------------------------------

        if (!categoryExists($conn, $category_id)) {

            $error =
                "Selected category does not exist.";

        } else {

            // ------------------------------------------
            // CHECK DUPLICATE PRODUCT CODE
            // ------------------------------------------

            $check = $conn->prepare("
                SELECT id
                FROM products
                WHERE product_code = ?
                LIMIT 1
            ");

            if (!$check) {

                $error =
                    "Unable to check product code.";

            } else {

                $check->bind_param(
                    "s",
                    $product_code
                );

                $check->execute();

                $existing =
                    $check
                        ->get_result()
                        ->fetch_assoc();

                $check->close();


                if ($existing) {

                    $error =
                        "Product code already exists.";

                } else {

                    // ------------------------------------------
                    // START TRANSACTION
                    // ------------------------------------------

                    $conn->begin_transaction();

                    try {

                        // ------------------------------------------
                        // INSERT PRODUCT
                        // ------------------------------------------

                        $stmt = $conn->prepare("
                            INSERT INTO products
                            (
                                product_code,
                                product_name,
                                category_id,
                                selling_price,
                                cost_price,
                                stock_quantity,
                                reorder_level
                            )
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");

                        if (!$stmt) {

                            throw new Exception(
                                "Unable to prepare product insert."
                            );
                        }


                        $stmt->bind_param(
                            "ssiddii",
                            $product_code,
                            $product_name,
                            $category_id,
                            $selling_price,
                            $cost_price,
                            $stock_quantity,
                            $reorder_level
                        );


                        if (!$stmt->execute()) {

                            if ($conn->errno === 1062) {

                                throw new Exception(
                                    "Product code already exists."
                                );
                            }

                            throw new Exception(
                                "Unable to add product."
                            );
                        }


                        $product_id =
                            $stmt->insert_id;

                        $stmt->close();


                        // ------------------------------------------
                        // RECORD INITIAL STOCK
                        // ------------------------------------------

                        if ($stock_quantity > 0) {

                            $transaction_type =
                                "stock_in";

                            $previous_stock =
                                0;

                            $new_stock =
                                $stock_quantity;

                            $remarks =
                                "Initial stock";


                            $history_stmt =
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
                                    VALUES (?, ?, ?, ?, ?, ?, ?)
                                ");

                            if (!$history_stmt) {

                                throw new Exception(
                                    "Unable to prepare inventory history."
                                );
                            }


                            $history_stmt->bind_param(
                                "isiiisi",
                                $product_id,
                                $transaction_type,
                                $stock_quantity,
                                $previous_stock,
                                $new_stock,
                                $remarks,
                                $current_user_id
                            );


                            if (!$history_stmt->execute()) {

                                throw new Exception(
                                    "Unable to record initial stock."
                                );
                            }


                            $history_stmt->close();
                        }


                        // ------------------------------------------
                        // COMMIT
                        // ------------------------------------------

                        $conn->commit();


                        header(
                            "Location: products.php?success=added"
                        );

                        exit;


                    } catch (Throwable $e) {

                        $conn->rollback();

                        $error =
                            $e->getMessage();
                    }
                }
            }
        }
    }
}


// ==========================================
// UPDATE PRODUCT
// ==========================================

if (isset($_POST['update_product'])) {

    $id =
        isset($_POST['id']) &&
        ctype_digit((string)$_POST['id'])
            ? intval($_POST['id'])
            : 0;


    $product_code =
        trim($_POST['product_code'] ?? '');

    $product_name =
        trim($_POST['product_name'] ?? '');

    $category_input =
        trim($_POST['category_id'] ?? '');

    $selling_price_input =
        trim($_POST['selling_price'] ?? '');

    $cost_price_input =
        trim($_POST['cost_price'] ?? '');

    $reorder_level_input =
        trim($_POST['reorder_level'] ?? '');


    // ------------------------------------------
    // CATEGORY
    // ------------------------------------------

    $category_id =
        $category_input === ''
            ? null
            : (
                ctype_digit($category_input)
                    ? intval($category_input)
                    : -1
            );


    // ------------------------------------------
    // VALIDATION
    // ------------------------------------------

    if ($id <= 0) {

        $error =
            "Invalid product.";

    } elseif (
        $product_code === '' ||
        $product_name === ''
    ) {

        $error =
            "Product code and product name are required.";

    } elseif (
        strlen($product_code) > 50
    ) {

        $error =
            "Product code must not exceed 50 characters.";

    } elseif (
        strlen($product_name) > 150
    ) {

        $error =
            "Product name must not exceed 150 characters.";

    } elseif (
        !isValidNonNegativeNumber($selling_price_input) ||
        !isValidNonNegativeNumber($cost_price_input)
    ) {

        $error =
            "Selling price and cost price must be valid non-negative numbers.";

    } elseif (
        !isValidNonNegativeInteger($reorder_level_input)
    ) {

        $error =
            "Reorder level must be a valid non-negative whole number.";

    } elseif (
        $category_id !== null &&
        $category_id <= 0
    ) {

        $error =
            "Invalid category.";

    } else {

        $selling_price =
            (float)$selling_price_input;

        $cost_price =
            (float)$cost_price_input;

        $reorder_level =
            (int)$reorder_level_input;


        // ------------------------------------------
        // CHECK PRODUCT EXISTS
        // ------------------------------------------

        $check = $conn->prepare("
            SELECT id
            FROM products
            WHERE id = ?
            LIMIT 1
        ");

        if (!$check) {

            $error =
                "Unable to check product.";

        } else {

            $check->bind_param(
                "i",
                $id
            );

            $check->execute();

            $existing_product =
                $check
                    ->get_result()
                    ->fetch_assoc();

            $check->close();


            if (!$existing_product) {

                $error =
                    "Product not found.";

            } elseif (
                !categoryExists(
                    $conn,
                    $category_id
                )
            ) {

                $error =
                    "Selected category does not exist.";

            } else {

                // ------------------------------------------
                // CHECK DUPLICATE PRODUCT CODE
                // ------------------------------------------

                $check = $conn->prepare("
                    SELECT id
                    FROM products
                    WHERE product_code = ?
                    AND id != ?
                    LIMIT 1
                ");

                if (!$check) {

                    $error =
                        "Unable to check product code.";

                } else {

                    $check->bind_param(
                        "si",
                        $product_code,
                        $id
                    );

                    $check->execute();

                    $duplicate =
                        $check
                            ->get_result()
                            ->fetch_assoc();

                    $check->close();


                    if ($duplicate) {

                        $error =
                            "Product code already exists.";

                    } else {

                        // ------------------------------------------
                        // UPDATE PRODUCT
                        // ------------------------------------------

                        $stmt = $conn->prepare("
                            UPDATE products
                            SET
                                product_code = ?,
                                product_name = ?,
                                category_id = ?,
                                selling_price = ?,
                                cost_price = ?,
                                reorder_level = ?
                            WHERE id = ?
                        ");

                        if (!$stmt) {

                            $error =
                                "Unable to prepare product update.";

                        } else {

                            $stmt->bind_param(
                                "ssiddii",
                                $product_code,
                                $product_name,
                                $category_id,
                                $selling_price,
                                $cost_price,
                                $reorder_level,
                                $id
                            );


                            if ($stmt->execute()) {

                                $stmt->close();


                                header(
                                    "Location: products.php?success=updated"
                                );

                                exit;

                            } else {

                                if ($conn->errno === 1062) {

                                    $error =
                                        "Product code already exists.";

                                } else {

                                    $error =
                                        "Unable to update product.";
                                }

                                $stmt->close();
                            }
                        }
                    }
                }
            }
        }
    }
}


// ==========================================
// DELETE PRODUCT
// ==========================================

if (isset($_POST['delete_product'])) {

    $id =
        isset($_POST['id']) &&
        ctype_digit((string)$_POST['id'])
            ? intval($_POST['id'])
            : 0;


    if ($id <= 0) {

        $error =
            "Invalid product.";

    } else {

        // ------------------------------------------
        // CHECK PRODUCT EXISTS
        // ------------------------------------------

        $check = $conn->prepare("
            SELECT id
            FROM products
            WHERE id = ?
            LIMIT 1
        ");

        if (!$check) {

            $error =
                "Unable to check product.";

        } else {

            $check->bind_param(
                "i",
                $id
            );

            $check->execute();

            $existing_product =
                $check
                    ->get_result()
                    ->fetch_assoc();

            $check->close();


            if (!$existing_product) {

                $error =
                    "Product not found.";

            } else {

                // ------------------------------------------
                // CHECK SALES HISTORY
                // ------------------------------------------

                $check = $conn->prepare("
                    SELECT id
                    FROM sale_items
                    WHERE product_id = ?
                    LIMIT 1
                ");

                if (!$check) {

                    $error =
                        "Unable to check product sales.";

                } else {

                    $check->bind_param(
                        "i",
                        $id
                    );

                    $check->execute();

                    $has_sales =
                        $check
                            ->get_result()
                            ->fetch_assoc();

                    $check->close();


                    if ($has_sales) {

                        $error =
                            "This product cannot be deleted because it already has sales records.";

                    } else {

                        // ------------------------------------------
                        // CHECK INVENTORY HISTORY
                        // ------------------------------------------

                        $check = $conn->prepare("
                            SELECT id
                            FROM inventory_transactions
                            WHERE product_id = ?
                            LIMIT 1
                        ");

                        if (!$check) {

                            $error =
                                "Unable to check inventory history.";

                        } else {

                            $check->bind_param(
                                "i",
                                $id
                            );

                            $check->execute();

                            $has_inventory_history =
                                $check
                                    ->get_result()
                                    ->fetch_assoc();

                            $check->close();


                            if ($has_inventory_history) {

                                $error =
                                    "This product cannot be deleted because it already has inventory history.";

                            } else {

                                // ------------------------------------------
                                // DELETE PRODUCT
                                // ------------------------------------------

                                $stmt = $conn->prepare("
                                    DELETE FROM products
                                    WHERE id = ?
                                ");

                                if (!$stmt) {

                                    $error =
                                        "Unable to prepare product deletion.";

                                } else {

                                    $stmt->bind_param(
                                        "i",
                                        $id
                                    );


                                    if ($stmt->execute()) {

                                        if (
                                            $stmt->affected_rows === 0
                                        ) {

                                            $error =
                                                "Product not found.";

                                            $stmt->close();

                                        } else {

                                            $stmt->close();


                                            header(
                                                "Location: products.php?success=deleted"
                                            );

                                            exit;
                                        }

                                    } else {

                                        $stmt->close();

                                        $error =
                                            "Unable to delete product.";
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}


// ==========================================
// GET CATEGORIES
// ==========================================

$categories = $conn->query("
    SELECT
        id,
        category_name
    FROM categories
    ORDER BY category_name ASC
");

if (!$categories) {
    die("Unable to load categories.");
}


// ==========================================
// SEARCH
// ==========================================

$search =
    trim(
        $_GET['search'] ?? ''
    );


// ==========================================
// PAGINATION
// ==========================================

$per_page = 20;

$page =
    isset($_GET['page']) &&
    ctype_digit((string)$_GET['page'])
        ? intval($_GET['page'])
        : 1;

if ($page < 1) {
    $page = 1;
}


// ==========================================
// COUNT PRODUCTS
// ==========================================

$count_stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total
    FROM products
    WHERE
        product_name LIKE ?
        OR product_code LIKE ?
");

if (!$count_stmt) {
    die("Unable to count products.");
}


$searchTerm =
    "%" . $search . "%";


$count_stmt->bind_param(
    "ss",
    $searchTerm,
    $searchTerm
);


if (!$count_stmt->execute()) {

    $count_stmt->close();

    die("Unable to count products.");
}


$count_result =
    $count_stmt->get_result();


$count_row =
    $count_result->fetch_assoc();


$total_products =
    intval(
        $count_row['total'] ?? 0
    );


$count_stmt->close();


$total_pages =
    max(
        1,
        (int)ceil(
            $total_products / $per_page
        )
    );


if ($page > $total_pages) {
    $page = $total_pages;
}


$offset =
    ($page - 1) * $per_page;


// ==========================================
// GET PRODUCTS
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

    ORDER BY products.id DESC

    LIMIT ? OFFSET ?
");

if (!$stmt) {
    die("Unable to load products.");
}


$stmt->bind_param(
    "ssii",
    $searchTerm,
    $searchTerm,
    $per_page,
    $offset
);


if (!$stmt->execute()) {

    $stmt->close();

    die("Unable to load products.");
}


$products =
    $stmt->get_result();


// ==========================================
// SUCCESS MESSAGE
// ==========================================

$success = "";

if (isset($_GET['success'])) {

    if (
        $_GET['success'] === 'added'
    ) {

        $success =
            "Product added successfully.";

    } elseif (
        $_GET['success'] === 'updated'
    ) {

        $success =
            "Product updated successfully.";

    } elseif (
        $_GET['success'] === 'deleted'
    ) {

        $success =
            "Product deleted successfully.";
    }
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
        Products
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


    <!-- PRODUCT CSS -->

    <link
        rel="stylesheet"
        href="../assets/css/products.css"
    >

</head>


<body>


<!-- ========================================================
     SIDEBAR
======================================================== -->

<div class="sidebar" id="sidebar">

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


        <!-- MOBILE CLOSE BUTTON -->

        <button
            type="button"
            class="sidebar-close-btn"
            id="sidebarCloseBtn"
            aria-label="Close navigation"
        >
            ×
        </button>

    </div>


    <!-- ====================================================
         NAVIGATION
    ==================================================== -->

    <nav class="sidebar-nav">

        <a href="dashboard.php">
            Dashboard
        </a>


        <a
            href="products.php"
            class="active"
        >
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
    id="mobileOverlay"
></div>


<!-- ========================================================
     MAIN CONTENT
======================================================== -->

<div class="main">


    <!-- MOBILE MENU BUTTON -->

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


    <!-- ====================================================
         PAGE HEADER
    ==================================================== -->

    <div class="header">

        <h1>
            Products
        </h1>

        <p>
            Manage products, prices and inventory
        </p>

    </div>


    <!-- ====================================================
         MESSAGES
    ==================================================== -->

    <?php if ($success): ?>

        <div class="success">

            <?= htmlspecialchars(
                $success,
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </div>

    <?php endif; ?>


    <?php if (!empty($error)): ?>

        <div class="error">

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </div>

    <?php endif; ?>


    <!-- ====================================================
         PRODUCT HEADER
    ==================================================== -->

    <div class="product-header">

        <div>

            <h2>
                Product List
            </h2>

            <p>

                <?= number_format(
                    $total_products
                ) ?>

                product(s) found

            </p>

        </div>


        <button
            type="button"
            class="btn btn-add"
            onclick="openAddModal()"
        >

            + Add Product

        </button>

    </div>


    <!-- ====================================================
         PRODUCT TABLE
    ==================================================== -->

    <div class="table-box">


        <!-- SEARCH -->

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


            <?php if ($search !== ''): ?>

                <a
                    href="products.php"
                    class="clear-search"
                >

                    Clear

                </a>

            <?php endif; ?>

        </form>


        <!-- ==================================================
             PRODUCT TABLE
        ================================================== -->

        <?php if (
            $products &&
            $products->num_rows > 0
        ): ?>

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
                            Selling
                        </th>

                        <th>
                            Stock
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>

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
                                ?? 'None',
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </td>


                        <td>

                            ₱<?= number_format(
                                (float)$product['cost_price'],
                                2
                            ) ?>

                        </td>


                        <td>

                            ₱<?= number_format(
                                (float)$product['selling_price'],
                                2
                            ) ?>

                        </td>


                        <td>

                            <?= number_format(
                                (int)$product['stock_quantity']
                            ) ?>

                        </td>


                        <td>

                            <?php if (
                                (int)$product['stock_quantity']
                                <=
                                (int)$product['reorder_level']
                            ): ?>

                                <span class="low-stock">
                                    Low Stock
                                </span>

                            <?php else: ?>

                                <span class="in-stock">
                                    In Stock
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <div class="actions">


                                <!-- EDIT -->

                                <button
                                    type="button"
                                    class="edit"
                                    onclick='openEditModal(
                                        <?= htmlspecialchars(
                                            json_encode(
                                                $product,
                                                JSON_HEX_TAG |
                                                JSON_HEX_AMP |
                                                JSON_HEX_APOS |
                                                JSON_HEX_QUOT
                                            ),
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    )'
                                >

                                    Edit

                                </button>


                                <!-- DELETE -->

                                <form
                                    method="POST"
                                    style="display:inline;"
                                    onsubmit="
                                        return confirm(
                                            'Delete this product?'
                                        );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$product['id'] ?>"
                                    >


                                    <button
                                        type="submit"
                                        name="delete_product"
                                        class="delete"
                                    >

                                        Delete

                                    </button>

                                </form>


                            </div>

                        </td>

                    </tr>

                <?php endwhile; ?>

                </tbody>

            </table>


        <?php else: ?>

            <div class="empty">

                No products found.

            </div>

        <?php endif; ?>


        <!-- ==================================================
             PAGINATION
        ================================================== -->

        <?php if (
            $total_pages > 1
        ): ?>

            <div class="pagination">


                <?php if (
                    $page > 1
                ): ?>

                    <a
                        href="?search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>"
                    >

                        &laquo;

                    </a>

                <?php else: ?>

                    <span class="disabled">

                        &laquo;

                    </span>

                <?php endif; ?>


                <?php

                $start_page =
                    max(
                        1,
                        $page - 2
                    );

                $end_page =
                    min(
                        $total_pages,
                        $page + 2
                    );

                ?>


                <?php if (
                    $start_page > 1
                ): ?>

                    <a
                        href="?search=<?= urlencode($search) ?>&page=1"
                    >

                        1

                    </a>


                    <?php if (
                        $start_page > 2
                    ): ?>

                        <span>
                            ...
                        </span>

                    <?php endif; ?>

                <?php endif; ?>


                <?php for (
                    $i = $start_page;
                    $i <= $end_page;
                    $i++
                ): ?>


                    <?php if (
                        $i == $page
                    ): ?>

                        <span class="active">

                            <?= $i ?>

                        </span>

                    <?php else: ?>

                        <a
                            href="?search=<?= urlencode($search) ?>&page=<?= $i ?>"
                        >

                            <?= $i ?>

                        </a>

                    <?php endif; ?>


                <?php endfor; ?>


                <?php if (
                    $end_page < $total_pages
                ): ?>


                    <?php if (
                        $end_page <
                        $total_pages - 1
                    ): ?>

                        <span>
                            ...
                        </span>

                    <?php endif; ?>


                    <a
                        href="?search=<?= urlencode($search) ?>&page=<?= $total_pages ?>"
                    >

                        <?= $total_pages ?>

                    </a>

                <?php endif; ?>


                <?php if (
                    $page < $total_pages
                ): ?>

                    <a
                        href="?search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>"
                    >

                        &raquo;

                    </a>

                <?php else: ?>

                    <span class="disabled">

                        &raquo;

                    </span>

                <?php endif; ?>


            </div>


            <div class="page-info">

                Page
                <?= $page ?>

                of

                <?= $total_pages ?>

                —

                <?= $total_products ?>

                total products

            </div>

        <?php endif; ?>


    </div>

</div>


<!-- ========================================================
     ADD PRODUCT MODAL
======================================================== -->

<div
    class="modal"
    id="addModal"
>

    <div class="modal-content">

        <button
            type="button"
            class="modal-close"
            onclick="closeAddModal()"
            aria-label="Close modal"
        >
            &times;
        </button>


        <h2>
            Add Product
        </h2>


        <form method="POST">

            <div class="modal-grid">

                <!-- PRODUCT CODE -->

                <div class="form-group">

                    <label>
                        Product Code
                    </label>

                    <input
                        type="text"
                        name="product_code"
                        maxlength="50"
                        required
                    >

                </div>


                <!-- PRODUCT NAME -->

                <div class="form-group">

                    <label>
                        Product Name
                    </label>

                    <input
                        type="text"
                        name="product_name"
                        maxlength="150"
                        required
                    >

                </div>


                <!-- CATEGORY -->

                <div class="form-group">

                    <label>
                        Category
                    </label>

                    <select name="category_id">

                        <option value="">
                            No Category
                        </option>

                        <?php

                        $categories->data_seek(0);

                        while (
                            $category =
                            $categories->fetch_assoc()
                        ):

                        ?>

                            <option
                                value="<?= (int)$category['id'] ?>"
                            >

                                <?= htmlspecialchars(
                                    $category['category_name'],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>


                <!-- COST -->

                <div class="form-group">

                    <label>
                        Cost Price
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="cost_price"
                        required
                    >

                </div>


                <!-- SELLING -->

                <div class="form-group">

                    <label>
                        Selling Price
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="selling_price"
                        required
                    >

                </div>


                <!-- INITIAL STOCK -->

                <div class="form-group">

                    <label>
                        Initial Stock
                    </label>

                    <input
                        type="number"
                        min="0"
                        name="stock_quantity"
                        required
                    >

                </div>


                <!-- REORDER -->

                <div class="form-group">

                    <label>
                        Reorder Level
                    </label>

                    <input
                        type="number"
                        min="0"
                        name="reorder_level"
                        value="5"
                        required
                    >

                </div>

            </div>


            <div class="modal-buttons">

                <button
                    type="button"
                    class="btn btn-cancel"
                    onclick="closeAddModal()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    name="add_product"
                    class="btn btn-save"
                >
                    Add Product
                </button>

            </div>

        </form>

    </div>

</div>


<!-- ========================================================
     EDIT PRODUCT MODAL
======================================================== -->

<div
    class="modal"
    id="editModal"
>

    <div class="modal-content">

        <button
            type="button"
            class="modal-close"
            onclick="closeEditModal()"
            aria-label="Close modal"
        >
            &times;
        </button>


        <h2>
            Edit Product
        </h2>


        <form method="POST">

            <input
                type="hidden"
                name="id"
                id="edit_id"
            >


            <div class="modal-grid">

                <!-- PRODUCT CODE -->

                <div class="form-group">

                    <label>
                        Product Code
                    </label>

                    <input
                        type="text"
                        name="product_code"
                        id="edit_code"
                        maxlength="50"
                        required
                    >

                </div>


                <!-- PRODUCT NAME -->

                <div class="form-group">

                    <label>
                        Product Name
                    </label>

                    <input
                        type="text"
                        name="product_name"
                        id="edit_name"
                        maxlength="150"
                        required
                    >

                </div>


                <!-- CATEGORY -->

                <div class="form-group">

                    <label>
                        Category
                    </label>

                    <select
                        name="category_id"
                        id="edit_category"
                    >

                        <option value="">
                            No Category
                        </option>

                        <?php

                        $categories->data_seek(0);

                        while (
                            $category =
                            $categories->fetch_assoc()
                        ):

                        ?>

                            <option
                                value="<?= (int)$category['id'] ?>"
                            >

                                <?= htmlspecialchars(
                                    $category['category_name'],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>


                <!-- COST -->

                <div class="form-group">

                    <label>
                        Cost Price
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="cost_price"
                        id="edit_cost"
                        required
                    >

                </div>


                <!-- SELLING -->

                <div class="form-group">

                    <label>
                        Selling Price
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="selling_price"
                        id="edit_selling"
                        required
                    >

                </div>


                <!-- CURRENT STOCK -->

                <div class="form-group">

                    <label>
                        Current Stock
                    </label>

                    <input
                        type="number"
                        id="edit_stock"
                        readonly
                    >

                </div>


                <!-- REORDER -->

                <div class="form-group">

                    <label>
                        Reorder Level
                    </label>

                    <input
                        type="number"
                        min="0"
                        name="reorder_level"
                        id="edit_reorder"
                        required
                    >

                </div>

            </div>


            <div class="modal-buttons">

                <button
                    type="button"
                    class="btn btn-cancel"
                    onclick="closeEditModal()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    name="update_product"
                    class="btn btn-save"
                >
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>


<!-- ========================================================
     PRODUCT MODAL JAVASCRIPT
======================================================== -->

<script>

/* ==========================================
   OPEN ADD MODAL
========================================== */

function openAddModal() {

    const modal =
        document.getElementById("addModal");

    if (modal) {
        modal.style.display = "flex";
    }

}


/* ==========================================
   CLOSE ADD MODAL
========================================== */

function closeAddModal() {

    const modal =
        document.getElementById("addModal");

    if (modal) {
        modal.style.display = "none";
    }

}


/* ==========================================
   OPEN EDIT MODAL
========================================== */

function openEditModal(product) {

    const modal =
        document.getElementById("editModal");

    if (!modal) {
        return;
    }


    document.getElementById("edit_id").value =
        product.id;

    document.getElementById("edit_code").value =
        product.product_code;

    document.getElementById("edit_name").value =
        product.product_name;

    document.getElementById("edit_category").value =
        product.category_id ?? "";

    document.getElementById("edit_cost").value =
        product.cost_price;

    document.getElementById("edit_selling").value =
        product.selling_price;

    document.getElementById("edit_stock").value =
        product.stock_quantity;

    document.getElementById("edit_reorder").value =
        product.reorder_level;


    modal.style.display = "flex";

}


/* ==========================================
   CLOSE EDIT MODAL
========================================== */

function closeEditModal() {

    const modal =
        document.getElementById("editModal");

    if (modal) {
        modal.style.display = "none";
    }

}


/* ==========================================
   CLOSE MODAL WHEN CLICKING OUTSIDE
========================================== */

window.addEventListener(
    "click",
    function(event) {

        if (
            event.target.classList.contains("modal")
        ) {

            event.target.style.display = "none";

        }

    }
);


/* ==========================================
   CLOSE MODAL WITH ESCAPE
========================================== */

document.addEventListener(
    "keydown",
    function(event) {

        if (event.key !== "Escape") {
            return;
        }


        document
            .querySelectorAll(".modal")
            .forEach(
                function(modal) {

                    modal.style.display = "none";

                }
            );

    }
);

</script>


<!-- ========================================================
     MOBILE SIDEBAR JAVASCRIPT
======================================================== -->

<script
    src="../assets/js/navigation.js"
></script>


</body>

</html>