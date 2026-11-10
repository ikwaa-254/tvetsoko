<?php
require_once 'config.php';
header('Content-Type: application/json');

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = (int)$_GET['id'];

    // First check if product exists at all (any status)
    $checkStmt = $pdo->prepare("SELECT id, status FROM products WHERE id = ?");
    $checkStmt->execute([$id]);
    $exists = $checkStmt->fetch();

    if (!$exists) {
        echo json_encode(['error' => 'Product not found', 'reason' => 'Product ID does not exist in database']);
        exit;
    }

    if ($exists['status'] !== 'active') {
        echo json_encode(['error' => 'Product not found', 'reason' => 'Product is not active (status: ' . $exists['status'] . ')']);
        exit;
    }

    // Fetch full product details
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.id = ? AND p.status = 'active'
    ");
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if ($product) {
        echo json_encode($product);
    } else {
        echo json_encode(['error' => 'Product not found', 'reason' => 'Unknown error fetching product details']);
    }
} else {
    echo json_encode(['error' => 'No ID provided', 'reason' => 'Product ID parameter is missing']);
}
?>