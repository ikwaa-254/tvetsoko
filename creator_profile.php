<?php
require_once 'config.php';

// 1. Get the creator's ID from the URL (e.g., creator_profile.php?id=5)
$creator_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$creator_id) {
    // Redirect or show an error if no ID is provided
    header("Location: index.php");
    exit;
}

// 2. Fetch the creator's details
$stmtCreator = $pdo->prepare("SELECT * FROM creators WHERE id = ?");
$stmtCreator->execute([$creator_id]);
$creator = $stmtCreator->fetch();

if (!$creator) {
    die("Creator not found.");
}

// 3. Fetch all active products made by this specific creator
$stmtProducts = $pdo->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'active' AND p.creator_id = ?
    ORDER BY p.created_at DESC
");
$stmtProducts->execute([$creator_id]);
$creatorProducts = $stmtProducts->fetchAll();

// ... (Include your cart logic and current buyer checks here, same as index.php) ...
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($creator['name']); ?>'s Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #06e72c;
            --text-dark: #333;
            --text-muted: #666;
            --bg-light: #f9f9f9;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--bg-light);
        }

        /* Upgraded Profile Header */
        .profile-header {
            text-align: center;
            background: white;
            border-bottom: 1px solid rgba(128, 128, 128, 0.2);
            margin-bottom: 2rem;
            padding-bottom: 2rem;
        }

        /* New Banner Image */
        .profile-banner {
            width: 100%;
            height: 200px;
            background: linear-gradient(to right, rgba(6, 231, 44, 0.3), rgba(6, 231, 44, 0.8)); /* Placeholder banner */
            /* background-image: url('uploads/banner.jpg'); */
            background-size: cover;
            background-position: center;
        }

        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-top: -75px; /* Pulls avatar up into the banner */
            border: 5px solid white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .creator-name {
            margin: 10px 0 5px;
            color: var(--text-dark);
        }

        .badge {
            display: inline-block;
            background: rgba(6, 231, 44, 0.15);
            color: #05a01f;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .bio {
            max-width: 600px;
            margin: 0 auto 1.5rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* New Action & Social Buttons */
        .profile-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 1rem;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #05c225;
        }

        .btn-outline {
            border: 2px solid var(--text-muted);
            color: var(--text-muted);
        }

        /* Product Grid Styles */
        .products-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .section-title {
            margin-bottom: 1.5rem;
            color: var(--text-dark);
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            padding-bottom: 3rem;
        }

        /* Missing Product Card Layout */
        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #eee;
        }

        .product-info {
            padding: 15px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .product-category {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .product-title {
            font-size: 1.1rem;
            margin: 5px 0 10px;
            color: var(--text-dark);
        }

        .product-footer {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .product-price {
            font-weight: bold;
            font-size: 1.2rem;
            color: var(--primary);
        }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

    <section class="profile-header">
        <div class="profile-banner"></div>
        
        <img src="<?php echo !empty($creator['profile_image']) ? 'uploads/'.htmlspecialchars($creator['profile_image']) : 'default_avatar.png'; ?>" alt="Avatar" class="profile-avatar">
        
        <h1 class="creator-name"><?php echo htmlspecialchars($creator['name']); ?></h1>
        <p class="badge"><i class="fas fa-check-circle"></i> Verified Student Creator</p>
        
        <p class="bio">
            <?php echo !empty($creator['bio']) ? nl2br(htmlspecialchars($creator['bio'])) : 'This creator has not written a bio yet.'; ?>
        </p>

        <div class="profile-actions">
            <?php if (!empty($creator['email'])): ?>
                <a href="mailto:<?php echo htmlspecialchars($creator['email']); ?>" class="btn btn-primary"><i class="fas fa-envelope"></i> Contact Creator</a>
            <?php endif; ?>

            <?php if (!empty($creator['linkedin'])): ?>
                <a target="_blank" href="<?php echo htmlspecialchars($creator['linkedin']); ?>" class="btn btn-outline"><i class="fab fa-linkedin"></i> LinkedIn</a>
            <?php endif; ?>

            <?php if (!empty($creator['portfolio'])): ?>
                <a target="_blank" href="<?php echo htmlspecialchars($creator['portfolio']); ?>" class="btn btn-outline"><i class="fas fa-globe"></i> Portfolio</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="products-container">
        <h2 class="section-title"><i class="fas fa-briefcase"></i> Portfolio & Products (<?php echo count($creatorProducts); ?>)</h2>
        
        <div class="products-grid">
            <?php if (empty($creatorProducts)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open fa-3x" style="margin-bottom: 1rem;"></i>
                    <h3>No products yet</h3>
                    <p>This creator is currently working on new items!</p>
                </div>
            <?php else: ?>
                <?php foreach ($creatorProducts as $product): ?>
                    <div class="product-card">
                        <img src="<?php echo !empty($product['image']) ? 'uploads/'.htmlspecialchars($product['image']) : 'default_product.jpg'; ?>" alt="Product" class="product-image">
                        <div class="product-info">
                            <span class="product-category"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></span>
                            <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                            
                            <div class="product-footer">
                                <span class="product-price">$<?php echo number_format($product['price'], 2); ?></span>
                                
                                <a href="add_to_cart.php?id=<?php echo $product['id']; ?>" class="btn btn-primary" style="padding: 5px 15px; font-size: 0.9rem;">
                                    <i class="fas fa-cart-plus"></i> Add
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

</body>
</html>