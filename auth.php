<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/config/database.php";


// =====================================================
// VERIFY CURRENT SESSION
// =====================================================

function verifySession()
{
    global $conn;

    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $user_id = intval($_SESSION['user_id']);

    $stmt = $conn->prepare("
        SELECT
            id,
            username,
            full_name,
            role,
            status
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("i", $user_id);

    if (!$stmt->execute()) {
        return false;
    }

    $result = $stmt->get_result();

    $user = $result->fetch_assoc();

    $stmt->close();

    // User no longer exists
    if (!$user) {
        return false;
    }

    // Account has been deactivated
    if ($user['status'] !== 'active') {
        return false;
    }

    // Update session information
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];

    return true;
}


// =====================================================
// LOGOUT / DESTROY SESSION
// =====================================================

function destroySession()
{
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}


// =====================================================
// REQUIRE LOGIN
// =====================================================

function requireLogin()
{
    if (!verifySession()) {

        destroySession();

        header("Location: ../login.php");
        exit;
    }
}


// =====================================================
// REQUIRE ADMIN
// =====================================================

function requireAdmin()
{
    requireLogin();

    if (
        !isset($_SESSION['role']) ||
        $_SESSION['role'] !== 'admin'
    ) {

        header("Location: ../pos/index.php");
        exit;
    }
}


// =====================================================
// CURRENT USER
// =====================================================

function currentUser()
{
    return [

        'id' =>
            $_SESSION['user_id'] ?? null,

        'username' =>
            $_SESSION['username'] ?? null,

        'full_name' =>
            $_SESSION['full_name'] ?? null,

        'role' =>
            $_SESSION['role'] ?? null

    ];
}

?>