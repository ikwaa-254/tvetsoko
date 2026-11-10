<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $address = trim($_POST['address']);

    // Validate
    if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($address)) {
        setToast('All fields are required!', 'error');
        header('Location: index.php');
        exit;
    }

    // Check if email exists
    $check = $pdo->prepare("SELECT id FROM buyers WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        setToast('Email already registered! Please login.', 'error');
        header('Location: index.php');
        exit;
    }

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    // Insert buyer
    $stmt = $pdo->prepare("INSERT INTO buyers (name, email, phone, password_hash, address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, $phone, $passwordHash, $address]);

    $buyerId = $pdo->lastInsertId();

    // Auto login
    $_SESSION['buyer_id'] = $buyerId;

    setToast('Account created successfully! Welcome to TvetSoko.', 'success');
    header('Location: index.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student signup - TvetSoko</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
        .login-container {
            background: var(--glass); border: 1px solid var(--glass-border);
            border-radius: 24px; padding: 3rem; width: 90%; max-width: 400px;
            backdrop-filter: blur(20px); text-align: center;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        }
        .login-icon {
            font-size: 4rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        .error-msg {
            background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger);
            color: var(--danger); padding: 0.75rem 1rem;
            border-radius: 8px; margin-bottom: 1.5rem;
            font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;
        }
        .hint {
            margin-top: 1.5rem; padding: 0.75rem;
            background: rgba(255,255,255,0.03); border-radius: 8px;
            font-size: 0.85rem; color: var(--gray);
        }
        .back-link {
            display: inline-block; margin-top: 1.5rem;
            color: var(--gray); text-decoration: none;
            transition: all 0.3s ease;
        }
        .back-link:hover { color: var(--primary); }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-icon"><i class="fas fa-user-lock"></i></div>
        <h2><i class="fas fa-shield-alt"></i> Create Account</h2>
    

        <form method="POST" action="">
            <div class="form-group">
                 <label><i class="fas fa-user"></i> name</label>
                  <input type="text" name="username" required placeholder="first name">
                <label><i class="fas fa-email"></i> email</label>
                <input type="email" name="email" required placeholder="@sth.com" autofocus>
                 <label><i class="fas fa-phone"></i> phone</label>
                 <input type="tel" name="phone" required placeholder="phone">
                  <label><i class="fas fa-address"></i> address</label>
                   <input type="text" name="address" required placeholder="address">
            </div>
            <div class="form-group">
                <label><i class="fas fa-key"></i> Password</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> signup
            </button>
        </form>


        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Marketplace
        </a>
    </div>
</body>
</html>