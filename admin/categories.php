<?php

require_once "../auth.php";

requireAdmin();


// =====================================================
// VARIABLES
// =====================================================

$message = "";
$error = "";


// =====================================================
// ADD CATEGORY
// =====================================================

if (isset($_POST["add_category"])) {

    $category_name = trim(
        $_POST["category_name"] ?? ""
    );


    if ($category_name === "") {

        $error = "Category name is required.";

    } elseif (strlen($category_name) > 100) {

        $error = "Category name must not exceed 100 characters.";

    } else {

        $stmt = $conn->prepare("
            SELECT id
            FROM categories
            WHERE category_name = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error = "Unable to check category.";

        } else {

            $stmt->bind_param(
                "s",
                $category_name
            );

            if (!$stmt->execute()) {

                $stmt->close();

                $error = "Unable to check category.";

            } else {

                $result = $stmt->get_result();

                $exists = $result->num_rows > 0;

                $stmt->close();


                if ($exists) {

                    $error = "Category already exists.";

                } else {

                    $stmt = $conn->prepare("
                        INSERT INTO categories
                        (
                            category_name
                        )
                        VALUES (?)
                    ");

                    if (!$stmt) {

                        $error = "Unable to prepare category.";

                    } else {

                        $stmt->bind_param(
                            "s",
                            $category_name
                        );


                        if ($stmt->execute()) {

                            $stmt->close();

                            header(
                                "Location: categories.php?success=added"
                            );

                            exit;

                        } else {

                            if ($conn->errno === 1062) {

                                $error = "Category already exists.";

                            } else {

                                $error = "Unable to add category.";
                            }

                            $stmt->close();
                        }
                    }
                }
            }
        }
    }
}


// =====================================================
// UPDATE CATEGORY
// =====================================================

if (isset($_POST["update_category"])) {

    $id = intval(
        $_POST["id"] ?? 0
    );

    $category_name = trim(
        $_POST["category_name"] ?? ""
    );


    if ($id <= 0) {

        $error = "Invalid category.";

    } elseif ($category_name === "") {

        $error = "Category name is required.";

    } elseif (strlen($category_name) > 100) {

        $error = "Category name must not exceed 100 characters.";

    } else {

        $stmt = $conn->prepare("
            SELECT id
            FROM categories
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error = "Unable to check category.";

        } else {

            $stmt->bind_param(
                "i",
                $id
            );


            if (!$stmt->execute()) {

                $stmt->close();

                $error = "Unable to check category.";

            } else {

                $result = $stmt->get_result();

                $category_exists =
                    $result->num_rows > 0;

                $stmt->close();


                if (!$category_exists) {

                    $error = "Category not found.";

                } else {

                    $stmt = $conn->prepare("
                        SELECT id
                        FROM categories
                        WHERE category_name = ?
                        AND id != ?
                        LIMIT 1
                    ");

                    if (!$stmt) {

                        $error =
                            "Unable to check duplicate category.";

                    } else {

                        $stmt->bind_param(
                            "si",
                            $category_name,
                            $id
                        );


                        if (!$stmt->execute()) {

                            $stmt->close();

                            $error =
                                "Unable to check duplicate category.";

                        } else {

                            $result =
                                $stmt->get_result();

                            $duplicate =
                                $result->num_rows > 0;

                            $stmt->close();


                            if ($duplicate) {

                                $error =
                                    "Category already exists.";

                            } else {

                                $stmt = $conn->prepare("
                                    UPDATE categories
                                    SET category_name = ?
                                    WHERE id = ?
                                ");

                                if (!$stmt) {

                                    $error =
                                        "Unable to prepare category update.";

                                } else {

                                    $stmt->bind_param(
                                        "si",
                                        $category_name,
                                        $id
                                    );


                                    if ($stmt->execute()) {

                                        $stmt->close();

                                        header(
                                            "Location: categories.php?success=updated"
                                        );

                                        exit;

                                    } else {

                                        if ($conn->errno === 1062) {

                                            $error =
                                                "Category already exists.";

                                        } else {

                                            $error =
                                                "Unable to update category.";
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
    }
}


// =====================================================
// DELETE CATEGORY
// =====================================================

if (isset($_POST["delete_category"])) {

    $id = intval(
        $_POST["id"] ?? 0
    );


    if ($id <= 0) {

        $error = "Invalid category.";

    } else {

        $stmt = $conn->prepare("
            SELECT id
            FROM categories
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error = "Unable to check category.";

        } else {

            $stmt->bind_param(
                "i",
                $id
            );


            if (!$stmt->execute()) {

                $stmt->close();

                $error = "Unable to check category.";

            } else {

                $result = $stmt->get_result();

                $category_exists =
                    $result->num_rows > 0;

                $stmt->close();


                if (!$category_exists) {

                    $error = "Category not found.";

                } else {

                    $stmt = $conn->prepare("
                        SELECT COUNT(*) AS total
                        FROM products
                        WHERE category_id = ?
                    ");

                    if (!$stmt) {

                        $error =
                            "Unable to check category usage.";

                    } else {

                        $stmt->bind_param(
                            "i",
                            $id
                        );


                        if (!$stmt->execute()) {

                            $stmt->close();

                            $error =
                                "Unable to check category usage.";

                        } else {

                            $result =
                                $stmt->get_result();

                            $data =
                                $result->fetch_assoc();

                            $stmt->close();


                            $product_count =
                                intval(
                                    $data["total"]
                                );


                            if ($product_count > 0) {

                                $error =
                                    "This category cannot be deleted because "
                                    . $product_count
                                    . " product(s) are using it.";

                            } else {

                                $stmt = $conn->prepare("
                                    DELETE FROM categories
                                    WHERE id = ?
                                ");

                                if (!$stmt) {

                                    $error =
                                        "Unable to prepare category deletion.";

                                } else {

                                    $stmt->bind_param(
                                        "i",
                                        $id
                                    );


                                    if ($stmt->execute()) {

                                        $stmt->close();

                                        header(
                                            "Location: categories.php?success=deleted"
                                        );

                                        exit;

                                    } else {

                                        $error =
                                            "Unable to delete category.";

                                        $stmt->close();
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


// =====================================================
// SUCCESS MESSAGE
// =====================================================

if (isset($_GET["success"])) {

    switch ($_GET["success"]) {

        case "added":

            $message =
                "Category added successfully.";

            break;


        case "updated":

            $message =
                "Category updated successfully.";

            break;


        case "deleted":

            $message =
                "Category deleted successfully.";

            break;
    }
}


// =====================================================
// SEARCH
// =====================================================

$search =
    trim(
        $_GET["search"] ?? ""
    );


// =====================================================
// GET CATEGORIES
// =====================================================

if ($search !== "") {

    $search_value =
        "%" . $search . "%";


    $stmt = $conn->prepare("
        SELECT
            id,
            category_name,
            created_at
        FROM categories
        WHERE category_name LIKE ?
        ORDER BY id DESC
    ");


    if (!$stmt) {

        $error =
            "Unable to load categories.";

        $categories = false;

    } else {

        $stmt->bind_param(
            "s",
            $search_value
        );


        if (!$stmt->execute()) {

            $stmt->close();

            $error =
                "Unable to load categories.";

            $categories = false;

        } else {

            $categories =
                $stmt->get_result();

            $stmt->close();
        }
    }

} else {

    $categories = $conn->query("
        SELECT
            id,
            category_name,
            created_at
        FROM categories
        ORDER BY id DESC
    ");


    if (!$categories) {

        $error =
            "Unable to load categories.";
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

    <title>Category Management</title>


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
        href="../assets/css/categories.css"
    >

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<div
    class="sidebar"
    id="sidebar"
>

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
                    $_SESSION["full_name"] ?? "User",
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </strong>


            <span>

                <?= htmlspecialchars(
                    ucfirst(
                        $_SESSION["role"] ?? "User"
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
            ×
        </button>

    </div>


    <nav class="sidebar-nav">

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="products.php">
            Products
        </a>

        <a
            href="categories.php"
            class="active"
        >
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


<!-- =====================================================
     MOBILE OVERLAY
===================================================== -->

<div
    class="mobile-overlay"
    id="mobileOverlay"
></div>


<!-- =====================================================
     MAIN
===================================================== -->

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


    <!-- =================================================
         HEADER
    ================================================= -->

    <div class="header">

        <div>

            <h1>
                Category Management
            </h1>

            <p>
                Manage product categories
            </p>

        </div>


        <button
            type="button"
            class="btn btn-primary"
            onclick="openAddModal()"
        >
            + Add Category
        </button>

    </div>


    <!-- =================================================
         SUCCESS MESSAGE
    ================================================= -->

    <?php if ($message): ?>

        <div class="message">

            <?= htmlspecialchars(
                $message,
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         ERROR MESSAGE
    ================================================= -->

    <?php if ($error): ?>

        <div class="error">

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         SEARCH
    ================================================= -->

    <div class="search-box">

        <form
            method="GET"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                placeholder="Search category..."
                value="<?= htmlspecialchars(
                    $search,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >


            <button
                type="submit"
                class="btn btn-primary"
            >
                Search
            </button>


            <?php if ($search !== ""): ?>

                <a
                    href="categories.php"
                    class="btn btn-cancel"
                >
                    Clear
                </a>

            <?php endif; ?>

        </form>

    </div>


    <!-- =================================================
         CATEGORY SECTION
    ================================================= -->

    <div class="section">

        <div class="section-header">

            <div>

                <h2>
                    Product Categories
                </h2>

                <p>
                    Manage the categories used by your products.
                </p>

            </div>

        </div>


        <!-- =================================================
             TABLE
        ================================================= -->

        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Category Name
                        </th>

                        <th>
                            Created
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php if (
                        $categories &&
                        $categories->num_rows > 0
                    ): ?>


                        <?php while (
                            $category =
                            $categories->fetch_assoc()
                        ): ?>

                            <tr>

                                <td>
                                    <?= (int)$category["id"] ?>
                                </td>


                                <td>

                                    <span class="category-name">

                                        <?= htmlspecialchars(
                                            $category["category_name"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        date(
                                            "M d, Y",
                                            strtotime(
                                                $category["created_at"]
                                            )
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </td>


                                <td>

                                    <div class="actions">


                                        <button
                                            type="button"
                                            class="btn btn-edit"
                                            onclick='openEditModal(
                                                <?= htmlspecialchars(
                                                    json_encode(
                                                        $category,
                                                        JSON_HEX_TAG |
                                                        JSON_HEX_APOS |
                                                        JSON_HEX_AMP |
                                                        JSON_HEX_QUOT
                                                    ),
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>
                                            )'
                                        >
                                            Edit
                                        </button>


                                        <form
                                            method="POST"
                                            onsubmit="
                                                return confirm(
                                                    'Are you sure you want to delete this category?'
                                                );
                                            "
                                        >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$category["id"] ?>"
                                            >


                                            <button
                                                type="submit"
                                                name="delete_category"
                                                class="btn btn-danger"
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
                                colspan="4"
                                class="empty"
                            >
                                No categories found.
                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


</div>


<!-- =====================================================
     ADD CATEGORY MODAL
===================================================== -->

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
            Add Category
        </h2>


        <form method="POST">


            <div class="form-group">

                <label>
                    Category Name
                </label>


                <input
                    type="text"
                    name="category_name"
                    placeholder="Enter category name"
                    maxlength="100"
                    required
                >

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
                    name="add_category"
                    class="btn btn-save"
                >
                    Add Category
                </button>


            </div>


        </form>


    </div>

</div>


<!-- =====================================================
     EDIT CATEGORY MODAL
===================================================== -->

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
            Edit Category
        </h2>


        <form method="POST">


            <input
                type="hidden"
                name="id"
                id="edit_id"
            >


            <div class="form-group">

                <label>
                    Category Name
                </label>


                <input
                    type="text"
                    name="category_name"
                    id="edit_category_name"
                    maxlength="100"
                    required
                >

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
                    name="update_category"
                    class="btn btn-save"
                >
                    Save Changes
                </button>


            </div>


        </form>


    </div>

</div>


<!-- =====================================================
     CATEGORY MODAL JAVASCRIPT
===================================================== -->

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

function openEditModal(category) {

    const modal =
        document.getElementById("editModal");

    if (!modal) {
        return;
    }


    document.getElementById(
        "edit_id"
    ).value = category.id;


    document.getElementById(
        "edit_category_name"
    ).value = category.category_name;


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


<!-- =====================================================
     MOBILE NAVIGATION
===================================================== -->

<script
    src="../assets/js/navigation.js"
></script>


</body>

</html>