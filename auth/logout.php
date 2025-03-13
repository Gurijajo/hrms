<?php
require_once '../includes/functions.php';

if (isset($_SESSION['user_id'])) {
    // Log the logout activity
    $database = new Database();
    $db = $database->getConnection();
    
    logActivity(
        $db,
        $_SESSION['user_id'],
        'LOGOUT',
        'User logged out successfully'
    );
}

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: ../index.php");
exit();
?>