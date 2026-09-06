<?php

session_start();

require_once "config/database.php";


// =====================================================
// REDIRECT IF ALREADY LOGGED IN
// =====================================================

if (isset($_SESSION['user_id'])) {

    if (
        isset($_SESSION['role']) &&
        $_SESSION['role'] === 'admin'
    ) {

        header("Location: admin/dashboard.php");
        exit;

    } else {

        header("Location: pos/index.php");
        exit;
    }
}


// =====================================================
// VARIABLES
// =====================================================

$error = "";


// =====================================================
// LOGIN PROCESS
// =====================================================

if (isset($_POST['login'])) {

    // -------------------------------------------------
    // Get form values
    // -------------------------------------------------

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';


    // -------------------------------------------------
    // Validate input
    // -------------------------------------------------

    if ($username === "" || $password === "") {

        $error = "Please enter username and password.";

    } else {

        // -------------------------------------------------
        // Find user
        // -------------------------------------------------

        $stmt = $conn->prepare("
            SELECT
                id,
                username,
                password,
                full_name,
                role,
                status
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error = "Database error. Please try again.";

        } else {

            $stmt->bind_param(
                "s",
                $username
            );

            $stmt->execute();

            $result = $stmt->get_result();

            $user = $result->fetch_assoc();


            // -------------------------------------------------
            // Check account
            // -------------------------------------------------

            if (!$user) {

                $error = "Invalid username or password.";

            } elseif ($user['status'] === 'inactive') {

                $error =
                    "Your account is inactive. " .
                    "Please contact the administrator.";

            } elseif (!password_verify(
                $password,
                $user['password']
            )) {

                $error = "Invalid username or password.";

            } else {

                // -------------------------------------------------
                // Login successful
                // -------------------------------------------------

                session_regenerate_id(true);


                // Save user information in session

                $_SESSION['user_id'] =
                    $user['id'];

                $_SESSION['username'] =
                    $user['username'];

                $_SESSION['full_name'] =
                    $user['full_name'];

                $_SESSION['role'] =
                    $user['role'];


                // -------------------------------------------------
                // Redirect based on role
                // -------------------------------------------------

                if ($user['role'] === 'admin') {

                    header(
                        "Location: admin/dashboard.php"
                    );

                    exit;

                } else {

                    header(
                        "Location: pos/index.php"
                    );

                    exit;
                }
            }
        }
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

<title>Login - Business System</title>


<link rel="stylesheet" href="assets/css/global.css">
<link rel="stylesheet" href="assets/css/login.css">

</head>


<body>

<div class="login-container">

    <div class="login-box">

        <div class="logo">

            <h1>
                Small Business System
            </h1>

            <p>
                Financial Tracking & Point of Sale
            </p>

        </div>


        <!-- =========================================
             ERROR MESSAGE
        ========================================== -->

        <?php if ($error !== ""): ?>

            <div class="error">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <!-- =========================================
             LOGIN FORM
        ========================================== -->

        <form method="POST">

            <div class="form-group">

                <label>
                    Username
                </label>

                <input
                    type="text"
                    name="username"
                    placeholder="Enter username"
                    autocomplete="username"
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
                    autocomplete="current-password"
                    required
                >

            </div>


            <button
                type="submit"
                name="login"
                class="login-button"
            >
                Login
            </button>

        </form>


        <div class="footer">

            Small Business Financial Tracking System

        </div>

    </div>

</div>

</body>

</html>