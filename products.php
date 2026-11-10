<?php
require_once 'config.php';

// Fetch all active products
$stmt = $pdo->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'active' 
    ORDER BY p.created_at DESC
");
$products = $stmt->fetchAll();

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
    <title>Shop - TvetSoko</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Modern Clean Content Controls - Relocated Placement Styles */
        .controls {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            width: 100%;
        }
        .search-box {
            position: relative;
            flex: 1;
            min-width: 280px;
        }
        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        .search-box input {
            width: 100%;
            padding: 0.85rem 1rem 0.85rem 2.8rem;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s ease;
        }
        .search-box input:focus {
            border-color: var(--primary, #10b981);
        }
        .controls select {
            padding: 0.85rem 1.5rem;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(30, 41, 59, 0.98); /* Polished slate color fallback */
            color: #f8fafc; /* Replaced legacy neon color styling */
            font-size: 0.95rem;
            cursor: pointer;
            outline: none;
        }
        .controls select:focus {
            border-color: var(--primary, #10b981);
        }

        /* Structured Products Layout */
        .products-container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 3rem 2rem 4rem; 
        }
        .section-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #f8fafc;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .products-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
            gap: 2rem; 
            align-items: stretch;
        }

        /* Standardized Card Aesthetics */
        .product-card {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            backdrop-filter: blur(12px);
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
            border-color: rgba(16, 185, 129, 0.3);
        }
        .product-image-wrapper {
            position: relative;
            width: 100%;
            padding-top: 75%;
            overflow: hidden;
            background: #0f172a;
        }
        .product-image { 
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .product-info {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        .badge {
            background: rgba(255, 255, 255, 0.1);
            color: #f8fafc;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-new {
            background: var(--primary, #10b981);
            color: #fff;
        }
        .product-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0.75rem 0 0.5rem;
            color: #f8fafc;
            line-height: 1.4;
        }
        .product-creator {
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 0.75rem;
        }
        .product-description {
            font-size: 0.9rem;
            color: #94a3b8;
            line-height: 1.5;
            margin-bottom: 1.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .product-footer {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }
        .product-price {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--primary, #10b981);
        }
        .btn-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #f8fafc;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .btn-icon:hover {
            background: var(--primary, #10b981);
            color: white;
            border-color: var(--primary, #10b981);
            transform: scale(1.05);
        }
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 4rem 2rem;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="bg-animation" id="bgAnimation"></div>

    <nav class="navbar">
        <a href="index.php" class="logo">
            <i class="fas fa-school"></i> TvetSoko
        </a>
        <div class="nav-links">
            <a href="index.php"><i class="fas fa-home"></i> Home</a>
            
            <?php if (basename($_SERVER['PHP_SELF']) !== 'products.php'): ?>
            <a href="products.php"><i class="fas fa-shopping-bag"></i> Shop</a>
            <?php endif; ?>

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
            <a href="profile.php" class="btn btn-secondary">
                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($currentBuyer['name']); ?>
            </a>
            <?php elseif (!isAdmin()): ?>
            <a href="buyer_login.php" class="btn btn-secondary">
                <i class="fas fa-user"></i> Login
            </a>
            <?php endif; ?>
            <?php if (isAdmin()): ?>
            <a href="admin_orders.php" class="btn btn-secondary">
                <i class="fas fa-lock"></i> Admin
            </a>
            <?php endif; ?>
            <?php if (isBuyer() || isAdmin()): ?>
            <a href="logout.php" class="btn btn-danger">
                <i class="fas fa-sign-out-alt"></i>
            </a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="products-container">
        <!-- Text Title Header Element -->
        <h2 class="section-title"><i class="fas fa-shopping-bag"></i> All Products</h2>
        
        <!-- Controls Component Row Moved Safely After The Heading Block -->
        <div class="controls">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search products, creators, categories, or descriptions..." onkeyup="filterProducts()">
            </div>
            <select id="sortSelect" onchange="sortProducts()">
                <option value="newest">Newest First</option>
                <option value="price-low">Price: Low to High</option>
                <option value="price-high">Price: High to Low</option>
                <option value="name">Name A-Z</option>
            </select>
        </div>

        <div class="products-grid" id="productsGrid">
            <?php if (empty($products)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>No products yet</h3>
                <p>Be the first to add a student creation!</p>
            </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                <!-- Added data-category attribute loop hook mapping -->
                <div class="product-card" data-name="<?php echo strtolower(htmlspecialchars($product['name'])); ?>" 
                     data-creator="<?php echo strtolower(htmlspecialchars($product['creator_name'])); ?>"
                     data-category="<?php echo strtolower(htmlspecialchars($product['category_name'] ?? 'Other')); ?>"
                     data-desc="<?php echo strtolower(htmlspecialchars($product['description'])); ?>"
                     data-price="<?php echo $product['price']; ?>"
                     data-date="<?php echo $product['created_at']; ?>">
                    <div class="product-image-wrapper">
                        <img src="<?php echo $product['image_path'] ? 'uploads/' . htmlspecialchars($product['image_path']) : 'https://via.placeholder.com/400x300?text=No+Image'; ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                    </div>
                    <div class="product-info">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                            <span class="badge"><?php echo htmlspecialchars($product['category_name'] ?? 'Other'); ?></span>
                            <?php if (strtotime($product['created_at']) > strtotime('-7 days')): ?>
                            <span class="badge badge-new">NEW</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <div class="product-creator">
                            <i class="fas fa-user-graduate"></i> By <?php echo htmlspecialchars($product['creator_name']); ?>
                        </div>
                        <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                        <div class="product-footer">
                            <span class="product-price">KES <?php echo number_format($product['price']); ?></span>
                            <div class="product-actions">
                                <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="btn-icon" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (isBuyer()): ?>
                                <form action="cart_add.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="submit" class="btn-icon" title="Add to Cart">
                                        <i class="fas fa-cart-plus"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function filterProducts() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.product-card');
            cards.forEach(card => {
                const name = card.dataset.name;
                const creator = card.dataset.creator;
                const category = card.dataset.category; // Pulled dynamically from updated mapping parameters
                const desc = card.dataset.desc;
                
                // Evaluating matches including search parameters against the category tag value string
                if (name.includes(search) || creator.includes(search) || category.includes(search) || desc.includes(search)) {
                    card.style.display = 'flex'; 
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        function sortProducts() {
            const sort = document.getElementById('sortSelect').value;
            const grid = document.getElementById('productsGrid');
            const cards = Array.from(grid.querySelectorAll('.product-card'));
            cards.sort((a, b) => {
                switch(sort) {
                    case 'price-low': return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
                    case 'price-high': return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
                    case 'name': return a.dataset.name.localeCompare(b.dataset.name);
                    case 'newest': default: return new Date(b.dataset.date) - new Date(a.dataset.date);
                }
            });
            cards.forEach(card => grid.appendChild(card));
        }

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