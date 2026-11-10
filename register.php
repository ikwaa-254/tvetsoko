<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']); // Added phone variable
    $password = $_POST['password'];

    // 1. Check if the email is already registered
    $checkStmt = $pdo->prepare("SELECT id FROM buyers WHERE email = ?");
    $checkStmt->execute([$email]);
    
    if ($checkStmt->fetch()) {
        // Email exists, trigger an error toast
        setToast('An account with this email already exists!', 'error');
    } else {
        // 2. Hash the password securely
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // 3. Insert the new user into the database (Updated to include phone)
        $insertStmt = $pdo->prepare("INSERT INTO buyers (name, email, phone, password_hash) VALUES (?, ?, ?, ?)");
        
        if ($insertStmt->execute([$name, $email, $phone, $hashedPassword])) {
            // Success! Send them to the login page
            setToast('Account created successfully! Please log in.', 'success');
            header('Location: buyer_login.php');
            exit;
        } else {
            setToast('Something went wrong. Please try again.', 'error');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - TvetSoko</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Rectified Floating Card Constraints */
        .login-container {
            background: var(--glass); 
            border: 1px solid var(--glass-border);
            border-radius: 24px; 
            padding: 3.5rem 2.5rem; 
            width: 90%; 
            max-width: 480px; /* Expanded space to easily balance 4 field inputs */
            backdrop-filter: blur(20px); 
            text-align: center; 
            margin: 4rem auto;
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

        /* Structured Form Group Alignments */
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

        /* Form Submissions Alignment Fix */
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
        <div class="login-icon"><i class="fas fa-user-plus"></i></div>
        <h2>Join TVET SOKO</h2>
        <p>Create your buyer account</p>

        <form method="POST" action="">
            <div class="form-group">
                <label><i class="fas fa-id-badge"></i> Full Name</label>
                <input type="text" name="name" required placeholder="John Doe" autofocus>
            </div>
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email</label>
                <input type="email" name="email" required placeholder="student@example.com">
            </div>
            <div class="form-group">
                <label><i class="fas fa-phone"></i> Phone Number</label>
                <input type="tel" name="phone" required placeholder="0712345678">
            </div>
            <div class="form-group">
                <label><i class="fas fa-key"></i> Password</label>
                <input type="password" name="password" required placeholder="Create a strong password" minlength="6">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-user-check"></i> Register
            </button>
        </form>

        <div class="login-link">
            Already have an account? <a href="buyer_login.php">Login here</a>
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