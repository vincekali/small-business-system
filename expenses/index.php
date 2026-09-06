<?php

require_once "../config/database.php";
require_once "../auth.php";

requireAdmin();


// =====================================================
// HELPER
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
// EXPENSE CATEGORIES
// =====================================================

$expense_categories = [
    "Electricity",
    "Water",
    "Rent",
    "Internet",
    "Supplies",
    "Transportation",
    "Salary",
    "Maintenance",
    "Other"
];


// =====================================================
// ADD EXPENSE
// =====================================================

if (isset($_POST["add_expense"])) {

    $description =
        trim($_POST["description"] ?? "");

    $category =
        trim($_POST["category"] ?? "");

    $amount =
        $_POST["amount"] ?? "";


    if ($description === "") {

        $error =
            "Please enter an expense description.";

    } elseif (strlen($description) > 255) {

        $error =
            "Expense description is too long.";

    } elseif (
        !in_array(
            $category,
            $expense_categories,
            true
        )
    ) {

        $error =
            "Please select a valid expense category.";

    } elseif (
        !is_numeric($amount) ||
        !is_finite((float) $amount) ||
        (float) $amount <= 0
    ) {

        $error =
            "Expense amount must be greater than zero.";

    } else {

        $amount = (float) $amount;


        $stmt = $conn->prepare("
            INSERT INTO expenses
            (
                description,
                category,
                amount
            )
            VALUES (?, ?, ?)
        ");


        if (!$stmt) {

            $error =
                "Unable to prepare expense.";

        } else {

            $stmt->bind_param(
                "ssd",
                $description,
                $category,
                $amount
            );


            if ($stmt->execute()) {

                $stmt->close();

                header(
                    "Location: index.php?success=added"
                );

                exit;

            }


            $stmt->close();

            $error =
                "Unable to add expense.";

        }

    }

}


// =====================================================
// UPDATE EXPENSE
// =====================================================

if (isset($_POST["update_expense"])) {

    $id =
        filter_input(
            INPUT_POST,
            "id",
            FILTER_VALIDATE_INT
        );


    $description =
        trim($_POST["description"] ?? "");

    $category =
        trim($_POST["category"] ?? "");

    $amount =
        $_POST["amount"] ?? "";


    if (!$id || $id <= 0) {

        $error =
            "Invalid expense.";

    } elseif ($description === "") {

        $error =
            "Please enter an expense description.";

    } elseif (strlen($description) > 255) {

        $error =
            "Expense description is too long.";

    } elseif (
        !in_array(
            $category,
            $expense_categories,
            true
        )
    ) {

        $error =
            "Please select a valid expense category.";

    } elseif (
        !is_numeric($amount) ||
        !is_finite((float) $amount) ||
        (float) $amount <= 0
    ) {

        $error =
            "Expense amount must be greater than zero.";

    } else {

        $amount = (float) $amount;


        // Check that expense exists.

        $check =
            $conn->prepare("
                SELECT id
                FROM expenses
                WHERE id = ?
                LIMIT 1
            ");


        if (!$check) {

            $error =
                "Unable to verify expense.";

        } else {

            $check->bind_param(
                "i",
                $id
            );

            $check->execute();

            $check_result =
                $check->get_result();

            $exists =
                $check_result->num_rows > 0;

            $check->close();


            if (!$exists) {

                $error =
                    "Expense not found.";

            } else {

                $stmt =
                    $conn->prepare("
                        UPDATE expenses
                        SET
                            description = ?,
                            category = ?,
                            amount = ?
                        WHERE id = ?
                    ");


                if (!$stmt) {

                    $error =
                        "Unable to prepare expense update.";

                } else {

                    $stmt->bind_param(
                        "ssdi",
                        $description,
                        $category,
                        $amount,
                        $id
                    );


                    if ($stmt->execute()) {

                        $stmt->close();

                        header(
                            "Location: index.php?success=updated"
                        );

                        exit;

                    }


                    $stmt->close();

                    $error =
                        "Unable to update expense.";

                }

            }

        }

    }

}


// =====================================================
// DELETE EXPENSE
// =====================================================

if (isset($_POST["delete_expense"])) {

    $id =
        filter_input(
            INPUT_POST,
            "id",
            FILTER_VALIDATE_INT
        );


    if (!$id || $id <= 0) {

        $error =
            "Invalid expense.";

    } else {

        $stmt =
            $conn->prepare("
                DELETE FROM expenses
                WHERE id = ?
            ");


        if (!$stmt) {

            $error =
                "Unable to prepare expense deletion.";

        } else {

            $stmt->bind_param(
                "i",
                $id
            );


            if ($stmt->execute()) {

                $stmt->close();

                header(
                    "Location: index.php?success=deleted"
                );

                exit;

            }


            $stmt->close();

            $error =
                "Unable to delete expense.";

        }

    }

}


// =====================================================
// SEARCH
// =====================================================

$search =
    trim($_GET["search"] ?? "");


// =====================================================
// GET EXPENSES
// =====================================================

$stmt =
    $conn->prepare("
        SELECT
            id,
            description,
            category,
            amount,
            expense_date

        FROM expenses

        WHERE description LIKE ?
           OR category LIKE ?

        ORDER BY expense_date DESC,
                 id DESC
    ");


if (!$stmt) {

    die(
        "Unable to load expenses."
    );

}


$searchTerm =
    "%" . $search . "%";


$stmt->bind_param(
    "ss",
    $searchTerm,
    $searchTerm
);


if (!$stmt->execute()) {

    $stmt->close();

    die(
        "Unable to load expenses."
    );

}


$expenses =
    $stmt->get_result();


// =====================================================
// TOTAL EXPENSES
// =====================================================

$result =
    $conn->query("
        SELECT
            COALESCE(
                SUM(amount),
                0
            ) AS total

        FROM expenses
    ");


if ($result) {

    $total_expenses =
        getMoneyValue(
            $result->fetch_assoc()["total"] ?? 0
        );

} else {

    $total_expenses = 0.00;

}


// =====================================================
// TODAY'S EXPENSES
// =====================================================

$result =
    $conn->query("
        SELECT
            COALESCE(
                SUM(amount),
                0
            ) AS total

        FROM expenses

        WHERE DATE(expense_date) =
              CURDATE()
    ");


if ($result) {

    $today_expenses =
        getMoneyValue(
            $result->fetch_assoc()["total"] ?? 0
        );

} else {

    $today_expenses = 0.00;

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
        Expense Management
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
        href="../assets/css/expenses.css"
    >

</head>


<body>


<!-- ==========================================
     MOBILE MENU
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


        <a href="../reports/sales.php">
            Sales Reports
        </a>


        <a
            href="index.php"
            class="active"
        >
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


    <!-- HEADER -->

    <div class="header">

        <h1>
            Expense Management
        </h1>

        <p>
            Record and monitor business expenses
        </p>

    </div>


    <!-- ======================================
         ERROR
    ======================================= -->

    <?php if (isset($error)): ?>

        <div class="error">

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </div>

    <?php endif; ?>


    <!-- ======================================
         SUCCESS
    ======================================= -->

    <?php if (isset($_GET["success"])): ?>

        <?php

        $success_message = "";

        if (
            $_GET["success"] === "added"
        ) {

            $success_message =
                "Expense added successfully.";

        } elseif (
            $_GET["success"] === "updated"
        ) {

            $success_message =
                "Expense updated successfully.";

        } elseif (
            $_GET["success"] === "deleted"
        ) {

            $success_message =
                "Expense deleted successfully.";

        }

        ?>


        <?php if ($success_message !== ""): ?>

            <div class="success">

                <?= htmlspecialchars(
                    $success_message,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>

        <?php endif; ?>

    <?php endif; ?>


    <!-- ======================================
         SUMMARY
    ======================================= -->

    <div class="cards">


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


        <div class="card">

            <div class="card-title">
                Total Recorded Expenses
            </div>

            <div class="card-value">

                ₱<?= number_format(
                    $total_expenses,
                    2
                ) ?>

            </div>

        </div>


    </div>


    <!-- ======================================
         ADD EXPENSE
    ======================================= -->

    <div class="form-box">


        <div class="section-header">

            <div>

                <h2>
                    Add Expense
                </h2>

                <p>
                    Record a new business expense
                </p>

            </div>

        </div>


        <form
            method="POST"
            class="expense-form"
        >


            <div class="form-group">

                <label for="description">
                    Description
                </label>

                <input
                    type="text"
                    id="description"
                    name="description"
                    placeholder="Expense description"
                    maxlength="255"
                    required
                >

            </div>


            <div class="form-group">

                <label for="category">
                    Category
                </label>

                <select
                    id="category"
                    name="category"
                    required
                >

                    <option value="">
                        Select Category
                    </option>


                    <?php foreach (
                        $expense_categories
                        as $expense_category
                    ): ?>

                        <option
                            value="<?= htmlspecialchars(
                                $expense_category,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                        >

                            <?= htmlspecialchars(
                                $expense_category,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="form-group">

                <label for="amount">
                    Amount
                </label>

                <input
                    type="number"
                    id="amount"
                    name="amount"
                    placeholder="0.00"
                    step="0.01"
                    min="0.01"
                    required
                >

            </div>


            <div class="form-submit">

                <button
                    type="submit"
                    name="add_expense"
                    class="btn btn-add"
                >
                    + Add Expense
                </button>

            </div>


        </form>


    </div>


    <!-- ======================================
         EXPENSE TABLE
    ======================================= -->

    <div class="table-box">


        <div class="table-header">


            <div>

                <h2>
                    Expense Records
                </h2>

                <p>
                    View and manage recorded expenses
                </p>

            </div>


        </div>


        <!-- SEARCH -->

        <form
            method="GET"
            class="search-form"
        >


            <input
                type="text"
                name="search"
                placeholder="Search description or category..."
                value="<?= htmlspecialchars(
                    $search,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >


            <button
                type="submit"
                class="btn btn-save"
            >
                Search
            </button>


            <?php if ($search !== ""): ?>

                <a
                    href="index.php"
                    class="btn btn-cancel clear-search"
                >
                    Clear
                </a>

            <?php endif; ?>


        </form>


        <!-- TABLE -->

        <div class="table-container">


            <table>


                <thead>

                    <tr>

                        <th>
                            Description
                        </th>

                        <th>
                            Category
                        </th>

                        <th>
                            Amount
                        </th>

                        <th>
                            Date
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php if (
                        $expenses->num_rows > 0
                    ): ?>


                        <?php while (
                            $expense =
                            $expenses->fetch_assoc()
                        ): ?>


                            <tr>


                                <!-- DESCRIPTION -->

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $expense[
                                                "description"
                                            ],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- CATEGORY -->

                                <td>

                                    <span class="category-badge">

                                        <?= htmlspecialchars(
                                            $expense[
                                                "category"
                                            ],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </span>

                                </td>


                                <!-- AMOUNT -->

                                <td>

                                    <strong class="amount">

                                        ₱<?= number_format(
                                            getMoneyValue(
                                                $expense[
                                                    "amount"
                                                ]
                                            ),
                                            2
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <?= htmlspecialchars(
                                        date(
                                            "M d, Y h:i A",
                                            strtotime(
                                                $expense[
                                                    "expense_date"
                                                ]
                                            )
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div class="actions">


                                        <button
                                            type="button"
                                            class="btn btn-edit"
                                            data-expense="<?= htmlspecialchars(
                                                json_encode(
                                                    [
                                                        "id" =>
                                                            (int) $expense["id"],

                                                        "description" =>
                                                            $expense["description"],

                                                        "category" =>
                                                            $expense["category"],

                                                        "amount" =>
                                                            $expense["amount"]
                                                    ],
                                                    JSON_HEX_TAG |
                                                    JSON_HEX_APOS |
                                                    JSON_HEX_AMP |
                                                    JSON_HEX_QUOT
                                                ),
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>"
                                        >
                                            Edit
                                        </button>


                                        <form
                                            method="POST"
                                            class="delete-form"
                                        >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $expense["id"] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="delete_expense"
                                                class="btn btn-danger"
                                                onclick="return confirm('Delete this expense?');"
                                            >
                                                Delete
                                            </button>

                                        </form>


                                    </div>

                                </td>


                            </tr>


                        <?php endwhile; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="5"
                                class="empty"
                            >

                                <?php if ($search !== ""): ?>

                                    No expenses found
                                    matching your search.

                                <?php else: ?>

                                    No expenses recorded.

                                <?php endif; ?>

                            </td>

                        </tr>


                    <?php endif; ?>


                </tbody>


            </table>


        </div>


    </div>


</div>


<!-- ==========================================
     EDIT MODAL
========================================== -->

<div
    class="modal"
    id="editModal"
>


    <div class="modal-content">


        <button
            type="button"
            id="editModalClose"
            class="modal-close"
            aria-label="Close"
        >
            ×
        </button>


        <h2>
            Edit Expense
        </h2>


        <form method="POST">


            <input
                type="hidden"
                name="id"
                id="edit_id"
            >


            <div class="form-group">

                <label for="edit_description">
                    Description
                </label>

                <input
                    type="text"
                    name="description"
                    id="edit_description"
                    maxlength="255"
                    required
                >

            </div>


            <div class="form-group">

                <label for="edit_category">
                    Category
                </label>

                <select
                    name="category"
                    id="edit_category"
                    required
                >


                    <?php foreach (
                        $expense_categories
                        as $expense_category
                    ): ?>

                        <option
                            value="<?= htmlspecialchars(
                                $expense_category,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                        >

                            <?= htmlspecialchars(
                                $expense_category,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </option>

                    <?php endforeach; ?>


                </select>

            </div>


            <div class="form-group">

                <label for="edit_amount">
                    Amount
                </label>

                <input
                    type="number"
                    name="amount"
                    id="edit_amount"
                    step="0.01"
                    min="0.01"
                    required
                >

            </div>


            <div class="modal-buttons">

                <button
                    type="button"
                    class="btn btn-cancel"
                    id="editCancelBtn"
                >
                    Cancel
                </button>


                <button
                    type="submit"
                    name="update_expense"
                    class="btn btn-save"
                >
                    Save Changes
                </button>

            </div>


        </form>


    </div>


</div>


<script
    src="../assets/js/navigation.js"
></script>


<script>

const editModal =
    document.getElementById("editModal");

const editModalClose =
    document.getElementById("editModalClose");

const editCancelBtn =
    document.getElementById("editCancelBtn");


function openEditModal(expense) {

    document.getElementById("edit_id").value =
        expense.id;

    document.getElementById("edit_description").value =
        expense.description;

    document.getElementById("edit_category").value =
        expense.category;

    document.getElementById("edit_amount").value =
        expense.amount;


    editModal.style.display = "flex";


    document.body.style.overflow = "hidden";


    document.getElementById(
        "edit_description"
    ).focus();

}


function closeEditModal() {

    editModal.style.display = "none";

    document.body.style.overflow = "";

}


document.querySelectorAll(
    ".btn-edit"
).forEach(
    function(button) {

        button.addEventListener(
            "click",
            function() {

                try {

                    const expense =
                        JSON.parse(
                            button.dataset.expense
                        );

                    openEditModal(
                        expense
                    );

                } catch (error) {

                    console.error(
                        "Unable to open expense.",
                        error
                    );

                }

            }
        );

    }
);


if (editModalClose) {

    editModalClose.addEventListener(
        "click",
        closeEditModal
    );

}


if (editCancelBtn) {

    editCancelBtn.addEventListener(
        "click",
        closeEditModal
    );

}


if (editModal) {

    editModal.addEventListener(
        "click",
        function(event) {

            if (
                event.target === editModal
            ) {

                closeEditModal();

            }

        }
    );

}


document.addEventListener(
    "keydown",
    function(event) {

        if (
            event.key === "Escape" &&
            editModal &&
            editModal.style.display === "flex"
        ) {

            closeEditModal();

        }

    }
);

</script>


</body>

</html>

<?php

$stmt->close();

?>