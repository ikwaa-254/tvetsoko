<?php
require_once 'config.php';

if (!isBuyer()) {
    header('Location: index.php');
    exit;
}

$buyerId = $_SESSION['buyer_id'];

// Handle quantity update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_qty'], $_POST['cart_id'])) {
        $cartId = (int)$_POST['cart_id'];
        $qty = (int)$_POST['update_qty'];
        if ($qty <= 0) {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND buyer_id = ?");
            $stmt->execute([$cartId, $buyerId]);
        } else {
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND buyer_id = ?");
            $stmt->execute([$qty, $cartId, $buyerId]);
        }
    }
    if (isset($_POST['remove_item'], $_POST['cart_id'])) {
        $cartId = (int)$_POST['cart_id'];
        $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND buyer_id = ?");
        $stmt->execute([$cartId, $buyerId]);
    }
    header('Location: cart.php');
    exit;
}

// Fetch cart items with product details
$stmt = $pdo->prepare("
    SELECT c.id as cart_id, c.quantity, p.id as product_id, p.name, p.price, p.image_path, p.creator_name
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.buyer_id = ?
");
$stmt->execute([$buyerId]);
$cartItems = $stmt->fetchAll();

$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$shipping = $subtotal > 1000 ? 0 : 150;
$total = $subtotal + $shipping;

$currentBuyer = getCurrentBuyer($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart - TVETSOKO</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .container { 
            max-width: 900px; 
            margin: 0 auto; 
            padding: 3rem 2rem; 
        }
        .section-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: #f8fafc;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Reorganized Horizontal Cart Items Layout */
        .cart-item {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1.25rem 2rem;
            margin-bottom: 1.5rem;
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
            backdrop-filter: blur(12px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        .cart-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: #0f172a;
        }
        .cart-item-info {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            flex-grow: 1;
            gap: 1.5rem;
        }
        .item-text-details {
            flex: 2;
            min-width: 150px;
        }
        .cart-item-info h4 {
            font-size: 1.1rem;
            color: #f8fafc;
            margin-bottom: 0.25rem;
        }
        .cart-item-info .price {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary, #10b981);
            flex: 1;
            text-align: right;
            white-space: nowrap;
        }

        /* Vibrant Red Circle Quantity Styling Fix */
        .qty-form {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(0, 0, 0, 0.15);
            padding: 0.35rem 0.75rem;
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .qty-form button {
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 1.2rem;
            cursor: pointer;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s ease;
        }
        .qty-form button:hover {
            color: #fff;
        }
        .qty-form span {
            font-weight: 700;
            color: #fff;
            min-width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: 2px solid #ef4444; 
            background: rgba(239, 68, 68, 0.1);
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.2);
            font-size: 0.95rem;
        }

        /* Trash Actions Alignment */
        .remove-btn {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #ef4444;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .remove-btn:hover {
            background: #ef4444;
            color: #fff;
            border-color: #ef4444;
            transform: scale(1.05);
        }

        /* Consolidated Checkout Summary Panel */
        .cart-summary {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 2rem;
            margin-top: 2.5rem;
            backdrop-filter: blur(12px);
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            color: #94a3b8;
            font-size: 1rem;
        }
        .summary-row.total {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1.25rem;
            margin-top: 0.5rem;
            color: #fff;
            font-size: 1.4rem;
            font-weight: 700;
        }
        .summary-row.total span:last-child {
            color: var(--primary, #10b981);
        }
        
        /* Updated Actions Buttons with Clear White Typography Visibility */
        .summary-actions {
            display: flex;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .summary-actions .btn {
            flex: 1;
            padding: 1rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.2s ease;
            color: #ffffff !important; /* Forces typography color to white */
        }
        .summary-actions .btn-primary {
            background-color: var(--primary, #10b981);
            border: 1px solid var(--primary, #10b981);
        }
        .summary-actions .btn-primary:hover {
            background-color: var(--primary-dark, #059669);
            border-color: var(--primary-dark, #059669);
            transform: translateY(-2px);
        }
        .summary-actions .btn-secondary {
            background-color: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .summary-actions .btn-secondary:hover {
            background-color: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            color: #94a3b8;
        }
        .empty-state i { font-size: 4rem; color: rgba(255, 255, 255, 0.1); margin-bottom: 1.5rem; }
        .empty-state h3 { color: #f8fafc; margin-bottom: 0.5rem; }
        .empty-state .btn-success {
            color: #ffffff !important;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.85rem 2rem;
            border-radius: 12px;
            background: var(--primary, #10b981);
        }
    </style>
</head>
<body>
    <div class="bg-animation" id="bgAnimation"></div>

    <nav class="navbar">
        <a href="index.php" class="logo"><i class="fas fa-school"></i>TVETSOKO </a>
        <div class="nav-links">
            <a href="products.php"><i class="fas fa-arrow-left"></i> Continue Shopping</a>
        </div>
    </nav>

    <div class="container">
        <h2 class="section-title"><i class="fas fa-shopping-cart"></i> Shopping Cart</h2>

        <?php if (empty($cartItems)): ?>
        <div class="empty-state">
            <i class="fas fa-shopping-basket"></i>
            <h3>Your cart is empty</h3>
            <p>Browse our collection and add items to your cart!</p>
            <a href="products.php" class="btn btn-success" style="margin-top:1rem; max-width: 300px; margin-left: auto; margin-right: auto;">
                <i class="fas fa-shopping-bag"></i> Continue Shopping
            </a>
        </div>
        <?php else: ?>
            <?php foreach ($cartItems as $item): ?>
            <div class="cart-item">
                <img src="<?php echo $item['image_path'] ? 'uploads/' . htmlspecialchars($item['image_path']) : 'https://via.placeholder.com/80?text=No+Image'; ?>" 
                     alt="<?php echo htmlspecialchars($item['name']); ?>">
                
                <div class="cart-item-info">
                    <div class="item-text-details">
                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                        <p style="color:#94a3b8; font-size:0.9rem; margin: 0;">By <?php echo htmlspecialchars($item['creator_name']); ?></p>
                    </div>
                    
                    <form method="POST" class="qty-form">
                        <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                        <button type="submit" name="update_qty" value="<?php echo max(0, $item['quantity'] - 1); ?>">-</button>
                        <span><?php echo $item['quantity']; ?></span>
                        <button type="submit" name="update_qty" value="<?php echo $item['quantity'] + 1; ?>">+</button>
                    </form>
                    
                    <div class="price">KES <?php echo number_format($item['price']); ?></div>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                    <button type="submit" name="remove_item" class="remove-btn" title="Remove">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
            <?php endforeach; ?>

            <div class="cart-summary">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>KES <?php echo number_format($subtotal); ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping</span>
                    <span><?php echo $shipping === 0 ? 'FREE' : 'KES ' . number_format($shipping); ?></span>
                </div>
                <div class="summary-row total">
                    <span>Total</span>
                    <span>KES <?php echo number_format($total); ?></span>
                </div>
                <div class="summary-actions">
                    <a href="products.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Continue Shopping
                    </a>
                    <a href="checkout.php" class="btn btn-primary">
                        <i class="fas fa-credit-card"></i> Proceed to Checkout
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function createBackgroundAnimation() {
            const container = document.getElementById('bgAnimation');
            if(!container) return;
            for (let i = 0; i < 15; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                const size = Math.random() * 100 + 50;
                particle.style.width  = size + 'px';
                particle.style.height = size + 'px';
                particle.style.left   = Math.random() * 100 + '%';
                particle.style.top    = Math.random() * 100 + '%';
                particle.style.animationDelay    = Math.random() * 20 + 's';
                particle.style.animationDuration = (Math.random() * 20 + 20) + 's';
                container.appendChild(particle);
            }
        }
        createBackgroundAnimation();
    </script>
</body>
</html>