<?php
require_once 'config.php';

if (!isBuyer()) {
    header('Location: index.php');
    exit;
}

$currentBuyer = getCurrentBuyer($pdo);

// Get order stats
$orderStmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total FROM orders WHERE buyer_id = ? AND status != 'Cancelled'");
$orderStmt->execute([$_SESSION['buyer_id']]);
$orderStats = $orderStmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - TvetSoko</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .container { 
            max-width: 800px; /* Increased from 600px to give content more horizontal breathing room */
            margin: 0 auto; 
            padding: 3rem 2rem; 
        }

        /* Profile Header Design */
        .profile-card-header {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2.5rem;
            text-align: center;
            backdrop-filter: blur(12px);
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        .profile-avatar {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, var(--primary, #10b981), var(--secondary, #0ea5e9));
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #fff;
            margin-bottom: 1.25rem;
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.2);
        }
        .profile-card-header h2 {
            font-size: 1.8rem;
            color: #f8fafc;
            margin-bottom: 0.25rem;
        }
        .profile-card-header p {
            color: #94a3b8;
            font-size: 1rem;
        }
        .profile-meta-phone {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #64748b;
        }

        /* Action Buttons Grid Layout */
        .profile-actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        .profile-actions-grid .btn {
            padding: 1rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .profile-actions-grid .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }

        /* Modernized Info Dashboard Cards */
        .info-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(12px);
        }
        .info-card h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #f8fafc;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 0.75rem;
        }
        .info-card h3 i {
            color: var(--primary, #10b981);
        }

        /* Horizontal Activity Summary Layout */
        .dashboard-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        .dashboard-stat-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.04);
            padding: 1.25rem;
            border-radius: 12px;
            text-align: center;
        }
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.25rem;
        }
        .stat-value.highlight {
            color: var(--primary, #10b981);
        }
        .stat-label {
            font-size: 0.85rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Clean Separated Table List Rows */
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .info-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .info-label {
            color: #94a3b8;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .info-label i {
            width: 20px;
            color: #64748b;
        }
        .info-value {
            color: #f8fafc;
            font-weight: 500;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <div class="bg-animation" id="bgAnimation"></div>

    <nav class="navbar">
        <a href="index.php" class="logo"><i class="fas fa-school"></i> TvetSoko</a>
        <div class="nav-links">
            <a href="index.php"><i class="fas fa-home"></i> Home</a>
            <a href="products.php"><i class="fas fa-shopping-bag"></i> Shop</a>
            <a href="orders.php"><i class="fas fa-box"></i> Orders</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <!-- Organized Header Block -->
        <div class="profile-header-card">
            <div class="profile-card-header">
                <div class="profile-avatar"><i class="fas fa-user"></i></div>
                <h2><?php echo htmlspecialchars($currentBuyer['name']); ?></h2>
                <p><?php echo htmlspecialchars($currentBuyer['email']); ?></p>
                <div class="profile-meta-phone">
                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($currentBuyer['phone']); ?>
                </div>
            </div>
        </div>

        <!-- Symmetrical Main Call-To-Action Layout Rows -->
        <div class="profile-actions-grid">
            <a href="orders.php" class="btn btn-primary"><i class="fas fa-box"></i> My Orders</a>
            <a href="cart.php" class="btn btn-secondary"><i class="fas fa-shopping-cart"></i> My Cart</a>
        </div>

        <!-- Horizontal Metric Grid Wrap Component -->
        <div class="info-card">
            <h3><i class="fas fa-chart-line"></i> Activity Summary</h3>
            <div class="dashboard-stats">
                <div class="dashboard-stat-box">
                    <div class="stat-value"><?php echo $orderStats['count']; ?></div>
                    <div class="stat-label">Orders Filled</div>
                </div>
                <div class="dashboard-stat-box">
                    <div class="stat-value highlight">KES <?php echo number_format($orderStats['total']); ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
            </div>
        </div>

        <!-- Account Data Summary List View Component -->
        <div class="info-card">
            <h3><i class="fas fa-info-circle"></i> Account Details</h3>
            <div class="info-row">
                <div class="info-label"><i class="fas fa-user"></i> Name</div>
                <div class="info-value"><?php echo htmlspecialchars($currentBuyer['name']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label"><i class="fas fa-envelope"></i> Email</div>
                <div class="info-value"><?php echo htmlspecialchars($currentBuyer['email']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label"><i class="fas fa-phone"></i> Phone</div>
                <div class="info-value"><?php echo htmlspecialchars($currentBuyer['phone']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label"><i class="fas fa-map-marker-alt"></i> Address</div>
                <div class="info-value"><?php echo htmlspecialchars($currentBuyer['address']); ?></div>
            </div>
        </div>
    </div>

    <script>
        // Background Particle Generator Initialization
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