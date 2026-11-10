<?php
require_once 'config.php';

// Fetch all active products with category info
$stmt = $pdo->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'active' 
    ORDER BY p.created_at DESC
");
$products = $stmt->fetchAll();

// Get stats
$stats = [
    'products' => $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn(),
    'creators' => $pdo->query("SELECT COUNT(DISTINCT creator_name) FROM products WHERE status = 'active'")->fetchColumn()
];

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
    <title>TvetSoko - Creativity Marketplace</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero {
            text-align: center;
            padding: 4rem 2rem 2rem;
            max-width: 800px;
            margin: 0 auto;
        }
        .products-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem 4rem;
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            align-items: stretch;
        }
        .product-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .product-image-wrapper {
            width: 100%;
            height: 250px;
            overflow: hidden;
        }
        .product-image { 
            width: 100%;
            height: 100%;
            object-fit: cover;
            cursor: pointer; 
        }
        .product-info {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        .product-footer {
            margin-top: auto;
        }
        .site-footer {
            text-align: center;
            padding: 2rem;
            margin-top: 2rem;
            background: rgba(15, 23, 42, 0.95);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .site-footer p {
            margin: 0.5rem 0;
            color: #94a3b8;
        }
        .designer-name {
            font-weight: bold;
            color: var(--primary, #10b981);
        }

        /* --- High-Visibility Red Cart Badge Notification --- */
        .cart-badge {
            position: absolute;
            top: -6px;
            right: -10px;
            background: #ef4444;
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 9999px;
            min-width: 18px;
            height: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: badgePop 0.3s ease-out;
        }

        @keyframes badgePop {
            0% { transform: scale(0.5); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* ── Auto-Advancing Featured Slider Section ── */
        .featured-slider-section {
            width: 100%;
            max-width: 800px;
            margin: 0 auto 3rem auto;
            padding: 0 1rem;
            position: relative;
        }
        .featured-slider-section .section-label {
            text-align: center;
            font-size: 0.75rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--primary, #10b981);
            margin-bottom: 1.25rem;
            opacity: 0.9;
        }
        .slider-container {
            position: relative;
            width: 100%;
            height: 400px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            background: #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .slider-slide {
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity 1s ease-in-out, transform 1s ease-in-out;
            transform: scale(1.05);
            z-index: 0;
            text-decoration: none;
            display: block;
        }
        .slider-slide.active {
            opacity: 1;
            z-index: 1;
            transform: scale(1);
        }
        .slider-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .slide-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(15, 23, 42, 0.95) 0%, transparent 60%);
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 2rem;
            pointer-events: none;
        }
        .slide-name {
            color: #fff;
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .slide-price {
            color: var(--primary, #10b981);
            font-size: 1.25rem;
            font-weight: 800;
        }
        .slider-slide .badge-new-float {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--primary, #10b981);
            color: #fff;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            padding: 5px 12px;
            border-radius: 20px;
            text-transform: uppercase;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            z-index: 2;
        }
        .slider-dots {
            display: flex;
            justify-content: center;
            gap: 0.75rem;
            margin-top: 1.25rem;
        }
        .slider-dots .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            cursor: pointer;
            transition: background 0.3s ease, transform 0.3s ease;
        }
        .slider-dots .dot.active {
            background: var(--primary, #10b981);
            transform: scale(1.2);
        }
        .stats-bar {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin: 1.5rem 0;
            background: rgba(255, 255, 255, 0.05);
            padding: 1rem;
            border-radius: 8px;
        }
        .stat-item { text-align: center; }
        .stat-value { font-size: 1.5rem; font-weight: bold; color: #fff; }
        .stat-label { font-size: 0.8rem; color: #94a3b8; }

        /* --- Sleek Horizontal Ad Banner Layout Styles --- */
        .ad-container-wrapper {
            max-width: 800px;
            margin: 0 auto 2.5rem auto;
            padding: 0 1rem;
            width: 100%;
        }
        .soko-ad-banner {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 1.25rem 1rem 1rem 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            min-height: 85px;
            overflow: hidden;
            backdrop-filter: blur(8px);
        }
        .ad-badge-label {
            position: absolute;
            top: 4px;
            left: 8px;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(255, 255, 255, 0.3);
            font-weight: 700;
        }
        .ad-text-content {
            color: #94a3b8;
            font-size: 0.95rem;
            text-align: center;
            line-height: 1.4;
        }
        .ad-text-content strong {
            color: var(--primary, #10b981);
        }
        .ad-image-content {
            max-width: 100%;
            height: auto;
            max-height: 65px;
            border-radius: 6px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div class="bg-animation" id="bgAnimation"></div>

    <nav class="navbar">
        <a href="index.php" class="logo"><i class="fas fa-school"></i> TVETSOKO</a>
        <div class="nav-links">
            <a href="index.php"><i class="fas fa-home"></i> Home</a>
            <a href="products.php"><i class="fas fa-shopping-bag"></i> Shop</a>
            
            <?php if (isBuyer()): ?>
            <a href="orders.php"><i class="fas fa-box"></i> My Orders</a>
            <a href="cart.php" style="position:relative;">
                <i class="fas fa-shopping-cart"></i> Cart
                <?php if ($cartCount > 0): ?><span class="cart-badge"><?php echo $cartCount; ?></span><?php endif; ?>
            </a>
            <a href="profile.php" class="btn btn-secondary"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($currentBuyer['name']); ?></a>
            <?php endif; ?>
            
            <?php if (!isBuyer() && !isAdmin()): ?>
            <a href="buyer_login.php" class="btn btn-secondary"><i class="fas fa-sign-in-alt"></i> Login</a>
            <a href="register.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Register</a>
            <?php endif; ?>

            <?php if (isAdmin()): ?>
            <a href="admin_orders.php" class="btn btn-secondary" style="border: 1px solid var(--primary); color: #fff;"><i class="fas fa-clipboard-list"></i> Orders</a>
            <a href="admin_products.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Product</a>
            <?php endif; ?>

            <?php if (isBuyer() || isAdmin()): ?>
            <a href="logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
            <?php endif; ?>
        </div>
    </nav>

    <section class="hero">
        <h1>TVET SOKO</h1>
        <p>Skills • Build • Earn</p>
    </section>

    <div class="ad-container-wrapper">
        <div class="soko-ad-banner">
            <span class="ad-badge-label">Sponsored Notice</span>
            
            <div class="ad-text-content">
                Need premium custom items or tool engineering updates? The new <strong>Carpentry & Joinery</strong> catalogs are now 5% off this week! 
                <a href="products.php" style="color: var(--primary, #10b981); text-decoration: underline; margin-left: 0.5rem; font-weight: 600;">Explore Shop →</a>
            </div>
        </div>
    </div>

    <?php if (!empty($products)): ?>
    <section class="featured-slider-section">
        <p class="section-label"><i class="fas fa-star"></i> &nbsp;Featured Creations</p>
        
        <div class="slider-container" id="featuredSlider">
            <?php
            // Only grab the first 3 products for the featured slider
            $featuredProducts = array_slice($products, 0, 3);
            
            foreach ($featuredProducts as $index => $product) {
                $img   = $product['image_path']
                       ? 'uploads/' . htmlspecialchars($product['image_path'])
                       : 'https://via.placeholder.com/800x400?text=No+Image';
                $isNew = strtotime($product['created_at']) > strtotime('-7 days');
                $name  = htmlspecialchars($product['name']);
                $price = 'KES ' . number_format($product['price']);
                $link  = 'product_detail.php?id=' . $product['id'];
                
                $activeClass = ($index === 0) ? 'active' : '';

                echo '<a href="' . $link . '" class="slider-slide ' . $activeClass . '">';
                echo '<img src="' . $img . '" alt="' . $name . '" loading="lazy">';
                echo '<div class="slide-overlay">';
                echo '<h3 class="slide-name">' . $name . '</h3>';
                echo '<div class="slide-price">' . $price . '</div>';
                echo '</div>';
                if ($isNew) {
                    echo '<span class="badge-new-float">New</span>';
                }
                echo '</a>';
            }
            ?> 
        </div>
        <div class="stats-bar">
            <div class="stat-item"><div class="stat-value"><?php echo $stats['products']; ?></div><div class="stat-label">Products</div></div>
            <div class="stat-item"><div class="stat-value"><?php echo $stats['creators']; ?></div><div class="stat-label">Creators</div></div>
        </div>
        
        <div class="slider-dots" id="sliderDots">
            <?php for($i = 0; $i < count($featuredProducts); $i++): ?>
                <span class="dot <?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>"></span>
            <?php endfor; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="products-container">
        <h2 class="section-title"><i class="fas fa-fire"></i> All Products</h2>
        <div class="products-grid" id="productsGrid">
            <?php if (empty($products)): ?>
            <div class="empty-state" style="grid-column: 1 / -1;">
                <i class="fas fa-box-open"></i>
                <h3>No products yet</h3>
                <p>Be the first to add a student creation!</p>
            </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <div class="product-image-wrapper">
                        <a href="product_detail.php?id=<?php echo $product['id']; ?>">
                            <img src="<?php echo $product['image_path'] ? 'uploads/' . htmlspecialchars($product['image_path']) : 'https://via.placeholder.com/400x300?text=No+Image'; ?>"
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                        </a>
                    </div>
                    <div class="product-info">
                        <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:0.5rem;">
                            <span class="badge"><?php echo htmlspecialchars($product['category_name'] ?? 'Other'); ?></span>
                            <?php if (strtotime($product['created_at']) > strtotime('-7 days')): ?><span class="badge badge-new">NEW</span><?php endif; ?>
                        </div>
                        <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <div class="product-creator">
                            <i class="fas fa-user-graduate"></i> By
                            <a href="creator_profile.php?id=<?php echo $product['creator_id']; ?>" style="text-decoration: none; color: var(--primary, #10b981); font-weight: 500;">
                                <?php echo htmlspecialchars($product['creator_name']); ?>
                            </a>
                        </div>
                        <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                        <div class="product-footer">
                            <span class="product-price">KES <?php echo number_format($product['price']); ?></span>
                            <div class="product-actions">
                                <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="btn-icon" title="View Details"><i class="fas fa-eye"></i></a>
                                <a href="creator_profile.php?id=<?php echo $product['creator_id']; ?>" class="btn-icon" title="View Creator Profile" style="background:var(--secondary); border-color:var(--secondary);">
                                    <i class="fas fa-user-graduate"></i>
                                </a>
                                <?php if (isBuyer()): ?>
                                <form action="cart_add.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="submit" class="btn-icon" style="background:var(--primary); border-color:var(--primary);" title="Add to Cart"><i class="fas fa-cart-plus"></i></button>
                                </form>
                                <?php else: ?>
                                <a href="buyer_login.php" class="btn-icon" style="background:var(--primary); border-color:var(--primary);" title="Login to Add to Cart">
                                    <i class="fas fa-cart-plus"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <footer class="site-footer">
        <p>&copy; <?php echo date('Y'); ?> TvetSoko. All rights reserved.</p>
        <p>Designed by <span class="designer-name">ikwaacre@tives</span></p>
    </footer>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        function createBackgroundAnimation() {
            const container = document.getElementById('bgAnimation');
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

        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast     = document.createElement('div');
            toast.className = `toast ${type}`;
            const icons = { success: 'check-circle', error: 'exclamation-circle', info: 'info-circle' };
            toast.innerHTML = `<i class="fas fa-${icons[type]}"></i><span>${message}</span>`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity   = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        <?php if (isset($_SESSION['toast'])): ?>
            showToast("<?php echo htmlspecialchars($_SESSION['toast']['message']); ?>", "<?php echo htmlspecialchars($_SESSION['toast']['type']); ?>");
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        document.addEventListener('DOMContentLoaded', () => {
            const slides = document.querySelectorAll('.slider-slide');
            const dots = document.querySelectorAll('.slider-dots .dot');
            
            if (slides.length <= 1) return;

            let currentSlide = 0;
            const slideInterval = 6000;

            function goToSlide(index) {
                slides[currentSlide].classList.remove('active');
                if(dots[currentSlide]) dots[currentSlide].classList.remove('active');

                currentSlide = index;

                slides[currentSlide].classList.add('active');
                if(dots[currentSlide]) dots[currentSlide].classList.add('active');
            }

            function nextSlide() {
                const nextIndex = (currentSlide + 1) % slides.length;
                goToSlide(nextIndex);
            }

            let sliderTimer = setInterval(nextSlide, slideInterval);

            dots.forEach(dot => {
                dot.addEventListener('click', () => {
                    clearInterval(sliderTimer);
                    const index = parseInt(dot.getAttribute('data-index'));
                    goToSlide(index);
                    sliderTimer = setInterval(nextSlide, slideInterval);
                });
            });
        });

        // ── Updated Secret Admin Entrance Hotkey Handler ──
        document.addEventListener('keydown', function(event) {
            // Check if a buyer is currently logged in using the helper function state
            const isBuyerLoggedIn = <?php echo isBuyer() ? 'true' : 'false'; ?>;

            if (event.ctrlKey && event.shiftKey && event.key.toLowerCase() === 'a') {
                // If a buyer session is active, abort execution completely
                if (isBuyerLoggedIn) {
                    return;
                }
                
                event.preventDefault();
                window.location.href = 'admin_login.php';
            }
        });
    </script>
</body>
</html>