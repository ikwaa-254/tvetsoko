<?php
require_once 'config.php';

// Check admin auth
if (!isAdmin()) {
    header('Location: admin_login.php');
    exit;
}

// Handle Clear All Completed Orders Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_completed') {
    try {
        $pdo->beginTransaction();
        
        // 1. Delete items belonging to completed orders first (Foreign key safety)
        $pdo->query("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE status = 'Completed')");
        
        // 2. Delete the completed orders
        $pdo->query("DELETE FROM orders WHERE status = 'Completed'");
        
        $pdo->commit();
        setToast('All completed orders have been cleared successfully!', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        setToast('Failed to clear completed orders: ' . $e->getMessage(), 'error');
    }
    header('Location: admin_orders.php');
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$_POST['status'], $_POST['order_id']]);
    setToast('Order status updated successfully!', 'success');
    header('Location: admin_orders.php');
    exit;
}

// Fetch all orders with buyer info and items
$stmt = $pdo->query("
    SELECT o.*, b.name as buyer_name, b.email as buyer_email, b.phone as buyer_phone
    FROM orders o
    LEFT JOIN buyers b ON o.buyer_id = b.id
    ORDER BY o.created_at DESC
");
$orders = $stmt->fetchAll();

// Fetch order items for each order
foreach ($orders as &$order) {
    $itemStmt = $pdo->prepare("
        SELECT * FROM order_items WHERE order_id = ?
    ");
    $itemStmt->execute([$order['id']]);
    $order['items'] = $itemStmt->fetchAll();
}
unset($order);

// Stats for admin dashboard
$stats = [
    'total_orders' => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Pending'")->fetchColumn(),
    'completed' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Completed'")->fetchColumn(),
    'total_revenue' => $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status = 'Completed'")->fetchColumn()
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Orders - TvetSoko</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .navbar {
            background: rgba(15, 23, 42, 0.85) !important;
            border-bottom: 1px solid rgba(128, 128, 128, 0.2); 
        }

        /* Top controls row styling */
        .controls-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            gap: 1rem;
        }

        .action-buttons-group {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        
        .btn-clear-action {
            background-color: #ef4444;
            color: #ffffff;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.2s ease;
        }
        .btn-clear-action:hover {
            background-color: #dc2626;
        }

        .btn-print-action {
            background-color: #0f172a;
            color: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }
        .btn-print-action:hover {
            background-color: #1e293b;
        }

        /* --- Compact Horizontal Stats Card Bar --- */
        .horizontal-stats-card {
            display: flex;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            align-items: center;
            justify-content: space-around;
            flex-wrap: wrap;
        }

        .stat-item {
            flex: 1;
            text-align: center;
            padding: 0.5rem 1rem;
            min-width: 150px;
        }

        .stat-item:not(:last-child) {
            border-right: 1px solid #e2e8f0;
        }

        .stat-item h3 {
            margin: 0 0 0.25rem 0;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
        }

        .stat-item .value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }

        /* --- Premium Accordion Card Theme --- */
        .accordion-item {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            margin-bottom: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: all 0.25s ease;
        }

        .accordion-item:hover {
            border-color: #94a3b8;
            box-shadow: 0 4px 12px -1px rgba(0, 0, 0, 0.1);
        }

        .accordion-header {
            padding: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            background: #f1f5f9;
            user-select: none;
            gap: 1rem;
        }

        .accordion-header:hover {
            background: #e2e8f0;
        }

        .accordion-meta {
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-grow: 1;
        }

        .accordion-title-group h3 {
            margin: 0;
            font-size: 1.1rem;
            color: #0f172a;
        }

        .accordion-title-group .date {
            margin: 0.25rem 0 0 0;
            font-size: 0.85rem;
            color: #475569;
        }

        .accordion-summary-details {
            display: flex;
            gap: 2rem;
            color: #334155;
            font-size: 0.95rem;
        }

        .accordion-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .chevron-icon {
            transition: transform 0.3s ease;
            color: #475569;
        }

        .accordion-item.active .chevron-icon {
            transform: rotate(180deg);
        }

        .accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out, padding 0.3s ease;
            background: #ffffff;
            border-top: 0 solid #cbd5e1;
        }

        .accordion-item.active .accordion-content {
            max-height: 2000px;
            border-top-width: 1px;
            padding: 1.5rem;
        }

        .status-form select {
            cursor: pointer;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background-color: white;
        }

        /* --- Print-Only Report Container Design --- */
        #printable-report-area {
            display: none;
        }

        /* --- CSS Print Engine Override Styles --- */
        @media print {
            body * {
                visibility: hidden;
            }
            #printable-report-area, #printable-report-area * {
                visibility: visible;
            }
            #printable-report-area {
                display: block !important;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                color: #000000;
                background: #ffffff;
            }
            .report-header {
                border-bottom: 2px solid #000000;
                padding-bottom: 1rem;
                margin-bottom: 2rem;
                display: flex;
                justify-content: space-between;
                align-items: flex-end;
            }
            .report-header h1 { margin: 0; font-size: 22pt; }
            .report-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 2rem;
            }
            .report-table th, .report-table td {
                border: 1px solid #cbd5e1;
                padding: 0.75rem;
                text-align: left;
                font-size: 10pt;
            }
            .report-table th { background-color: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .report-summary-box {
                float: right;
                width: 300px;
                border: 1px solid #000;
                padding: 1rem;
                background-color: #f8fafc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .report-summary-box div {
                display: flex;
                justify-content: space-between;
                margin-bottom: 0.5rem;
                font-size: 11pt;
            }
        }

        @media (max-width: 768px) {
            .horizontal-stats-card { flex-direction: column; padding: 1rem; }
            .stat-item { border-right: none !important; border-bottom: 1px solid #e2e8f0; width: 100%; padding: 1rem 0; }
            .stat-item:last-child { border-bottom: none; }
            .controls-row { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .action-buttons-group { width: 100%; flex-direction: column; align-items: stretch; }
            .btn-clear-action, .btn-print-action { justify-content: center; width: 100%; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="logo">
            <i class="fas fa-school"></i> TvetSoko Admin
        </a>
        <div class="nav-links">
            <a href="admin_orders.php" class="active">
                <i class="fas fa-clipboard-list"></i> Orders
            </a>
            <a href="admin_products.php">
                <i class="fas fa-box"></i> Products
            </a>
            <a href="index.php">
                <i class="fas fa-store"></i> Store
            </a>
            <a href="logout.php" class="btn btn-danger" style="padding: 0.5rem 1rem;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="controls-row">
            <div class="page-header" style="margin: 0;">
                <h1><i class="fas fa-clipboard-list"></i> All Orders</h1>
            </div>
            
            <div class="action-buttons-group">
                <button type="button" class="btn-print-action" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Revenue Report
                </button>

                <?php if ($stats['completed'] > 0): ?>
                <form method="POST" onsubmit="return confirm('Are you sure you want to permanently clear all completed orders from the database? This action cannot be undone.');">
                    <input type="hidden" name="action" value="clear_completed">
                    <button type="submit" class="btn-clear-action">
                        <i class="fas fa-trash-alt"></i> Clear Completed (<?php echo $stats['completed']; ?>)
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="horizontal-stats-card">
            <div class="stat-item">
                <h3>Total Orders</h3>
                <div class="value"><?php echo $stats['total_orders']; ?></div>
            </div>
            <div class="stat-item">
                <h3>Pending</h3>
                <div class="value" style="color: #f59e0b;">
                    <?php echo $stats['pending']; ?>
                </div>
            </div>
            <div class="stat-item">
                <h3>Completed</h3>
                <div class="value" style="color: #16a34a;">
                    <?php echo $stats['completed']; ?>
                </div>
            </div>
            <div class="stat-item">
                <h3>Revenue</h3>
                <div class="value" style="color: #16a34a;">KES <?php echo number_format($stats['total_revenue']); ?></div>
            </div>
        </div>

        <?php if (empty($orders)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>No orders yet</h3>
            <p>Buyer orders will appear here once they are placed!</p>
        </div>
        <?php else: ?>
            <div class="accordion-container">
                <?php foreach ($orders as $order): ?>
                <div class="accordion-item" id="order-row-<?php echo $order['id']; ?>">
                    
                    <div class="accordion-header" onclick="toggleAccordion(this)">
                        <div class="accordion-meta">
                            <div class="accordion-title-group">
                                <h3><i class="fas fa-receipt"></i> <?php echo htmlspecialchars($order['order_code']); ?></h3>
                                <p class="date"><i class="fas fa-calendar"></i> <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></p>
                            </div>
                            <div class="accordion-summary-details">
                                <span><strong>Buyer:</strong> <?php echo htmlspecialchars($order['buyer_name']); ?></span>
                                <span><strong>Total:</strong> KES <?php echo number_format($order['total']); ?></span>
                            </div>
                        </div>
                        
                        <div class="accordion-controls" onclick="event.stopPropagation();">
                            <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                <?php echo $order['status']; ?>
                            </span>
                            <form method="POST" class="status-form">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <select name="status" onchange="this.form.submit()">
                                    <option value="Pending" <?php echo $order['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Processing" <?php echo $order['status'] === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="Shipped" <?php echo $order['status'] === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                                    <option value="Completed" <?php echo $order['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="Cancelled" <?php echo $order['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </form>
                            <i class="fas fa-chevron-down chevron-icon"></i>
                        </div>
                    </div>

                    <div class="accordion-content">
                        <div class="buyer-info" style="margin-top: 0;">
                            <h4><i class="fas fa-user"></i> Detailed Buyer Information</h4>
                            <div class="buyer-grid">
                                <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($order['buyer_name']); ?></p>
                                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($order['buyer_email']); ?></p>
                                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($order['buyer_phone']); ?></p>
                                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($order['delivery_address']); ?></p>
                            </div>
                            <?php if (!empty($order['notes'])): ?>
                            <div class="notes-box">
                                <i class="fas fa-sticky-note"></i> <strong>Notes:</strong> <?php echo htmlspecialchars($order['notes']); ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="items-list">
                            <h4><i class="fas fa-box-open"></i> Order Items</h4>
                            <?php foreach ($order['items'] as $item): ?>
                            <div class="item-row">
                                <img src="<?php echo $item['product_image'] ? 'uploads/' . htmlspecialchars($item['product_image']) : 'https://via.placeholder.com/60?text=No+Image'; ?>" 
                                     alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                <div class="item-details">
                                    <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                    <p>
                                        <?php echo $item['quantity']; ?> x KES <?php echo number_format($item['price']); ?>
                                        <span style="color: var(--primary);">(By <?php echo htmlspecialchars($item['creator_name']); ?>)</span>
                                    </p>
                                </div>
                                <div class="item-price">
                                    KES <?php echo number_format($item['total']); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="order-footer">
                            <div class="order-totals">
                                <span>Subtotal: <strong>KES <?php echo number_format($order['subtotal']); ?></strong></span>
                                <span>|</span>
                                <span>Shipping: <strong><?php echo $order['shipping'] == 0 ? 'FREE' : 'KES ' . number_format($order['shipping']); ?></strong></span>
                            </div>
                            <div class="grand-total">
                                Total: KES <?php echo number_format($order['total']); ?>
                            </div>
                        </div>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="printable-report-area">
        <div class="report-header">
            <div>
                <h1>TvetSoko - Completed Revenue Report</h1>
                <p>Generated on: <?php echo date('F j, Y g:i A'); ?></p>
            </div>
            <div style="text-align: right;">
                <p><strong>Status Filter:</strong> Completed Only</p>
            </div>
        </div>

        <table class="report-table">
            <thead>
                <tr>
                    <th>Date Ordered</th>
                    <th>Order Code</th>
                    <th>Buyer Name</th>
                    <th>Delivery Location</th>
                    <th>Items Purchased</th>
                    <th>Amount Paid</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $hasCompleted = false;
                foreach ($orders as $order): 
                    if ($order['status'] !== 'Completed') continue;
                    $hasCompleted = true;
                    
                    // Format item names into a single clean string block
                    $itemNames = [];
                    foreach ($order['items'] as $item) {
                        $itemNames[] = htmlspecialchars($item['product_name']) . " (x" . $item['quantity'] . ")";
                    }
                ?>
                <tr>
                    <td><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></td>
                    <td><strong><?php echo htmlspecialchars($order['order_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($order['buyer_name']); ?></td>
                    <td><?php echo htmlspecialchars($order['delivery_address']); ?></td>
                    <td><?php echo implode(', ', $itemNames); ?></td>
                    <td>KES <?php echo number_format($order['total']); ?></td>
                </tr>
                <?php endforeach; if (!$hasCompleted): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: #64748b;">No completed transactions found in current registry database.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="report-summary-box">
            <div>
                <span>Total Completed Orders:</span>
                <strong><?php echo $stats['completed']; ?></strong>
            </div>
            <hr style="border: 0; border-top: 1px solid #cbd5e1; margin: 0.5rem 0;">
            <div style="font-size: 13pt; font-weight: bold; color: #16a34a;">
                <span>Total Revenue:</span>
                <span>KES <?php echo number_format($stats['total_revenue']); ?></span>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    
    <script>
        function toggleAccordion(headerElement) {
            const accordionItem = headerElement.parentElement;
            accordionItem.classList.toggle('active');
        }

        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            if(!container) return;
            
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            const icons = { success: 'check-circle', error: 'exclamation-circle', info: 'info-circle' };
            toast.innerHTML = `<i class="fas fa-${icons[type] || 'info-circle'}"></i><span>${message}</span>`;
            container.appendChild(toast);
            
            setTimeout(() => { 
                toast.style.opacity = '0'; 
                toast.style.transform = 'translateX(100%)'; 
                setTimeout(() => toast.remove(), 300); 
            }, 3000);
        }

        <?php if (isset($_SESSION['toast'])): ?>
            showToast("<?php echo htmlspecialchars($_SESSION['toast']['message']); ?>", "<?php echo htmlspecialchars($_SESSION['toast']['type']); ?>");
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>
    </script>
</body>
</html>