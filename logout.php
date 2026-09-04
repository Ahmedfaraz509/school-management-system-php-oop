<?php

session_start();

// Clear all session data
$_SESSION = [];

// Destroy session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit;

?>