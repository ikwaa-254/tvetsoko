<?php
require_once 'config.php';

if (!isAdmin()) {
    header('Location: admin_login.php');
    exit;
}

// Handle product add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $price = (float)$_POST['price'];
    $description = trim($_POST['description']);
    $categoryId = (int)$_POST['category_id'];
    $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    
    $creatorId = isset($_POST['creator_id']) ? (int)$_POST['creator_id'] : 0;
    $newCreatorName = trim($_POST['new_creator_name'] ?? '');
    $creatorName = '';

    if (!empty($newCreatorName)) {
        $creatorImagePath = '';
        if (!empty($_FILES['new_creator_image']['name'])) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $ext = pathinfo($_FILES['new_creator_image']['name'], PATHINFO_EXTENSION);
            $creatorImagePath = 'creator_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['new_creator_image']['tmp_name'], $uploadDir . $creatorImagePath);
        }

        $newBio = trim($_POST['new_creator_bio'] ?? '');
        $newContact_email = trim($_POST['new_creator_contact_email'] ?? '');
        $newLinkedin = trim($_POST['new_creator_linkedin'] ?? '');
        $newPortfolio = trim($_POST['new_creator_portfolio'] ?? '');

        $stmtNewCreator = $pdo->prepare("INSERT INTO creators (name, profile_image, bio, contact_email, linkedin, portfolio) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtNewCreator->execute([$newCreatorName, $creatorImagePath, $newBio, $newContact_email, $newLinkedin, $newPortfolio]);
        
        $creatorId = (int)$pdo->lastInsertId();
        $creatorName = $newCreatorName;
    } elseif ($creatorId > 0) {
        $cStmt = $pdo->prepare("SELECT name FROM creators WHERE id = ?");
        $cStmt->execute([$creatorId]);
        $creatorName = $cStmt->fetchColumn() ?: 'Unknown Creator';
    } else {
        $creatorId = 0;
        $creatorName = 'Unknown Creator';
    }

    $imagePath = '';
    if (!empty($_FILES['image']['name'])) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $imagePath = uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imagePath);
    }

    if ($productId > 0) {
        if ($imagePath) {
            $stmt = $pdo->prepare("UPDATE products SET name=?, creator_name=?, creator_id=?, price=?, description=?, category_id=?, image_path=? WHERE id=?");
            $stmt->execute([$name, $creatorName, $creatorId, $price, $description, $categoryId, $imagePath, $productId]);
        } else {
            $stmt = $pdo->prepare("UPDATE products SET name=?, creator_name=?, creator_id=?, price=?, description=?, category_id=? WHERE id=?");
            $stmt->execute([$name, $creatorName, $creatorId, $price, $description, $categoryId, $productId]);
        }
        setToast('Product updated successfully!', 'success');
    } else {
        $stmt = $pdo->prepare("INSERT INTO products (name, creator_name, creator_id, price, description, category_id, image_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $creatorName, $creatorId, $price, $description, $categoryId, $imagePath]);
        setToast('Product added successfully!', 'success');
    }
    header('Location: admin_products.php');
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
    setToast('Product deleted!', 'info');
    header('Location: admin_products.php');
    exit;
}

// Fetch all products
$stmt = $pdo->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY p.created_at DESC
");
$products = $stmt->fetchAll();

// Fetch categories
$catStmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $catStmt->fetchAll();

// Fetch creators
$creatorStmt = $pdo->query("SELECT id, name FROM creators ORDER BY name"); 
$creatorsList = $creatorStmt->fetchAll();

// Edit mode
$editProduct = null;
if (isset($_GET['edit'])) {
    $editStmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $editStmt->execute([(int)$_GET['edit']]);
    $editProduct = $editStmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Products - TVETSOKO</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .navbar {
            background: rgba(15, 23, 42, 0.85) !important;
            border-bottom: 1px solid rgba(128, 128, 128, 0.2); 
        }

        .dashboard-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .btn-launch-modal {
            background-color: #0f172a;
            color: white;
            border: 1px solid #cbd5e1;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }
        .btn-launch-modal:hover { background-color: #1e293b; }

        /* --- Modern Modal System CSS --- */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1rem;
        }
        .modal-overlay.active { display: flex; }

        .form-card {
            background: #f8fafc; 
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            padding: 2rem;
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15);
            position: relative;
        }

        .modal-close-btn {
            position: absolute;
            top: 1.25rem; right: 1.25rem;
            background: #e2e8f0; border: none;
            width: 32px; height: 32px; border-radius: 50%;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            color: #64748b; transition: all 0.2s ease;
        }
        .modal-close-btn:hover { background: #cbd5e1; color: #0f172a; }

        /* --- Fixed & Spacious Modal Button Group Layout --- */
        .modal-button-actions {
            display: flex;
            gap: 1.25rem;
            margin-top: 2rem;
            border-top: 1px solid #cbd5e1;
            padding-top: 1.5rem;
        }
        
        .modal-button-actions .btn {
            flex: 1;
            padding: 0.85rem 1.5rem !important;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
            height: 48px;
            transition: all 0.2s ease;
        }

        /* --- Upgraded Table Container Styling --- */
        .products-table-container {
            background: #f8fafc; 
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        .products-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        .products-table th, .products-table td {
            padding: 1.25rem;
            border-bottom: 1px solid #cbd5e1;
            vertical-align: top;
        }
        .products-table th {
            background-color: #e2e8f0; 
            color: #1e293b;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .products-table tr:last-child td { border-bottom: none; }
        .products-table tr:hover td { background-color: #f1f5f9; }

        .products-table img {
            width: 70px; height: 70px; 
            object-fit: cover; border-radius: 8px;
            border: 1px solid #cbd5e1;
        }

        .product-meta-block h3 { margin: 0 0 0.35rem 0; font-size: 1.1rem; color: #0f172a; }
        .product-meta-block .desc-snippet { font-size: 0.88rem; color: #64748b; margin: 0 0 0.5rem 0; line-height: 1.4; max-width: 500px; }
        .product-tags-line { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .inline-badge {
            background: #e2e8f0; color: #334155; font-size: 0.78rem; font-weight: 600;
            padding: 0.2rem 0.6rem; border-radius: 4px; display: inline-flex; align-items: center; gap: 0.3rem;
        }
        .inline-badge.price-tag { background: #dcfce7; color: #166534; font-size: 0.85rem; }

        .action-btns { display: flex; gap: 0.5rem; }
        .action-btns .btn {
            padding: 0.5rem; width: 36px; height: 36px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 6px;
        }

        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #334155; }
        .form-group input[type="text"], .form-group input[type="number"], .form-group input[type="email"], .form-group input[type="url"], .form-group select, .form-group textarea {
            width: 100%; padding: 0.65rem 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; font-size: 0.95rem;
        }
        .form-group textarea { resize: vertical; min-height: 80px; }

        @media (max-width: 480px) {
            .modal-button-actions {
                flex-direction: column;
                gap: 0.75rem;
            }
            .modal-button-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="logo"><i class="fas fa-school"></i> TVETSOKO Admin</a>
        <div class="nav-links">
            <a href="admin_orders.php"><i class="fas fa-clipboard-list"></i> Orders</a>
            <a href="admin_products.php" class="active"><i class="fas fa-box"></i> Products</a>
            <a href="index.php"><i class="fas fa-store"></i> Store</a>
            <a href="logout.php" class="btn btn-danger" style="padding: 0.5rem 1rem;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="dashboard-header-row">
            <div class="page-header" style="margin: 0;">
                <h1><i class="fas fa-box"></i> Product Management</h1>
            </div>
            <button class="btn-launch-modal" onclick="openProductModal()">
                <i class="fas fa-plus-circle"></i> Add New Product
            </button>
        </div>

        <div class="modal-overlay" id="productFormModal">
            <div class="form-card">
                <button class="modal-close-btn" onclick="closeProductModal()"><i class="fas fa-times"></i></button>
                <h2 style="margin-bottom:1.5rem; color: #0f172a;">
                    <i class="fas fa-<?php echo $editProduct ? 'edit' : 'plus-circle'; ?>"></i> 
                    <?php echo $editProduct ? 'Edit Product' : 'Add New Product'; ?>
                </h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <?php if ($editProduct): ?>
                    <input type="hidden" name="product_id" value="<?php echo $editProduct['id']; ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Product Photo</label>
                        <?php if ($editProduct && $editProduct['image_path']): ?>
                        <div style="margin-bottom:0.5rem;">
                            <img src="uploads/<?php echo htmlspecialchars($editProduct['image_path']); ?>" style="width:100px; height:100px; object-fit:cover; border-radius:8px;">
                        </div>
                        <?php endif; ?>
                        <input type="file" name="image" accept="image/*" <?php echo $editProduct ? '' : 'required'; ?>>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Product Name</label>
                        <input type="text" name="name" required value="<?php echo $editProduct ? htmlspecialchars($editProduct['name']) : ''; ?>" placeholder="e.g., Handmade Leather Wallet">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Student Creator</label>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <select name="creator_id" id="creator_id_select">
                                <option value="">-- Select Existing Creator --</option>
                                <?php foreach ($creatorsList as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($editProduct && $editProduct['creator_id'] == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <span style="font-weight: bold; color: #64748b;">OR</span>
                            
                            <input type="text" name="new_creator_name" id="new_creator_name" placeholder="Type new creator name...">
                        </div>
                        <small style="color: #64748b; display: block; margin-top: 5px;">Select an existing creator, or type a new name to reveal profile setup fields.</small>
                    </div>

                    <div id="new_creator_details" style="display: none; background: #ffffff; padding: 15px; border-radius: 8px; border: 1px dashed #cbd5e1; margin-bottom: 1rem;">
                        <h4 style="margin-top:0; margin-bottom: 15px; color: #0f172a;"><i class="fas fa-user-plus"></i> Set Up New Creator Profile</h4>
                        
                        <div class="form-group">
                            <label>Profile Image</label>
                            <input type="file" name="new_creator_image" accept="image/*">
                        </div>
                        
                        <div class="form-group">
                            <label>Contact Email</label>
                            <input type="email" name="new_creator_contact_email" placeholder="creator@example.com">
                        </div>
                        
                        <div class="form-group">
                            <label>Short Bio</label>
                            <textarea name="new_creator_bio" rows="2" placeholder="Tell the buyers about this creator..."></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <div class="form-group" style="flex: 1;">
                                <label><i class="fab fa-linkedin"></i> LinkedIn URL</label>
                                <input type="url" name="new_creator_linkedin" placeholder="https://linkedin.com/in/...">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label><i class="fas fa-globe"></i> Portfolio URL</label>
                                <input type="url" name="new_creator_portfolio" placeholder="https://myportfolio.com">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-dollar-sign"></i> Price (KES)</label>
                        <input type="number" name="price" required min="1" step="0.01" value="<?php echo $editProduct ? $editProduct['price'] : ''; ?>" placeholder="e.g., 500">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Description</label>
                        <textarea name="description" required placeholder="Describe the product..."><?php echo $editProduct ? htmlspecialchars($editProduct['description']) : ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-folder"></i> Category</label>
                        <select name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($editProduct && $editProduct['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="modal-button-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $editProduct ? 'Update' : 'Save'; ?> Product
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeProductModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <h2 style="margin-bottom:1rem;"><i class="fas fa-list"></i> Registered Catalog Products</h2>
        <div class="products-table-container">
            <div style="overflow-x:auto;">
                <table class="products-table">
                    <thead>
                        <tr>
                            <th style="width: 90px;">Image</th>
                            <th>Product Information & Description</th>
                            <th>Student Creator</th>
                            <th>Value</th>
                            <th style="width: 110px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <img src="<?php echo $product['image_path'] ? 'uploads/' . htmlspecialchars($product['image_path']) : 'https://via.placeholder.com/70?text=No+Img'; ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                            </td>
                            <td>
                                <div class="product-meta-block">
                                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <p class="desc-snippet"><?php echo htmlspecialchars(substr($product['description'], 0, 120)) . (strlen($product['description']) > 120 ? '...' : ''); ?></p>
                                    <div class="product-tags-line">
                                        <span class="inline-badge"><i class="fas fa-folder"></i> <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="inline-badge" style="background:#f1f5f9;"><i class="fas fa-user-graduate"></i> <?php echo htmlspecialchars($product['creator_name']); ?></span>
                            </td>
                            <td>
                                <span class="inline-badge price-tag"><strong>KES <?php echo number_format($product['price'], 2); ?></strong></span>
                            </td>
                            <td>
                                <div class="action-btns" style="justify-content: center;">
                                    <a href="admin_products.php?edit=<?php echo $product['id']; ?>" class="btn btn-secondary" title="Edit Product Information">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="admin_products.php?delete=<?php echo $product['id']; ?>" 
                                       onclick="return confirm('Are you sure you want to completely remove this item from the store catalog?')" 
                                       class="btn btn-danger" title="Delete Product Profile">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

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
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; setTimeout(() => toast.remove(), 300); }, 3000);
        }

        <?php if (isset($_SESSION['toast'])): ?>
            showToast("<?php echo htmlspecialchars($_SESSION['toast']['message']); ?>", "<?php echo htmlspecialchars($_SESSION['toast']['type']); ?>");
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        const modal = document.getElementById('productFormModal');
        
        function openProductModal() {
            modal.classList.add('active');
        }
        
        function closeProductModal() {
            modal.classList.remove('active');
            if(window.location.search.includes('edit=')) {
                window.location.href = 'admin_products.php';
            }
        }

        <?php if ($editProduct): ?>
            openProductModal();
        <?php endif; ?>

        document.addEventListener('DOMContentLoaded', function() {
            const creatorSelect = document.getElementById('creator_id_select');
            const creatorNameInput = document.getElementById('new_creator_name');
            const newCreatorDetails = document.getElementById('new_creator_details');

            function toggleNewCreatorDetails() {
                if (creatorNameInput.value.trim() !== '') {
                    newCreatorDetails.style.display = 'block';
                    creatorSelect.value = ''; 
                } else {
                    newCreatorDetails.style.display = 'none';
                }
            }

            creatorNameInput.addEventListener('input', toggleNewCreatorDetails);
            
            creatorSelect.addEventListener('change', function() {
                if (this.value !== '') {
                    creatorNameInput.value = ''; 
                    toggleNewCreatorDetails();
                }
            });
        });
    </script>
</body>
</html>