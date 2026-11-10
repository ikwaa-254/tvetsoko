<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, name, email, phone, address, password_hash FROM buyers WHERE email = ?");
    $stmt->execute([$email]);
    $buyer = $stmt->fetch();

    if ($buyer && password_verify($password, $buyer['password_hash'])) {
        $_SESSION['buyer_id'] = $buyer['id'];
        setToast('Welcome back, ' . $buyer['name'] . '!', 'success');
        header('Location: index.php');
        exit;
    } else {
        setToast('Invalid email or password!', 'error');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - TvetSoko</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .login-container {
            background: var(--glass); 
            border: 1px solid var(--glass-border);
            border-radius: 24px; 
            padding: 3.5rem 2.5rem; 
            width: 90%; 
            max-width: 480px; 
            backdrop-filter: blur(20px); 
            text-align: center; 
            margin: 6rem auto;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        }
        .login-icon {
            font-size: 3.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        .login-container h2 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            color: #fff;
        }
        .login-container p {
            color: var(--gray);
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }
        .form-group {
            text-align: left;
            margin-bottom: 1.5rem;
            width: 100%;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: #f8fafc;
        }
        .form-group input {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.2s ease;
        }
        .form-group input:focus {
            border-color: var(--primary);
        }
        
        /* Flex alignment wrapper for input headers */
        .input-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .forgot-link {
            font-size: 0.85rem;
            color: var(--primary);
            text-decoration: none;
        }
        .forgot-link:hover {
            text-decoration: underline;
        }

        .login-container .btn-primary {
            width: 100%;
            padding: 0.85rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 0.5rem;
        }
        .back-link {
            display: inline-block; 
            margin-top: 1.5rem;
            color: var(--gray); 
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        .back-link:hover { color: var(--primary); }
        .login-link { margin-top: 1.5rem; font-size: 0.95rem; color: var(--gray); }
        .login-link a { color: var(--primary); text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-icon"><i class="fas fa-user-lock"></i></div>
        <h2><i class="fas fa-shield-alt"></i> Buyer Access</h2>
        <p>Login from here</p>

        <form method="POST" action="">
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email</label>
                <input type="email" name="email" required placeholder="student@example.com" autofocus>
            </div>
            <div class="form-group">
                <div class="input-header-row">
                    <label style="margin: 0;"><i class="fas fa-key"></i> Password</label>
                    <!-- Beautiful, contextual forgot password trigger -->
                    <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
                </div>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>

        <div class="login-link">
            Don't have an account? <a href="register.php">Register here</a>
        </div>

        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Marketplace
        </a>
    </div>

    <div class="toast-container" id="toastContainer"></div>
    <script>
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            if(!container) return;
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            const icons = { success: 'check-circle', error: 'exclamation-circle', info: 'info-circle' };
            toast.innerHTML = `<i class="fas fa-${icons[type]}"></i><span>${message}</span>`;
            container.appendChild(toast);
            
            setTimeout(() => { 
                toast.style.opacity = '0'; 
                toast.style.transform = 'translateX(100%)'; 
                setTimeout(() => toast.remove(), 300); 
            }, 3000);
        }

        <?php if (isset($_SESSION['toast'])): ?>
            showToast(<?php echo json_encode($_SESSION['toast']['message']); ?>, <?php echo json_encode($_SESSION['toast']['type']); ?>);
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>
    </script>
</body>
</html>