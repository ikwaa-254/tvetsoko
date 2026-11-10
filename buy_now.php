<?php
require_once 'config.php';

if (!isBuyer()) {
    setToast('Please login to purchase!', 'error');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $productId = (int)$_POST['product_id'];
    $buyerId = $_SESSION['buyer_id'];

    // Get product details
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        setToast('Product not found!', 'error');
        header('Location: products.php');
        exit;
    }

    $currentBuyer = getCurrentBuyer($pdo);
    $orderCode = 'ORD-' . strtoupper(substr(uniqid(), -8));
    $price = (float)$product['price'];
    $shipping = $price > 1000 ? 0 : 150;
    $total = $price + $shipping;

    try {
        $pdo->beginTransaction();

        // Insert order
        $orderStmt = $pdo->prepare("
            INSERT INTO orders (order_code, buyer_id, buyer_name, buyer_email, buyer_phone, delivery_address, notes, subtotal, shipping, total, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
        ");
        $orderStmt->execute([
            $orderCode, $buyerId, $currentBuyer['name'], $currentBuyer['email'],
            $currentBuyer['phone'], $currentBuyer['address'], '', $price, $shipping, $total
        ]);
        $orderId = $pdo->lastInsertId();

        // Insert order item
        $itemStmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, product_name, product_image, creator_name, price, quantity, total)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $itemStmt->execute([
            $orderId, $product['id'], $product['name'], $product['image_path'],
            $product['creator_name'], $price, 1, $price
        ]);

        $pdo->commit();

        setToast('Order ' . $orderCode . ' placed successfully!', 'success');
        header('Location: orders.php');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        setToast('Error placing order. Please try again.', 'error');
        header('Location: products.php');
        exit;
    }
}

header('Location: products.php');
exit;
?>