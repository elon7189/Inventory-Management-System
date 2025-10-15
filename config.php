<?php
// Database configuration
$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'inventory_simple';

// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset('utf8');

// Helper function to get navigation menu
function getNavigation() {
    return [
        'Dashboard' => 'index.php',
        'Products' => 'products.php',
        'Suppliers' => 'suppliers.php',
        'Warehouses' => 'warehouses.php',
        'Stock Management' => 'stocks.php',
        'Orders' => 'orders.php'
    ];
}

// Helper function to sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Helper function to format currency
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

// Helper function to get current page name
function getCurrentPage() {
    $currentFile = basename($_SERVER['PHP_SELF']);
    return str_replace('.php', '', $currentFile);
}
?>
