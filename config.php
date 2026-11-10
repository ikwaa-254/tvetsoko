<?php
/**
 * TvetSoko - Database Configuration
 * Update credentials for your XAMPP/MySQL setup
 */

session_start();

// Database credentials
$host = 'localhost';
$dbname = 'tvetsoko';
$username = 'root';      // Change if different
$password = '';          // Change if you set a MySQL password

// Create connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Helper function to check if user is admin
function isAdmin() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

// Helper function to check if buyer is logged in
function isBuyer() {
    return isset($_SESSION['buyer_id']) && !empty($_SESSION['buyer_id']);
}

// Helper function to get current buyer
function getCurrentBuyer($pdo) {
    if (!isBuyer()) return null;
    $stmt = $pdo->prepare("SELECT id, name, email, phone, address FROM buyers WHERE id = ?");
    $stmt->execute([$_SESSION['buyer_id']]);
    return $stmt->fetch();
}

// Helper function to show toast messages
function setToast($message, $type = 'info') {
    $_SESSION['toast'] = ['message' => $message, 'type' => $type];
}

function showToast() {
    if (isset($_SESSION['toast'])) {
        $toast = $_SESSION['toast'];
        unset($_SESSION['toast']);
        return json_encode($toast);
    }
    return 'null';
}
?>