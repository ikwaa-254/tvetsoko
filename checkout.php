<?php
require_once 'config.php';

// --- M-Pesa STK Push Helper Function ---
function triggerMpesaSTK($phone, $amount, $orderCode) {
    // Note: Update these credentials when moving to live production
    $consumerKey = 'Q0CM0exXVrKv3vY3ud77B4yZRWFCrIYtJAImsMQz5IGAEiPm'; 
    $consumerSecret = 'Wh2cSsTpM8cBRnr86sA4d0GHT2GgdduDCekzSDWwh3VGb5YAnnGKwdPltPyYuKdk'; 
    $businessShortCode = '174379'; 
    $passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'; 
    $callbackUrl = 'https://yourdomain.com/mpesa_callback.php'; 
    
    $phone = preg_replace('/^0/', '254', $phone);
    $phone = preg_replace('/^\+/', '', $phone);

    $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
    $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    $curl_response = curl_exec($curl);
    $token_data = json_decode($curl_response);
    
    if (!isset($token_data->access_token)) {
        return false; 
    }
    $accessToken = $token_data->access_token;

    $timestamp = date('YmdHis');
    $password = base64_encode($businessShortCode . $passkey . $timestamp);
    $stkUrl = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    
    $curl_post_data = array(
        'BusinessShortCode' => $businessShortCode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => round($amount),
        'PartyA' => $phone,
        'PartyB' => $businessShortCode,
        'PhoneNumber' => $phone,
        'CallBackURL' => $callbackUrl,
        'AccountReference' => $orderCode,
        'TransactionDesc' => 'Payment for Order ' . $orderCode
    );

    $data_string = json_encode($curl_post_data);
    
    curl_setopt($curl, CURLOPT_URL, $stkUrl);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json','Authorization:Bearer ' . $accessToken));
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
    $response = curl_exec($curl);
    curl_close($curl);
    
    return json_decode($response, true);
}
// --- End M-Pesa Helper ---

if (!isBuyer()) {
    header('Location: index.php');
    exit;
}

$buyerId = $_SESSION['buyer_id'];

// 1. Determine if this is a Direct Buy or a Cart Checkout
$isDirectBuy = false;
$checkoutItems = [];

// Check if direct_buy_id is passed from the product page OR the checkout form submission
if (isset($_POST['direct_buy_id'])) {
    $isDirectBuy = true;
    $productId = (int)$_POST['direct_buy_id'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

    // Fetch the specific product directly
    $stmt = $pdo->prepare("SELECT id as product_id, name, price, image_path, creator_name FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if ($product) {
        // Format it to match the structure of cart items
        $product['quantity'] = $quantity;
        $checkoutItems[] = $product;
    } else {
        setToast('Product no longer available.', 'error');
        header('Location: products.php');
        exit;
    }
} else {
    // Standard Cart Checkout: Fetch cart items
    $stmt = $pdo->prepare("
        SELECT c.quantity, p.id as product_id, p.name, p.price, p.image_path, p.creator_name
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.buyer_id = ?
    ");
    $stmt->execute([$buyerId]);
    $checkoutItems = $stmt->fetchAll();

    if (empty($checkoutItems)) {
        setToast('Your cart is empty!', 'error');
        header('Location: cart.php');
        exit;
    }
}

// 2. Calculate Totals
$subtotal = 0;
foreach ($checkoutItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$shipping = $subtotal > 1000 ? 0 : 150;
$total = $subtotal + $shipping;

$currentBuyer = getCurrentBuyer($pdo);

// 3. Process Checkout (Only run if the user submitted the final payment form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['phone'])) {
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $notes = trim($_POST['notes'] ?? '');
    $orderCode = 'ORD-' . strtoupper(substr(uniqid(), -8));

    try {
        $pdo->beginTransaction();

        // Insert order
        $orderStmt = $pdo->prepare("
            INSERT INTO orders (order_code, buyer_id, buyer_name, buyer_email, buyer_phone, delivery_address, notes, subtotal, shipping, total, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending Payment')
        ");
        $orderStmt->execute([
            $orderCode, $buyerId, $currentBuyer['name'], $currentBuyer['email'],
            $phone, $address, $notes, $subtotal, $shipping, $total
        ]);
        $orderId = $pdo->lastInsertId();

        // Insert order items
        $itemStmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, product_name, product_image, creator_name, price, quantity, total)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($checkoutItems as $item) {
            $itemStmt->execute([
                $orderId, $item['product_id'], $item['name'], $item['image_path'],
                $item['creator_name'], $item['price'], $item['quantity'],
                $item['price'] * $item['quantity']
            ]);
        }

        // Only clear the cart if this was a normal cart checkout
        if (!$isDirectBuy) {
            $clearStmt = $pdo->prepare("DELETE FROM cart WHERE buyer_id = ?");
            $clearStmt->execute([$buyerId]);
        }

        $pdo->commit();

        // Trigger M-Pesa STK Push
        $mpesaResponse = triggerMpesaSTK($phone, $total, $orderCode);

        if (isset($mpesaResponse['ResponseCode']) && $mpesaResponse['ResponseCode'] == '0') {
            setToast('Order placed! Please check your phone to enter your M-Pesa PIN.', 'success');
        } else {
            setToast('Order saved, but we could not send an M-Pesa prompt. Please pay manually via Paybill.', 'warning');
        }

        header('Location: orders.php');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        setToast('Error placing order. Please try again.', 'error');
        header('Location: products.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - TVETSOKO</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .container { 
            max-width: 800px; /* Increased width to un-cluster the form inputs */
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

        /* Modernized Checkout Summary Card */
        .cart-summary {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2.5rem;
            backdrop-filter: blur(12px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        .cart-summary h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #f8fafc;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 0.75rem;
        }
        .cart-summary h3 i {
            color: var(--primary, #10b981);
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

        /* Form Group Adjustments */
        .form-group {
            text-align: left;
            margin-bottom: 1.75rem;
            width: 100%;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: #f8fafc;
            font-weight: 500;
        }
        .form-group input, 
        .form-group textarea {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.2s ease;
            font-family: inherit;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--primary, #10b981);
        }

        /* Interactive Footer Actions with Absolute Visibility */
        .checkout-actions {
            display: flex;
            gap: 1.5rem;
            margin-top: 2rem;
        }
        .checkout-actions .btn {
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
            color: #ffffff !important; /* Pristine high contrast typography */
            cursor: pointer;
        }
        .checkout-actions .btn-success {
            background-color: var(--primary, #10b981);
            border: 1px solid var(--primary, #10b981);
        }
        .checkout-actions .btn-success:hover {
            background-color: var(--primary-dark, #059669);
            border-color: var(--primary-dark, #059669);
            transform: translateY(-2px);
        }
        .checkout-actions .btn-secondary {
            background-color: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .checkout-actions .btn-secondary:hover {
            background-color: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="bg-animation" id="bgAnimation"></div>

    <nav class="navbar">
        <a href="index.php" class="logo"><i class="fas fa-school"></i> TVETSOKO</a>
    </nav>

    <div class="container">
        <h2 class="section-title"><i class="fas fa-credit-card"></i> Checkout</h2>

        <div class="cart-summary">
            <h3><i class="fas fa-receipt"></i> Order Summary</h3>
            <?php foreach ($checkoutItems as $item): ?>
            <div class="summary-row">
                <span><?php echo htmlspecialchars($item['name']); ?> x<?php echo $item['quantity']; ?></span>
                <span>KES <?php echo number_format($item['price'] * $item['quantity']); ?></span>
            </div>
            <?php endforeach; ?>
            
            <div class="summary-row">
                <span>Shipping</span>
                <span><?php echo $shipping === 0 ? 'FREE' : 'KES ' . number_format($shipping); ?></span>
            </div>
            <div class="summary-row total">
                <span>Total</span>
                <span>KES <?php echo number_format($total); ?></span>
            </div>
        </div>

        <form method="POST">
            <?php if ($isDirectBuy): ?>
                <input type="hidden" name="direct_buy_id" value="<?php echo $checkoutItems[0]['product_id']; ?>">
                <input type="hidden" name="quantity" value="<?php echo $checkoutItems[0]['quantity']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label><i class="fas fa-phone"></i> Phone Number (M-Pesa)</label>
                <input type="tel" name="phone" required value="<?php echo htmlspecialchars($currentBuyer['phone'] ?? ''); ?>" placeholder="254712345678">
            </div>
            <div class="form-group">
                <label><i class="fas fa-map-marker-alt"></i> Delivery Address</label>
                <textarea name="address" required placeholder="Enter your full delivery address..."><?php echo htmlspecialchars($currentBuyer['address'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label><i class="fas fa-sticky-note"></i> Order Notes (Optional)</label>
                <textarea name="notes" placeholder="Any special instructions..."></textarea>
            </div>
            
            <div class="checkout-actions">
                <?php if ($isDirectBuy): ?>
                    <a href="product_detail.php?id=<?php echo $checkoutItems[0]['product_id']; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Product
                    </a>
                <?php else: ?>
                    <a href="cart.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Cart
                    </a>
                <?php endif; ?>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check"></i> Place Order
                </button>
            </div>
        </form>
    </div>

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
    </script>
</body>
</html>