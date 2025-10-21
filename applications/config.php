<?php
// Make $conn globally available
global $conn;

// Database configuration
$servername = 'localhost';
$username   = 'root';
$password   = 'your_mysql_password';
$dbname     = 'your_app_your_data';

// Try to load .env if it exists
$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
    $env_content = file_get_contents($env_file);
    if (preg_match('/DB_HOST\s*=\s*(.+)/', $env_content, $m)) $servername = trim($m[1], '\'" ');
    if (preg_match('/DB_USER\s*=\s*(.+)/', $env_content, $m)) $username = trim($m[1], '\'" ');
    if (preg_match('/DB_PASSWORD\s*=\s*(.+)/', $env_content, $m)) $password = trim($m[1], '\'" ');
    if (preg_match('/DB_NAME\s*=\s*(.+)/', $env_content, $m)) $dbname = trim($m[1], '\'" ');
}

// Create MySQLi connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    error_log("Database Connection Error: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");
?>