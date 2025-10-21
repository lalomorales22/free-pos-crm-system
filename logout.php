<?php
session_start();

// Store cart and age verification status
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$age_verified = isset($_SESSION['age_verified']) ? $_SESSION['age_verified'] : false;

// Clear all session variables
$_SESSION = [];

// Restore cart and age verification
$_SESSION['cart'] = $cart;
$_SESSION['age_verified'] = $age_verified;

// Redirect to home page
header('Location: index.php');
exit;
?>