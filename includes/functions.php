
<?php

// Start a session if one has not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect the user to another page
function redirect($url)
{
    header("Location: " . $url);
    exit;
}

// Protect pages that require a logged-in user
function requireLogin()
{
    if (!isset($_SESSION["user_id"])) {
        redirect("/moses-healthcare/index.php");
    }
}

// Restrict access according to the user's role
function requireRole($role)
{
    requireLogin();

    if (!isset($_SESSION["role"]) ||
        $_SESSION["role"] !== $role) {

        http_response_code(403);
        exit("Access denied. You do not have permission to view this page.");
    }
}

// Safely display text in HTML
function escape($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}
?>
