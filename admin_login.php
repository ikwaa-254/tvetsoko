<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, password_hash FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id'] = $admin['id'];
        setToast('Welcome back, Admin!', 'success');
        header('Location: admin_orders.php');
        exit;
    } else {
        setToast('Invalid admin credentials!', 'error');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - TvetSoko</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Flex Alignment for Centering Card Component */
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        
        /* Rectified Floating Card Constraints */
        .login-container {
            background: var(--glass); 
            border: 1px solid var(--glass-border);
            border-radius: 24px; 
            padding: 3.5rem 2.5rem; 
            width: 90%; 
            max-width: 480px; /* Expanded space to match buyer login card proportions */
            backdrop-filter: blur(20px); 
            text-align: center;
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

        .error-msg {
            background: rgba(239, 68, 68, 0.1); 
            border: 1px solid var(--danger);
            color: var(--danger); 
            padding: 0.75rem 1rem;
            border-radius: 8px; 
            margin-bottom: 1.5rem;
            font-size: 0.9rem; 
            display: flex; 
            align-items: center; 
            gap: 0.5rem;
        }
        .hint {
            margin-top: 1.5rem; 
            padding: 0.75rem;
            background: rgba(255,255,255,0.03); 
            border-radius: 8px;
            font-size: 0.85rem; 
            color: var(--gray);
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
        <div class="login-icon"><i class="fas fa-user-lock"></i></div>
        <h2><i class="fas fa-shield-alt"></i> Admin Access</h2>
        <p>Sign in to manage the marketplace</p>

        <form method="POST" action="">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Username</label>
                <input type="text" name="username" required placeholder="admin" autofocus>
            </div>
            <div class="form-group">
                <label><i class="fas fa-key"></i> Password</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>

        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Marketplace
        </a>
    </div>
</body>
</html>