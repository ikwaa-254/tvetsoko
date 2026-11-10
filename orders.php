<?php
require_once 'config.php';

if (!isBuyer()) {
    header('Location: index.php');
    exit;
}

$buyerId = $_SESSION['buyer_id'];

// --- Secure Order History Deletion Processing ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'hide_order') {
    $orderIdToHide = (int)$_POST['order_id'];
    
    // Validate ownership before updating: order must belong to the active logged-in buyer
    $checkStmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND buyer_id = ?");
    $checkStmt->execute([$orderIdToHide, $buyerId]);
    
    if ($checkStmt->fetch()) {
        // Soft delete: set buyer_visible flag to 0 so it disappears from their history view
        $updateStmt = $pdo->prepare("UPDATE orders SET buyer_visible = 0 WHERE id = ?");
        $updateStmt->execute([$orderIdToHide]);
        setToast('Order successfully removed from your history view.', 'success');
    } else {
        setToast('Unauthorized delete request or invalid transaction target.', 'error');
    }
    
    header('Location: orders.php');
    exit;
}

// Fetch buyer's orders (Added buyer_visible filter condition check)
$stmt = $pdo->prepare("
    SELECT * FROM orders 
    WHERE buyer_id = ? AND buyer_visible = 1 
    ORDER BY created_at DESC
");
$stmt->execute([$buyerId]);
$orders = $stmt->fetchAll();

// Fetch items for each order
foreach ($orders as &$order) {
    $itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $itemStmt->execute([$order['id']]);
    $order['items'] = $itemStmt->fetchAll();
}
unset($order);

$currentBuyer = getCurrentBuyer($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - TVETSOKO</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .container { 
            max-width: 1000px; 
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

        .order-card {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            margin-bottom: 2rem;
            overflow: hidden;
            backdrop-filter: blur(12px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.03);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .order-header h3 {
            color: #f8fafc;
            font-size: 1.2rem;
            margin-bottom: 0.25rem;
        }
        .order-header .date {
            font-size: 0.85rem;
            color: #94a3b8;
        }

        .status-badge {
            padding: 0.4rem 1rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .status-pending { background: rgba(245, 158, 11, 0.2); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
        .status-completed { background: rgba(34, 197, 94, 0.2); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); }
        .status-cancelled { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }

        .items-list {
            padding: 1.5rem;
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            gap: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .item-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 0.75rem 1.2rem;
            border-radius: 12px;
            flex: 1;
            min-width: 260px; 
            max-width: 100%;
        }
        .item-row img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 8px;
            background: #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .item-details {
            flex-grow: 1;
        }
        .item-details h4 {
            font-size: 1rem;
            color: #f8fafc;
            margin-bottom: 0.25rem;
        }
        .item-details p {
            font-size: 0.85rem;
            color: #94a3b8;
        }
        .item-price {
            font-weight: 700;
            color: var(--primary, #10b981);
            font-size: 0.95rem;
            white-space: nowrap;
        }

        .order-footer {
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            background: rgba(0, 0, 0, 0.1);
        }
        .delivery-info p {
            font-size: 0.9rem;
            color: #94a3b8;
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .delivery-info p:last-child { margin-bottom: 0; }
        .delivery-info i { color: var(--primary, #10b981); width: 16px; }
        
        .footer-financials {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.75rem;
        }
        .grand-total {
            font-size: 1.5rem;
            font-weight: 800;
            color: #fff;
        }
        
        .btn-delete-history {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #ef4444;
            padding: 0.45rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: all 0.2s ease;
        }
        .btn-delete-history:hover {
            background: #ef4444;
            color: #ffffff;
            border-color: #ef4444;
        }

        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            color: #94a3b8;
        }
        .empty-state i { font-size: 4rem; color: rgba(255, 255, 255, 0.1); margin-bottom: 1.5rem; }
        .empty-state h3 { color: #f8fafc; margin-bottom: 0.5rem; }
    </style>
</head>
<body>
    <div class="bg-animation" id="bgAnimation"></div>

    <nav class="navbar">
        <a href="index.php" class="logo"><i class="fas fa-school"></i>TVETSOKO</a>
        <div class="nav-links">
            <a href="index.php"><i class="fas fa-home"></i> Home</a>
            <a href="products.php"><i class="fas fa-shopping-bag"></i> Shop</a>
            <a href="orders.php"><i class="fas fa-box"></i> My Orders</a>
            <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
        </div>
    </nav>

    <div class="container">
        <h2 class="section-title"><i class="fas fa-box"></i> My Orders</h2>

        <?php if (empty($orders)): ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-list"></i>
            <h3>No orders yet</h3>
            <p>Your order history will appear here once you make a purchase!</p>
        </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
            <div class="order-card">
                <div class="order-header">
                    <div>
                        <h3><i class="fas fa-receipt"></i> <?php echo htmlspecialchars($order['order_code']); ?></h3>
                        <p class="date">
                            <i class="fas fa-calendar"></i> 
                            <?php echo date('F j, Y', strtotime($order['created_at'])); ?>
                        </p>
                    </div>
                    <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                        <?php echo $order['status']; ?>
                    </span>
                </div>

                <div class="items-list">
                    <?php foreach ($order['items'] as $item): ?>
                    <div class="item-row">
                        <img src="<?php echo $item['product_image'] ? 'uploads/' . htmlspecialchars($item['product_image']) : 'https://via.placeholder.com/60?text=No+Image'; ?>" 
                             alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                        <div class="item-details">
                            <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                            <p><?php echo $item['quantity']; ?> x KES <?php echo number_format($item['price']); ?></p>
                        </div>
                        <div class="item-price">
                            KES <?php echo number_format($item['total']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="order-footer">
                    <div class="delivery-info">
                        <p><i class="fas fa-truck"></i> <?php echo htmlspecialchars($order['delivery_address']); ?></p>
                        <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($order['buyer_phone']); ?></p>
                        <?php if ($order['notes']): ?>
                        <p><i class="fas fa-sticky-note"></i> <?php echo htmlspecialchars($order['notes']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="footer-financials">
                        <div class="grand-total">
                            KES <?php echo number_format($order['total']); ?>
                        </div>
                        <!-- Secure POST-based Remove Button Entry -->
                        <form method="POST" action="" onsubmit="return confirm('Remove this transaction record from your history view? This will not affect active delivery fulfillments.');">
                            <input type="hidden" name="action" value="hide_order">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <button type="submit" class="btn-delete-history">
                                <i class="fas fa-trash-alt"></i> Remove from History
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        // ── Background Animation Generator ──
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

        // Toast utility listener matching previous system updates
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            if(!container) return;
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            const icons = { success: 'check-circle', error: 'exclamation-circle', info: 'info-circle' };
            toast.innerHTML = `<i class="fas fa-${icons[type] || 'info-circle'}"></i><span>${message}</span>`;
            container.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; setTimeout(() => toast.remove(), 300); }, 3000);
        }

        <?php if (isset($_SESSION['toast'])): ?>
            showToast("<?php echo htmlspecialchars($_SESSION['toast']['message']); ?>", "<?php echo htmlspecialchars($_SESSION['toast']['type']); ?>");
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>
    </script>
</body>
</html>