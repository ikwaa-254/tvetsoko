<?php
require_once 'config.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: products.php');
    exit;
}

$productId = (int)$_GET['id'];

// Fetch product with category
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.id = ? AND p.status = 'active'
");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    setToast('Product not found!', 'error');
    header('Location: products.php');
    exit;
}

$currentBuyer = getCurrentBuyer($pdo);
$cartCount = 0;
if (isBuyer()) {
    $cartStmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE buyer_id = ?");
    $cartStmt->execute([$_SESSION['buyer_id']]);
    $cartCount = $cartStmt->fetchColumn() ?: 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - TvetSoko</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .navbar {
            background: rgba(15, 23, 42, 0.85) !important;
            border-bottom: 1px solid rgba(128, 128, 128, 0.2); 
        }

        .back-link {
            display: inline-flex; align-items: center; gap: 0.5rem;
            color: #64748b; text-decoration: none; font-weight: 500;
            margin-bottom: 1.5rem; transition: all 0.2s ease;
        }
        .back-link:hover { color: var(--primary); transform: translateX(-3px); }

        /* --- Refactored Split Layout --- */
        .product-layout {
            display: grid; grid-template-columns: 1fr 1fr; gap: 4rem;
            background: #ffffff; border: 1px solid #e2e8f0;
            border-radius: 24px; padding: 2.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            margin-bottom: 3rem;
        }

        .product-image-container { position: relative; }
        .product-image-main {
            width: 100%; border-radius: 16px; object-fit: cover;
            max-height: 500px; border: 1px solid #cbd5e1;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }

        .category-badge {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.4rem 1rem; border-radius: 20px;
            font-size: 0.85rem; font-weight: 600;
            background: #f1f5f9; color: #475569;
            border: 1px solid #e2e8f0; margin-bottom: 1.25rem;
        }

        .product-info-detail h1 { font-size: 2.5rem; font-weight: 800; color: #0f172a; margin: 0 0 0.5rem 0; }
        
        .creator-line {
            font-size: 1.05rem; font-weight: 500; color: #475569;
            margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;
        }
        .creator-line a { color: var(--primary); text-decoration: none; font-weight: 600; }
        .creator-line a:hover { text-decoration: underline; }

        .price-big { font-size: 2.75rem; font-weight: 800; color: #16a34a; margin-bottom: 2rem; }

        /* --- Interactive Quantity Module --- */
        .quantity-selector-block {
            display: flex; align-items: center; gap: 1rem;
            margin-bottom: 2rem; padding: 1rem;
            background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;
            width: max-content;
        }
        .quantity-selector-block label { font-weight: 600; color: #334155; }
        .qty-controls { display: flex; align-items: center; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; }
        .qty-btn { background: none; border: none; width: 36px; height: 36px; cursor: pointer; font-size: 1.1rem; color: #475569; font-weight: bold; }
        .qty-btn:hover { background: #f1f5f9; }
        .qty-input { width: 50px; text-align: center; border: none; border-left: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1; font-weight: 600; height: 36px; font-size: 1rem; }

        .action-buttons { display: flex; gap: 1rem; }
        .action-buttons form { flex: 1; }
        .action-buttons .btn { width: 100%; padding: 0.85rem; font-weight: 600; border-radius: 10px; display: inline-flex; justify-content: center; align-items: center; gap: 0.5rem; cursor: pointer; }

        .meta-info { margin-top: 2rem; padding-top: 1.25rem; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 0.88rem; display: flex; align-items: center; gap: 0.5rem; }

        /* --- Premium Bottom Content Tabs --- */
        .product-tabs-container { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 2rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .tabs-header { display: flex; gap: 1.5rem; border-bottom: 2px solid #e2e8f0; margin-bottom: 1.5rem; }
        .tab-trigger { background: none; border: none; padding: 0.75rem 0.5rem; font-size: 1.05rem; font-weight: 600; color: #64748b; cursor: pointer; position: relative; transition: color 0.2s ease; }
        .tab-trigger:hover { color: var(--primary); }
        .tab-trigger.active { color: var(--primary); }
        .tab-trigger.active::after { content: ''; position: absolute; bottom: -2px; left: 0; width: 100%; height: 2px; background: var(--primary); }
        .tab-content { display: none; line-height: 1.8; color: #475569; font-size: 1.05rem; }
        .tab-content.active { display: block; }

        @media (max-width: 768px) {
            .product-layout { grid-template-columns: 1fr; gap: 2rem; padding: 1.5rem; }
            .product-info-detail h1 { font-size: 1.85rem; }
            .price-big { font-size: 2.2rem; }
            .action-buttons { flex-direction: column; }
            .quantity-selector-block { width: 100%; justify-content: space-between; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="logo"><i class="fas fa-school"></i> TvetSoko</a>
        <div class="nav-links">
            <a href="index.php"><i class="fas fa-home"></i> Home</a>
            <a href="products.php"><i class="fas fa-shopping-bag"></i> Shop</a>
            <?php if (isBuyer()): ?>
            <a href="orders.php"><i class="fas fa-box"></i> My Orders</a>
            <a href="cart.php" style="position:relative;">
                <i class="fas fa-shopping-cart"></i> Cart
                <?php if ($cartCount > 0): ?>
                <span class="cart-badge"><?php echo $cartCount; ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            <?php if (isBuyer()): ?>
            <a href="profile.php"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($currentBuyer['name']); ?></a>
            <?php elseif (!isAdmin()): ?>
            <a href="buyer_login.php"><i class="fas fa-user"></i> Login</a>
            <?php endif; ?>
            <?php if (isAdmin()): ?>
            <a href="admin_orders.php"><i class="fas fa-lock"></i> Admin</a>
            <?php endif; ?>
            <?php if (isBuyer() || isAdmin()): ?>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i></a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container">
        <a href="products.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Shop
        </a>

        <div class="product-layout">
            <div class="product-image-container">
                <img src="<?php echo $product['image_path'] ? 'uploads/' . htmlspecialchars($product['image_path']) : 'https://via.placeholder.com/600x500?text=No+Image'; ?>" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image-main">
            </div>
            
            <div class="product-info-detail">
                <span class="category-badge"><i class="fas fa-folder"></i> <?php echo htmlspecialchars($product['category_name'] ?? 'Other'); ?></span>
                <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <div class="creator-line">
                    <i class="fas fa-user-graduate"></i> By 
                    <a href="creator_profile.php?id=<?php echo $product['creator_id']; ?>">
                        <?php echo htmlspecialchars($product['creator_name']); ?>
                    </a>
                </div>
                
                <div class="price-big">KES <?php echo number_format($product['price']); ?></div>

                <div class="quantity-selector-block">
                    <label for="display_qty">Quantity</label>
                    <div class="qty-controls">
                        <button type="button" class="qty-btn" onclick="updateQty(-1)">-</button>
                        <input type="number" id="display_qty" class="qty-input" value="1" min="1" readonly>
                        <button type="button" class="qty-btn" onclick="updateQty(1)">+</button>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <?php if (isBuyer()): ?>
                    <form action="cart_add.php" method="POST" id="cartForm">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <input type="hidden" name="quantity" id="cart_qty_hidden" value="1">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-cart-plus"></i> Add to Cart
                        </button>
                    </form>
                    
                    <form action="checkout.php" method="POST" id="buyNowForm">
                        <input type="hidden" name="direct_buy_id" value="<?php echo $product['id']; ?>">
                        <input type="hidden" name="quantity" id="buy_qty_hidden" value="1">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-bolt"></i> Buy Now
                        </button>
                    </form>
                    <?php else: ?>
                    <a href="buyer_login.php" class="btn btn-primary" style="text-decoration:none;">
                        <i class="fas fa-sign-in-alt"></i> Login to Purchase
                    </a>
                    <?php endif; ?>
                </div>
                
                <div class="meta-info">
                    <i class="fas fa-calendar-alt"></i> Catalog Listing Added: <?php echo date('F j, Y', strtotime($product['created_at'])); ?>
                </div>
            </div>
        </div>

        <div class="product-tabs-container">
            <div class="tabs-header">
                <button class="tab-trigger active" onclick="switchTab(event, 'tab-desc')">Description</button>
                <button class="tab-trigger" onclick="switchTab(event, 'tab-reviews')">Reviews & Feedback</button>
            </div>
            
            <div id="tab-desc" class="tab-content active">
                <?php echo nl2br(htmlspecialchars($product['description'])); ?>
            </div>
            
            <div id="tab-reviews" class="tab-content">
                <div style="text-align: center; padding: 1.5rem; color: #64748b;">
                    <i class="fas fa-comments" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                    <p>No client reviews posted yet for this student creation.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Handler sync for keeping multiple purchase forms perfectly updated with current quantity parameters
        function updateQty(changeValue) {
            const displayInput = document.getElementById('display_qty');
            const cartHiddenInput = document.getElementById('cart_qty_hidden');
            const buyHiddenInput = document.getElementById('buy_qty_hidden');
            
            let currentQty = parseInt(displayInput.value) + changeValue;
            if (currentQty < 1) currentQty = 1;
            
            displayInput.value = currentQty;
            if (cartHiddenInput) cartHiddenInput.value = currentQty;
            if (buyHiddenInput) buyHiddenInput.value = currentQty;
        }

        // Interactive display tab switching modules logic
        function switchTab(event, tabId) {
            const triggers = document.querySelectorAll('.tab-trigger');
            const contents = document.querySelectorAll('.tab-content');
            
            triggers.forEach(btn => btn.classList.remove('active'));
            contents.forEach(box => box.classList.remove('active'));
            
            event.currentTarget.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }
    </script>
</body>
</html>