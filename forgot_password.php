<?php
require_once 'config.php';

// Initialize tracking variables
$stage = 1; 
$verifiedEmail = '';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // STAGE 1: Verify Email Address
    if (isset($_POST['action']) && $_POST['action'] === 'verify_email') {
        $email = trim($_POST['email']);
        
        $stmt = $pdo->prepare("SELECT email FROM buyers WHERE email = ?");
        $stmt->execute([$email]);
        $buyer = $stmt->fetch();
        
        if ($buyer) {
            $stage = 2;
            $verifiedEmail = $buyer['email'];
            setToast('Email verified! Please enter your new password.', 'info');
        } else {
            setToast('No account found with that email address.', 'error');
        }
    }
    
    // STAGE 2: Update Password in Database
    if (isset($_POST['action']) && $_POST['action'] === 'reset_password') {
        $email = trim($_POST['verified_email']);
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if ($newPassword !== $confirmPassword) {
            $stage = 2; // Retain stage 2 state on validation failure
            $verifiedEmail = $email;
            setToast('Passwords do not match!', 'error');
        } else {
            // Securely hash the incoming raw string password
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE buyers SET password_hash = ? WHERE email = ?");
            $updated = $stmt->execute([$newHash, $email]);
            
            if ($updated) {
                setToast('Password reset successfully! You can now login.', 'success');
                header('Location: buyer_login.php');
                exit;
            } else {
                setToast('An error occurred. Please try again.', 'error');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - TvetSoko</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Reusing your established storefront login aesthetics */
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
            line-height: 1.4;
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
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-icon"><i class="fas fa-key"></i></div>
        
        <?php if ($stage === 1): ?>
            <!-- STAGE 1: Request Header & Form -->
            <h2>Account Recovery</h2>
            <p>Enter your registered email below to verify your account credentials.</p>

            <form method="POST" action="">
                <input type="hidden" name="action" value="verify_email">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Account Email</label>
                    <input type="email" name="email" required placeholder="student@example.com" autofocus>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-shield-alt"></i> Verify Account
                </button>
            </form>
        <?php else: ?>
            <!-- STAGE 2: Reset Header & Form -->
            <h2>Set New Password</h2>
            <p>Account Verified: <strong style="color: var(--primary);"><?php echo htmlspecialchars($verifiedEmail); ?></strong></p>

            <form method="POST" action="">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="verified_email" value="<?php echo htmlspecialchars($verifiedEmail); ?>">
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> New Password</label>
                    <input type="password" name="new_password" required placeholder="Minimum 6 characters" autofocus>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-check-double"></i> Confirm New Password</label>
                    <input type="password" name="confirm_password" required placeholder="Retype new password">
                </div>
                <button type="submit" class="btn btn-primary" style="background: var(--success); border-color: var(--success);">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        <?php endif; ?>

        <a href="buyer_login.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Return to Login Window
        </a>
    </div>

    <!-- Toast Engine Integration -->
    <div class="toast-container" id="toastContainer"></div>
    <script>
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
            showToast(<?php echo json_encode($_SESSION['toast']['message']); ?>, <?php echo json_encode($_SESSION['toast']['type']); ?>);
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>
    </script>
</body>
</html>