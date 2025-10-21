<?php
session_start();
require_once __DIR__ . '/applications/config.php';

// Check login status
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';

// If not logged in, redirect to login page
if (!$is_logged_in) {
    header('Location: login.php');
    exit;
}

// Check for age verification
$age_verified = isset($_SESSION['age_verified']) && $_SESSION['age_verified'] === true;

// Get user ID
$user_id = $_SESSION['user_id'];

// Handle cart removal
if (isset($_GET['remove_from_cart']) && isset($_SESSION['cart'][$_GET['remove_from_cart']])) {
    unset($_SESSION['cart'][$_GET['remove_from_cart']]);
    header("Location: orders.php");
    exit;
}

// Calculate cart items count for navbar
$cart_items_count = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_items_count += $item['quantity'];
    }
}

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';
$order_by = 'created_at DESC'; // Default sorting

switch ($sort) {
    case 'date_asc':
        $order_by = 'created_at ASC';
        break;
    case 'date_desc':
        $order_by = 'created_at DESC';
        break;
    case 'total_asc':
        $order_by = 'total_amount ASC';
        break;
    case 'total_desc':
        $order_by = 'total_amount DESC';
        break;
    case 'status':
        $order_by = 'order_status ASC, created_at DESC';
        break;
}

// Get total orders count
$count_query = "SELECT COUNT(*) as total FROM orders WHERE user_id = $user_id";
$count_result = $conn->query($count_query);
$total_orders = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_orders / $items_per_page);

// Get orders
$orders_query = "SELECT * FROM orders 
                WHERE user_id = $user_id 
                ORDER BY $order_by 
                LIMIT $offset, $items_per_page";
$orders_result = $conn->query($orders_query);
$orders = $orders_result->fetch_all(MYSQLI_ASSOC);

// Get all categories for menu
$categories_query = "SELECT c.*, COUNT(p.product_id) as product_count 
                   FROM categories c 
                   LEFT JOIN products p ON c.category_id = p.category_id 
                   WHERE c.parent_id IS NULL 
                   GROUP BY c.category_id 
                   ORDER BY c.name";
$categories_result = $conn->query($categories_query);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Calculate cart total for header
$cart_total = 0;
$cart_items_count = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_total += $item['price'] * $item['quantity'];
        $cart_items_count += $item['quantity'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Your App Your Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --primary-color: #1e88e5;
            --primary-dark: #1565c0;
            --primary-light: #64b5f6;
            --dark-surface-1: #121212;
            --dark-surface-2: #1e1e1e;
            --text-muted: #b0b0b0;
            --accent-color: #00acc1;
            --border-color: rgba(255, 255, 255, 0.1);
        }
        
        .orders-section {
            background-color: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            position: relative;
            padding-bottom: 0.75rem;
            margin-bottom: 0;
            color: #333;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--primary-color);
        }
        
        .sort-dropdown .dropdown-menu {
            min-width: 180px;
            padding: 0.5rem;
        }
        
        .sort-dropdown .dropdown-item {
            padding: 0.5rem 1rem;
            border-radius: 4px;
        }
        
        .sort-dropdown .dropdown-item.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .order-card {
            background-color: white;
            border-radius: 10px;
            margin-bottom: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid #e0e0e0;
        }
        
        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .order-header {
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .order-id {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .order-date {
            color: #666;
            font-size: 0.9rem;
        }
        
        .order-body {
            padding: 1rem;
        }
        
        .order-total {
            font-weight: 600;
            font-size: 1.1rem;
            color: #333;
        }
        
        .order-status {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-pending {
            background-color: rgba(255, 193, 7, 0.15);
            color: #ffc107;
        }
        
        .status-processing {
            background-color: rgba(0, 123, 255, 0.15);
            color: #007bff;
        }
        
        .status-shipped {
            background-color: rgba(102, 16, 242, 0.15);
            color: #6610f2;
        }
        
        .status-delivered {
            background-color: rgba(40, 167, 69, 0.15);
            color: #28a745;
        }
        
        .status-canceled {
            background-color: rgba(220, 53, 69, 0.15);
            color: #dc3545;
        }
        
        .pagination-container {
            margin-top: 2rem;
            display: flex;
            justify-content: center;
        }
        
        .empty-orders {
            text-align: center;
            padding: 3rem 0;
            color: #666;
        }
        
        .empty-orders i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
            color: #999;
        }
        
        .empty-orders p {
            margin-bottom: 1.5rem;
        }
        
        /* Additional styling for white background compatibility */
        .orders-section h1 {
            color: #333;
        }
        
        .orders-section p,
        .orders-section span {
            color: #333;
        }
        
        .text-muted {
            color: #666 !important;
        }
        
        .order-items-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .order-item-preview {
            width: 40px;
            height: 40px;
            border-radius: 4px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        
        .order-item-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .order-items-count {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 4px;
            background-color: rgba(255, 255, 255, 0.05);
            color: var(--text-muted);
            font-size: 0.8rem;
        }
        
        @media (max-width: 767px) {
            .order-body {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .order-status-container {
                margin-top: 0.5rem;
                margin-bottom: 0.5rem;
            }
            
            .order-actions {
                margin-top: 1rem;
                align-self: flex-end;
            }
        }
    </style>
</head>
<body>
    <?php if (!$age_verified): ?>
    <!-- Age Verification Modal -->
        <div class="age-verification-overlay" id="ageVerificationModal">
        <div class="age-verification-modal">
            <div class="age-verification-content">
                <img src="images/icon.png" alt="Your App Your Data emblem" class="age-verification-logo">
                <h2>Welcome to Your App Your Data</h2>
                <p>Spin up a free, modern POS + CRM sandbox that keeps every action and insight under your control.</p>
                <p>Before you dive in, please confirm that you understand this workspace is a demo environment and any sample data lives on your device.</p>
                <form method="post" action="" class="age-verification-form">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmAge" name="confirm_age" value="yes" required>
                        <label class="form-check-label" for="confirmAge">I understand this sandbox is for testing and stores information locally.</label>
                    </div>
                    <div class="age-verification-buttons">
                        <button type="submit" name="verify_age" class="btn btn-primary">Launch the Demo</button>
                        <a href="about.php" class="btn btn-outline-secondary">Learn More</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Top Navigation -->
    <nav class="navbar">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand" href="index.php">
                <img src="images/logo.png" alt="Your App Your Data logo" class="logo">
                <span>Your App Your Data</span>
            </a>
            <div class="d-flex align-items-center">
                <a href="#" class="btn-icon me-2" data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas">
                    <i class="bi bi-cart3"></i>
                    <?php if ($cart_items_count > 0): ?>
                    <span class="cart-badge"><?php echo $cart_items_count; ?></span>
                    <?php endif; ?>
                </a>
                <?php if ($is_logged_in): ?>
                <div class="dropdown">
                    <a href="#" class="btn-icon" data-bs-toggle="dropdown"><i class="bi bi-person-fill"></i></a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text">Hello, <?php echo htmlspecialchars($username); ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($is_admin): ?>
                        <li><a class="dropdown-item" href="backend.php"><i class="bi bi-gear me-2"></i> Admin</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="account.php"><i class="bi bi-person me-2"></i> Account</a></li>
                        <li><a class="dropdown-item" href="orders.php"><i class="bi bi-box me-2"></i> Orders</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="index.php?logout=1"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                    </ul>
                </div>
                <?php else: ?>
                <a href="login.php" class="btn-icon" title="Login / Register"><i class="bi bi-person-circle"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Orders Content -->
    <div class="container main-container">
        <h1 class="mb-4">My Orders</h1>
        
        <div class="orders-section">
            <div class="section-header">
                <h2 class="section-title">Order History</h2>
                
                <div class="dropdown sort-dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-sort-down me-1"></i> Sort By
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end dark-dropdown">
                        <li>
                            <a class="dropdown-item <?php echo $sort === 'date_desc' ? 'active' : ''; ?>" 
                               href="?sort=date_desc<?php echo $page > 1 ? '&page=' . $page : ''; ?>">
                               Newest First
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo $sort === 'date_asc' ? 'active' : ''; ?>" 
                               href="?sort=date_asc<?php echo $page > 1 ? '&page=' . $page : ''; ?>">
                               Oldest First
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo $sort === 'total_desc' ? 'active' : ''; ?>" 
                               href="?sort=total_desc<?php echo $page > 1 ? '&page=' . $page : ''; ?>">
                               Amount (High to Low)
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo $sort === 'total_asc' ? 'active' : ''; ?>" 
                               href="?sort=total_asc<?php echo $page > 1 ? '&page=' . $page : ''; ?>">
                               Amount (Low to High)
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo $sort === 'status' ? 'active' : ''; ?>" 
                               href="?sort=status<?php echo $page > 1 ? '&page=' . $page : ''; ?>">
                               Status
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order): 
                    // Get order items for this order
                    $items_query = "SELECT oi.*, p.name,
                                  (SELECT image_path FROM product_images WHERE product_id = p.product_id AND is_primary = 1 LIMIT 1) as image_path
                                  FROM order_items oi
                                  JOIN products p ON oi.product_id = p.product_id
                                  WHERE oi.order_id = {$order['order_id']}
                                  LIMIT 5";
                    $items_result = $conn->query($items_query);
                    $order_items = $items_result->fetch_all(MYSQLI_ASSOC);
                    
                    // Count total items
                    $items_count_query = "SELECT COUNT(*) as count FROM order_items WHERE order_id = {$order['order_id']}";
                    $items_count_result = $conn->query($items_count_query);
                    $total_items = $items_count_result->fetch_assoc()['count'];
                ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-id">Order #<?php echo $order['order_id']; ?></div>
                        <div class="order-date"><?php echo date('F j, Y', strtotime($order['created_at'])); ?></div>
                    </div>
                    <div class="order-body">
                        <div class="row align-items-center">
                            <div class="col-md-3 mb-3 mb-md-0">
                                <div class="order-total">$<?php echo number_format($order['total_amount'], 2); ?></div>
                                <div class="order-items-summary">
                                    <?php foreach ($order_items as $item): ?>
                                    <div class="order-item-preview">
                                        <img src="<?php echo !empty($item['image_path']) ? $item['image_path'] : 'images/placeholder.jpg'; ?>" 
                                             alt="<?php echo $item['name']; ?>">
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if ($total_items > 5): ?>
                                    <div class="order-items-count">
                                        +<?php echo $total_items - 5; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0 order-status-container">
                                <span class="order-status status-<?php echo $order['order_status']; ?>">
                                    <?php echo ucfirst($order['order_status']); ?>
                                </span>
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <?php if (!empty($order['tracking_number'])): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Tracking #:</small>
                                    <div><?php echo $order['tracking_number']; ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3 text-md-end order-actions">
                                <a href="order-confirmation.php?id=<?php echo $order['order_id']; ?>" class="btn btn-outline-primary btn-sm">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <nav aria-label="Page navigation">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $sort !== 'date_desc' ? '&sort=' . $sort : ''; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $sort !== 'date_desc' ? '&sort=' . $sort : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $sort !== 'date_desc' ? '&sort=' . $sort : ''; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-orders">
                    <i class="bi bi-box"></i>
                    <p>You haven't placed any orders yet.</p>
                    <a href="index.php" class="btn btn-primary">Start Shopping</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cart Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="cartOffcanvas" aria-labelledby="cartOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="cartOffcanvasLabel">Shopping Cart</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <?php 
            $cart_total = 0;
            if (isset($_SESSION['cart'])) {
                foreach ($_SESSION['cart'] as $item) {
                    $cart_total += $item['price'] * $item['quantity'];
                }
            }
            ?>
            <?php if (!empty($_SESSION['cart'])): ?>
            <form method="post" action="orders.php">
                <div class="cart-items">
                    <?php foreach ($_SESSION['cart'] as $item_id => $item): ?>
                    <div class="cart-item">
                        <div class="cart-item-image">
                            <img src="<?php echo (!empty($item['image']) && file_exists(trim($item['image'],'/'))) ? $item['image'] : 'images/placeholder.jpg'; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                        </div>
                        <div class="cart-item-details">
                            <h5 class="cart-item-title"><?php echo htmlspecialchars($item['name']); ?></h5>
                            <div class="cart-item-price">$<?php echo number_format($item['price'], 2); ?></div>
                            <input type="number" name="cart_quantity[<?php echo $item_id; ?>]" value="<?php echo $item['quantity']; ?>" min="1" class="form-control form-control-sm cart-item-quantity">
                        </div>
                        <div class="cart-item-total">
                            $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                        </div>
                        <button type="button" onclick="window.location.href='orders.php?remove_from_cart=<?php echo $item_id; ?>'" class="cart-item-remove" title="Remove Item">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="cart-summary mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Subtotal:</span>
                        <strong>$<?php echo number_format($cart_total, 2); ?></strong>
                    </div>
                    <button type="submit" name="update_cart" class="btn btn-outline-primary w-100 mb-2"><i class="bi bi-arrow-repeat"></i> Update Cart</button>
                    <a href="checkout.php" class="btn btn-primary w-100"><i class="bi bi-credit-card"></i> Checkout</a>
                </div>
            </form>
            <?php else: ?>
            <div class="empty-cart">
                <i class="bi bi-cart-x"></i>
                <p>Your cart is empty.</p>
                <a href="index.php" class="btn btn-primary">Start Shopping</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <div class="footer-logo">
                        <img src="images/icon.png" alt="Your App Your Data icon">
                        <h3>Your App Your Data</h3>
                    </div>
                    <p>A playful, privacy-first POS &amp; CRM workspace for founders, operators, and teams who want full ownership of their business data.</p>
                </div>
                <div class="col-md-3">
                    <h4>Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="terms.php">Terms & Conditions</a></li>
                        <li><a href="privacy.php">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h4>Categories</h4>
                    <ul class="footer-links">
                        <?php foreach ($categories as $category): ?>
                        <li>
                            <a href="index.php?category=<?php echo $category['category_id']; ?>">
                                <?php echo $category['name']; ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="col-md-2">
                    <h4>Connect With Us</h4>
                    <div class="social-links">
                        <a href="https://twitter.com/yourappyourdata" class="social-link"><i class="bi bi-twitter"></i></a>
                        <a href="https://github.com/yourappyourdata" class="social-link"><i class="bi bi-github"></i></a>
                        <a href="mailto:hello@yourappyourdata.com" class="social-link"><i class="bi bi-envelope"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Your App Your Data. All Rights Reserved.</p>
                <p class="demo-disclaimer">This open sandbox is for demo purposes only—keep backups of anything you love.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
