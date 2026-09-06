<?php

require_once "../auth.php";

requireAdmin();


// =====================================================
// VARIABLES
// =====================================================

$message = "";
$error = "";


// =====================================================
// ADD USER
// =====================================================

if (isset($_POST["add_user"])) {

    $username = trim($_POST["username"] ?? "");
    $full_name = trim($_POST["full_name"] ?? "");
    $password = $_POST["password"] ?? "";
    $role = $_POST["role"] ?? "";


    // ---------------------------------------------
    // VALIDATION
    // ---------------------------------------------

    if (
        $username === "" ||
        $full_name === "" ||
        $password === ""
    ) {

        $error = "Please fill in all required fields.";

    } elseif (strlen($username) > 50) {

        $error = "Username must not exceed 50 characters.";

    } elseif (strlen($full_name) > 100) {

        $error = "Full name must not exceed 100 characters.";

    } elseif (strlen($password) < 6) {

        $error = "Password must be at least 6 characters.";

    } elseif (strlen($password) > 255) {

        $error = "Password is too long.";

    } elseif (!in_array($role, ["admin", "cashier"], true)) {

        $error = "Invalid user role.";

    } else {

        // ---------------------------------------------
        // CHECK DUPLICATE USERNAME
        // ---------------------------------------------

        $stmt = $conn->prepare("
            SELECT id
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error = "Unable to check username.";

        } else {

            $stmt->bind_param(
                "s",
                $username
            );

            if (!$stmt->execute()) {

                $stmt->close();

                $error = "Unable to check username.";

            } else {

                $result = $stmt->get_result();

                $exists = $result->num_rows > 0;

                $stmt->close();


                if ($exists) {

                    $error = "Username already exists.";

                } else {

                    // ---------------------------------------------
                    // HASH PASSWORD
                    // ---------------------------------------------

                    $hashed_password = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );


                    if ($hashed_password === false) {

                        $error = "Unable to secure the password.";

                    } else {

                        // ---------------------------------------------
                        // INSERT USER
                        // ---------------------------------------------

                        $stmt = $conn->prepare("
                            INSERT INTO users
                            (
                                username,
                                password,
                                full_name,
                                role,
                                status
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?,
                                'active'
                            )
                        ");


                        if (!$stmt) {

                            $error =
                                "Unable to prepare user creation.";

                        } else {

                            $stmt->bind_param(
                                "ssss",
                                $username,
                                $hashed_password,
                                $full_name,
                                $role
                            );


                            if ($stmt->execute()) {

                                $stmt->close();

                                header(
                                    "Location: users.php?success=added"
                                );

                                exit;

                            } else {

                                if ($conn->errno === 1062) {

                                    $error =
                                        "Username already exists.";

                                } else {

                                    $error =
                                        "Unable to create user.";
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


// =====================================================
// UPDATE USER
// =====================================================

if (isset($_POST["update_user"])) {

    $id = intval(
        $_POST["id"] ?? 0
    );

    $username = trim(
        $_POST["username"] ?? ""
    );

    $full_name = trim(
        $_POST["full_name"] ?? ""
    );

    $role = $_POST["role"] ?? "";

    $password = $_POST["password"] ?? "";

    $current_user_id = intval(
        $_SESSION["user_id"] ?? 0
    );


    // ---------------------------------------------
    // VALIDATION
    // ---------------------------------------------

    if ($id <= 0) {

        $error = "Invalid user.";

    } elseif ($username === "") {

        $error = "Username is required.";

    } elseif ($full_name === "") {

        $error = "Full name is required.";

    } elseif (strlen($username) > 50) {

        $error =
            "Username must not exceed 50 characters.";

    } elseif (strlen($full_name) > 100) {

        $error =
            "Full name must not exceed 100 characters.";

    } elseif (
        $password !== "" &&
        strlen($password) < 6
    ) {

        $error =
            "New password must be at least 6 characters.";

    } elseif (
        $password !== "" &&
        strlen($password) > 255
    ) {

        $error =
            "New password is too long.";

    } elseif (!in_array($role, ["admin", "cashier"], true)) {

        $error = "Invalid user role.";

    } elseif (
        $id === $current_user_id &&
        $role !== "admin"
    ) {

        $error =
            "You cannot change your own account role.";

    } else {

        // ---------------------------------------------
        // CHECK USER EXISTS
        // ---------------------------------------------

        $stmt = $conn->prepare("
            SELECT
                id,
                role,
                status
            FROM users
            WHERE id = ?
            LIMIT 1
        ");


        if (!$stmt) {

            $error = "Unable to check user.";

        } else {

            $stmt->bind_param(
                "i",
                $id
            );


            if (!$stmt->execute()) {

                $stmt->close();

                $error = "Unable to check user.";

            } else {

                $result = $stmt->get_result();

                $user = $result->fetch_assoc();

                $stmt->close();


                if (!$user) {

                    $error = "User not found.";

                } elseif (
                    $user["role"] === "admin" &&
                    $user["status"] === "active" &&
                    $role === "cashier"
                ) {

                    // ---------------------------------------------
                    // PREVENT REMOVING LAST ACTIVE ADMIN
                    // ---------------------------------------------

                    $stmt = $conn->prepare("
                        SELECT COUNT(*) AS total
                        FROM users
                        WHERE role = 'admin'
                        AND status = 'active'
                    ");


                    if (!$stmt) {

                        $error =
                            "Unable to check administrator accounts.";

                    } else {

                        if (!$stmt->execute()) {

                            $stmt->close();

                            $error =
                                "Unable to check administrator accounts.";

                        } else {

                            $result = $stmt->get_result();

                            $data = $result->fetch_assoc();

                            $stmt->close();


                            $active_admins = intval(
                                $data["total"]
                            );


                            if ($active_admins <= 1) {

                                $error =
                                    "The last active administrator cannot be changed to cashier.";
                            }
                        }
                    }

                } else {

                    // ---------------------------------------------
                    // CHECK DUPLICATE USERNAME
                    // ---------------------------------------------

                    $stmt = $conn->prepare("
                        SELECT id
                        FROM users
                        WHERE username = ?
                        AND id != ?
                        LIMIT 1
                    ");


                    if (!$stmt) {

                        $error =
                            "Unable to check duplicate username.";

                    } else {

                        $stmt->bind_param(
                            "si",
                            $username,
                            $id
                        );


                        if (!$stmt->execute()) {

                            $stmt->close();

                            $error =
                                "Unable to check duplicate username.";

                        } else {

                            $result =
                                $stmt->get_result();

                            $duplicate =
                                $result->num_rows > 0;

                            $stmt->close();


                            if ($duplicate) {

                                $error =
                                    "Username already exists.";

                            } else {

                                // ---------------------------------------------
                                // UPDATE WITH NEW PASSWORD
                                // ---------------------------------------------

                                if ($password !== "") {

                                    $hashed_password =
                                        password_hash(
                                            $password,
                                            PASSWORD_DEFAULT
                                        );


                                    if (
                                        $hashed_password === false
                                    ) {

                                        $error =
                                            "Unable to secure the new password.";

                                    } else {

                                        $stmt = $conn->prepare("
                                            UPDATE users
                                            SET
                                                username = ?,
                                                full_name = ?,
                                                password = ?,
                                                role = ?
                                            WHERE id = ?
                                        ");


                                        if (!$stmt) {

                                            $error =
                                                "Unable to prepare user update.";

                                        } else {

                                            $stmt->bind_param(
                                                "ssssi",
                                                $username,
                                                $full_name,
                                                $hashed_password,
                                                $role,
                                                $id
                                            );


                                            if ($stmt->execute()) {

                                                $stmt->close();

                                                header(
                                                    "Location: users.php?success=updated"
                                                );

                                                exit;

                                            } else {

                                                if (
                                                    $conn->errno === 1062
                                                ) {

                                                    $error =
                                                        "Username already exists.";

                                                } else {

                                                    $error =
                                                        "Unable to update user.";
                                                }

                                                $stmt->close();
                                            }
                                        }
                                    }


                                // ---------------------------------------------
                                // UPDATE WITHOUT PASSWORD
                                // ---------------------------------------------

                                } else {

                                    $stmt = $conn->prepare("
                                        UPDATE users
                                        SET
                                            username = ?,
                                            full_name = ?,
                                            role = ?
                                        WHERE id = ?
                                    ");


                                    if (!$stmt) {

                                        $error =
                                            "Unable to prepare user update.";

                                    } else {

                                        $stmt->bind_param(
                                            "sssi",
                                            $username,
                                            $full_name,
                                            $role,
                                            $id
                                        );


                                        if ($stmt->execute()) {

                                            $stmt->close();

                                            header(
                                                "Location: users.php?success=updated"
                                            );

                                            exit;

                                        } else {

                                            if (
                                                $conn->errno === 1062
                                            ) {

                                                $error =
                                                    "Username already exists.";

                                            } else {

                                                $error =
                                                    "Unable to update user.";
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
}


// =====================================================
// ACTIVATE / DEACTIVATE USER
// =====================================================

if (isset($_POST["toggle_status"])) {

    $id = intval(
        $_POST["id"] ?? 0
    );

    $current_user_id = intval(
        $_SESSION["user_id"] ?? 0
    );


    // ---------------------------------------------
    // VALIDATE ID
    // ---------------------------------------------

    if ($id <= 0) {

        $error = "Invalid user.";


    // ---------------------------------------------
    // PREVENT SELF-DEACTIVATION
    // ---------------------------------------------

    } elseif ($id === $current_user_id) {

        $error =
            "You cannot deactivate your own account.";

    } else {

        // ---------------------------------------------
        // GET CURRENT USER
        // ---------------------------------------------

        $stmt = $conn->prepare("
            SELECT
                id,
                role,
                status
            FROM users
            WHERE id = ?
            LIMIT 1
        ");


        if (!$stmt) {

            $error =
                "Unable to check user status.";

        } else {

            $stmt->bind_param(
                "i",
                $id
            );


            if (!$stmt->execute()) {

                $stmt->close();

                $error =
                    "Unable to check user status.";

            } else {

                $result =
                    $stmt->get_result();

                $user =
                    $result->fetch_assoc();

                $stmt->close();


                if (!$user) {

                    $error =
                        "User not found.";

                } else {

                    // ---------------------------------------------
                    // DETERMINE NEW STATUS
                    // ---------------------------------------------

                    if (
                        $user["status"] === "active"
                    ) {

                        $new_status = "inactive";

                    } else {

                        $new_status = "active";
                    }


                    // ---------------------------------------------
                    // PREVENT DEACTIVATING LAST ADMIN
                    // ---------------------------------------------

                    if (
                        $new_status === "inactive" &&
                        $user["role"] === "admin"
                    ) {

                        $stmt = $conn->prepare("
                            SELECT COUNT(*) AS total
                            FROM users
                            WHERE role = 'admin'
                            AND status = 'active'
                        ");


                        if (!$stmt) {

                            $error =
                                "Unable to check administrator accounts.";

                        } else {

                            if (!$stmt->execute()) {

                                $stmt->close();

                                $error =
                                    "Unable to check administrator accounts.";

                            } else {

                                $result =
                                    $stmt->get_result();

                                $data =
                                    $result->fetch_assoc();

                                $stmt->close();


                                $active_admins =
                                    intval(
                                        $data["total"]
                                    );


                                if (
                                    $active_admins <= 1
                                ) {

                                    $error =
                                        "The last active administrator cannot be deactivated.";
                                }
                            }
                        }
                    }


                    // ---------------------------------------------
                    // UPDATE STATUS
                    // ---------------------------------------------

                    if ($error === "") {

                        $stmt = $conn->prepare("
                            UPDATE users
                            SET status = ?
                            WHERE id = ?
                        ");


                        if (!$stmt) {

                            $error =
                                "Unable to prepare status update.";

                        } else {

                            $stmt->bind_param(
                                "si",
                                $new_status,
                                $id
                            );


                            if ($stmt->execute()) {

                                $stmt->close();


                                if (
                                    $new_status === "active"
                                ) {

                                    header(
                                        "Location: users.php?success=activated"
                                    );

                                } else {

                                    header(
                                        "Location: users.php?success=deactivated"
                                    );
                                }

                                exit;

                            } else {

                                $error =
                                    "Unable to update user status.";

                                $stmt->close();
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
                "User added successfully.";

            break;


        case "updated":

            $message =
                "User updated successfully.";

            break;


        case "activated":

            $message =
                "User activated successfully.";

            break;


        case "deactivated":

            $message =
                "User deactivated successfully.";

            break;
    }
}


// =====================================================
// SEARCH
// =====================================================

$search = trim(
    $_GET["search"] ?? ""
);


// =====================================================
// GET USERS
// =====================================================

if ($search !== "") {

    $search_value =
        "%" . $search . "%";


    $stmt = $conn->prepare("
        SELECT
            id,
            username,
            full_name,
            role,
            status,
            created_at
        FROM users
        WHERE username LIKE ?
           OR full_name LIKE ?
           OR role LIKE ?
           OR status LIKE ?
        ORDER BY id DESC
    ");


    if (!$stmt) {

        $error =
            "Unable to load users.";

        $users = false;

    } else {

        $stmt->bind_param(
            "ssss",
            $search_value,
            $search_value,
            $search_value,
            $search_value
        );


        if (!$stmt->execute()) {

            $stmt->close();

            $error =
                "Unable to load users.";

            $users = false;

        } else {

            $users =
                $stmt->get_result();

            $stmt->close();
        }
    }

} else {

    $users = $conn->query("
        SELECT
            id,
            username,
            full_name,
            role,
            status,
            created_at
        FROM users
        ORDER BY id DESC
    ");


    if (!$users) {

        $error =
            "Unable to load users.";
    }
}


// =====================================================
// USER COUNT
// =====================================================

$total_users = 0;

if ($users) {

    $total_users =
        $users->num_rows;
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

    <title>User Management</title>


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
        href="../assets/css/users.css"
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


        <a href="categories.php">
            Categories
        </a>


        <a
            href="users.php"
            class="active"
        >
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
         PAGE HEADER
    ================================================= -->

    <div class="header">

        <h1>
            User Management
        </h1>

        <p>
            Manage system administrators and cashiers
        </p>

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
         USER HEADER
    ================================================= -->

    <div class="user-header">


        <div>

            <h2>
                User List
            </h2>

            <p>

                <?= (int)$total_users ?>

                user(s) found

            </p>

        </div>


        <button
            type="button"
            class="btn btn-add"
            onclick="openAddModal()"
        >
            + Add User
        </button>


    </div>


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
                placeholder="Search username, name, role, or status..."
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
                    href="users.php"
                    class="btn btn-cancel clear-search"
                >
                    Clear
                </a>

            <?php endif; ?>


        </form>


    </div>


    <!-- =================================================
         USER TABLE
    ================================================= -->

    <div class="section">


        <div class="section-header">

            <h2>
                System Users
            </h2>

        </div>


        <div class="table-container">


            <?php if (
                $users &&
                $users->num_rows > 0
            ): ?>


                <table>


                    <thead>

                        <tr>

                            <th>ID</th>

                            <th>Full Name</th>

                            <th>Username</th>

                            <th>Role</th>

                            <th>Status</th>

                            <th>Created</th>

                            <th>Actions</th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php while (
                            $user =
                            $users->fetch_assoc()
                        ): ?>


                            <tr>


                                <!-- ID -->

                                <td>
                                    <?= (int)$user["id"] ?>
                                </td>


                                <!-- FULL NAME -->

                                <td>

                                    <?= htmlspecialchars(
                                        $user["full_name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </td>


                                <!-- USERNAME -->

                                <td>

                                    <?= htmlspecialchars(
                                        $user["username"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </td>


                                <!-- ROLE -->

                                <td>

                                    <?php if (
                                        $user["role"] === "admin"
                                    ): ?>

                                        <span
                                            class="role role-admin"
                                        >
                                            Admin
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="role role-cashier"
                                        >
                                            Cashier
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php if (
                                        $user["status"] === "active"
                                    ): ?>

                                        <span
                                            class="status status-active"
                                        >
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="status status-inactive"
                                        >
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- CREATED -->

                                <td>

                                    <?= htmlspecialchars(
                                        date(
                                            "M d, Y",
                                            strtotime(
                                                $user["created_at"]
                                            )
                                        ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div class="actions">


                                        <!-- EDIT -->

                                        <button
                                            type="button"
                                            class="btn btn-edit"
                                            onclick='openEditModal(
                                                <?= htmlspecialchars(
                                                    json_encode(
                                                        $user,
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


                                        <!-- ACTIVATE / DEACTIVATE -->

                                        <?php if (
                                            (int)$user["id"] !==
                                            (int)$_SESSION["user_id"]
                                        ): ?>


                                            <form
                                                method="POST"
                                                onsubmit="return confirm('Change this user account status?');"
                                            >


                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int)$user["id"] ?>"
                                                >


                                                <?php if (
                                                    $user["status"] === "active"
                                                ): ?>

                                                    <button
                                                        type="submit"
                                                        name="toggle_status"
                                                        class="btn btn-danger"
                                                    >
                                                        Deactivate
                                                    </button>

                                                <?php else: ?>

                                                    <button
                                                        type="submit"
                                                        name="toggle_status"
                                                        class="btn btn-save"
                                                    >
                                                        Activate
                                                    </button>

                                                <?php endif; ?>


                                            </form>


                                        <?php endif; ?>


                                    </div>

                                </td>


                            </tr>


                        <?php endwhile; ?>


                    </tbody>


                </table>


            <?php else: ?>


                <div class="empty">
                    No users found.
                </div>


            <?php endif; ?>


        </div>


    </div>


</div>


<!-- =====================================================
     ADD USER MODAL
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
            aria-label="Close"
        >
            ×
        </button>


        <h2>
            Add User
        </h2>


        <form method="POST">


            <div class="form-group">

                <label>
                    Full Name
                </label>

                <input
                    type="text"
                    name="full_name"
                    placeholder="Enter full name"
                    maxlength="100"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Username
                </label>

                <input
                    type="text"
                    name="username"
                    placeholder="Enter username"
                    maxlength="50"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Password
                </label>

                <input
                    type="password"
                    name="password"
                    placeholder="Enter password"
                    minlength="6"
                    maxlength="255"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Role
                </label>

                <select
                    name="role"
                    required
                >

                    <option value="cashier">
                        Cashier
                    </option>

                    <option value="admin">
                        Admin
                    </option>

                </select>

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
                    name="add_user"
                    class="btn btn-save"
                >
                    Add User
                </button>


            </div>


        </form>


    </div>


</div>


<!-- =====================================================
     EDIT USER MODAL
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
            aria-label="Close"
        >
            ×
        </button>


        <h2>
            Edit User
        </h2>


        <form method="POST">


            <input
                type="hidden"
                name="id"
                id="edit_id"
            >


            <div class="form-group">

                <label>
                    Full Name
                </label>

                <input
                    type="text"
                    name="full_name"
                    id="edit_full_name"
                    maxlength="100"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Username
                </label>

                <input
                    type="text"
                    name="username"
                    id="edit_username"
                    maxlength="50"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    New Password
                </label>

                <input
                    type="password"
                    name="password"
                    id="edit_password"
                    placeholder="Leave blank to keep current password"
                    minlength="6"
                    maxlength="255"
                >

                <span class="form-help">
                    Leave this blank if you do not want to change the password.
                </span>

            </div>


            <div class="form-group">

                <label>
                    Role
                </label>

                <select
                    name="role"
                    id="edit_role"
                    required
                >

                    <option value="cashier">
                        Cashier
                    </option>

                    <option value="admin">
                        Admin
                    </option>

                </select>

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
                    name="update_user"
                    class="btn btn-save"
                >
                    Save Changes
                </button>


            </div>


        </form>


    </div>


</div>


<!-- =====================================================
     MODAL JAVASCRIPT
===================================================== -->

<script>

function openAddModal()
{
    document.getElementById(
        "addModal"
    ).style.display = "flex";
}


function closeAddModal()
{
    document.getElementById(
        "addModal"
    ).style.display = "none";
}


function openEditModal(user)
{
    document.getElementById(
        "edit_id"
    ).value = user.id;


    document.getElementById(
        "edit_full_name"
    ).value = user.full_name;


    document.getElementById(
        "edit_username"
    ).value = user.username;


    document.getElementById(
        "edit_role"
    ).value = user.role;


    document.getElementById(
        "edit_password"
    ).value = "";


    document.getElementById(
        "editModal"
    ).style.display = "flex";
}


function closeEditModal()
{
    document.getElementById(
        "editModal"
    ).style.display = "none";
}


// =====================================================
// CLOSE MODAL OUTSIDE
// =====================================================

window.addEventListener(
    "click",
    function(event)
    {

        const addModal =
            document.getElementById(
                "addModal"
            );


        const editModal =
            document.getElementById(
                "editModal"
            );


        if (
            event.target === addModal
        ) {

            closeAddModal();

        }


        if (
            event.target === editModal
        ) {

            closeEditModal();

        }

    }
);


// =====================================================
// ESC KEY
// =====================================================

document.addEventListener(
    "keydown",
    function(event)
    {

        if (
            event.key === "Escape"
        ) {

            closeAddModal();

            closeEditModal();

        }

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