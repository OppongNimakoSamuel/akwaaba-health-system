<?php
session_start();
require 'db.php';
$pdo = getDB();

// Unset all of the session variables
$_SESSION = array();

// If the session uses cookies, delete the session cookie securely.
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

// Finally, destroy the session.
session_destroy();

// Redirect back to the login page
header("Location: index.php");
exit;
?>