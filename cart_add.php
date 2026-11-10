<?php
require_once 'config.php';

if (!isBuyer()) {
    setToast('Please login to add items to cart!', 'error');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $productId = (int)$_POST['product_id'];
    $buyerId = $_SESSION['buyer_id'];

    // Check if product exists
    $check = $pdo->prepare("SELECT id FROM products WHERE id = ? AND status = 'active'");
    $check->execute([$productId]);
    if (!$check->fetch()) {
        setToast('Product not found!', 'error');
        header('Location: products.php');
        exit;
    }

    // Check if already in cart
    $cartCheck = $pdo->prepare("SELECT id, quantity FROM cart WHERE buyer_id = ? AND product_id = ?");
    $cartCheck->execute([$buyerId, $productId]);
    $existing = $cartCheck->fetch();

    if ($existing) {
        $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + 1 WHERE id = ?");
        $stmt->execute([$existing['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO cart (buyer_id, product_id, quantity) VALUES (?, ?, 1)");
        $stmt->execute([$buyerId, $productId]);
    }

    setToast('Item added to cart!', 'success');
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'products.php'));
exit;
?>