<?php
session_start();
require_once __DIR__ . '/applications/config.php';


if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
    header('Location: index.php');
    exit;
}

// Check login status
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';

// Check for age verification
$age_verified = isset($_SESSION['age_verified']) && $_SESSION['age_verified'] === true;

// Redirect if not age verified
if (!$age_verified) {
    header('Location: index.php');
    exit;
}

// Get order ID from URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id <= 0) {
    header('Location: index.php');
    exit;
}

// Get order details
$order_query = "SELECT * FROM orders WHERE order_id = $order_id";
$order_result = $conn->query($order_query);

if ($order_result->num_rows === 0) {
    header('Location: index.php');
    exit;
}

$order = $order_result->fetch_assoc();

// Check if user has access to this order
if ($is_logged_in && $order['user_id'] != $_SESSION['user_id'] && !$is_admin) {
    header('Location: index.php');
    exit;
}

// Get order items
$items_query = "SELECT oi.*, p.name, p.sku,
               (SELECT image_path FROM product_images WHERE product_id = p.product_id AND is_primary = 1 LIMIT 1) as image_path
               FROM order_items oi
               JOIN products p ON oi.product_id = p.product_id
               WHERE oi.order_id = $order_id";
$items_result = $conn->query($items_query);
$order_items = $items_result->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$subtotal = 0;
foreach ($order_items as $item) {
    $subtotal += $item['price_at_purchase'] * $item['quantity'];
}

// Set shipping and tax rates (same as in checkout.php)
$shipping_cost = 10.00;
$tax_rate = 0.07; // 7% tax rate
$tax_amount = $subtotal * $tax_rate;
$order_total = $subtotal + $shipping_cost + $tax_amount;

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
    <title>Order Confirmation - Your App Your Data</title>
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
        
        .confirmation-section {
            background-color: var(--dark-surface-2);
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .confirmation-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .confirmation-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary-light);
        }
        
        .order-number {
            font-size: 1.25rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        
        .order-date {
            color: var(--text-muted);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.75rem;
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
        
        .order-summary {
            margin-bottom: 2rem;
        }
        
        .order-details {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        
        .detail-block {
            flex: 1;
            min-width: 250px;
            margin-bottom: 1.5rem;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }
        
        .detail-value {
            color: white;
        }
        
        .order-items {
            margin-bottom: 2rem;
        }
        
        .item-row {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .item-row:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 60px;
            height: 60px;
            overflow: hidden;
            border-radius: 8px;
            margin-right: 15px;
        }
        
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .item-details {
            flex-grow: 1;
        }
        
        .item-name {
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .item-sku {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        .item-price {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        .item-quantity {
            margin: 0 1.5rem;
            text-align: center;
            min-width: 60px;
        }
        
        .item-quantity-value {
            font-weight: 600;
        }
        
        .item-quantity-label {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        .item-total {
            font-weight: 600;
            margin-left: auto;
            text-align: right;
            min-width: 70px;
        }
        
        .order-totals {
            border-top: 1px solid var(--border-color);
            padding-top: 1.5rem;
        }
        
        .totals-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }
        
        .totals-row.grand-total {
            font-size: 1.25rem;
            font-weight: 600;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
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
        
        .confirmation-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="images/logo.png" alt="Your App Your Data logo" class="logo">
                <span>Your App Your Data</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            Shop
                        </a>
                        <ul class="dropdown-menu dark-dropdown">
                            <?php foreach ($categories as $category): ?>
                            <li>
                                <a class="dropdown-item" href="index.php?category=<?php echo $category['category_id']; ?>">
                                    <?php echo $category['name']; ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="index.php">All Products</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                </ul>
                <div class="nav-btns">
                    <a href="#" class="btn btn-icon" data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas">
                        <i class="bi bi-cart3"></i>
                        <?php if ($cart_items_count > 0): ?>
                        <span class="cart-badge"><?php echo $cart_items_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if ($is_logged_in): ?>
                    <div class="dropdown">
                        <a href="#" class="btn btn-icon" data-bs-toggle="dropdown">
                            <i class="bi bi-person-fill"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end dark-dropdown">
                            <li><span class="dropdown-item-text">Hello, <?php echo htmlspecialchars($username); ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if ($is_admin): ?>
                            <li><a class="dropdown-item" href="backend.php"><i class="bi bi-gear me-2"></i> Admin Dashboard</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="account.php"><i class="bi bi-person me-2"></i> My Account</a></li>
                            <li><a class="dropdown-item" href="orders.php"><i class="bi bi-box me-2"></i> My Orders</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="index.php?logout=1"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                    <?php else: ?>
                    <a href="login.php" class="btn btn-icon" title="Login / Register">
                        <i class="bi bi-person-circle"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Confirmation Content -->
    <div class="container main-container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="confirmation-section">
                    <div class="confirmation-header">
                        <div class="text-center mb-3">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                        </div>
                        <h1 class="confirmation-title">Thank You for Your Order!</h1>
                        <div class="order-number">Order #<?php echo $order_id; ?></div>
                        <div class="order-date">Placed on <?php echo date('F j, Y', strtotime($order['created_at'])); ?></div>
                    </div>
                    
                    <div class="order-summary">
                        <h2 class="section-title">Order Summary</h2>
                        
                        <div class="order-details">
                            <div class="detail-block">
                                <div class="detail-label">Shipping Address</div>
                                <div class="detail-value"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></div>
                            </div>
                            
                            <div class="detail-block">
                                <div class="detail-label">Billing Address</div>
                                <div class="detail-value"><?php echo nl2br(htmlspecialchars($order['billing_address'])); ?></div>
                            </div>
                            
                            <div class="detail-block">
                                <div class="detail-label">Payment Method</div>
                                <div class="detail-value"><?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></div>
                            </div>
                            
                            <div class="detail-block">
                                <div class="detail-label">Order Status</div>
                                <div class="detail-value">
                                    <span class="order-status status-<?php echo $order['order_status']; ?>">
                                        <?php echo ucfirst($order['order_status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if (!empty($order['tracking_number'])): ?>
                            <div class="detail-block">
                                <div class="detail-label">Tracking Number</div>
                                <div class="detail-value"><?php echo $order['tracking_number']; ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="order-items">
                        <h2 class="section-title">Order Items</h2>
                        
                        <?php foreach ($order_items as $item): ?>
                        <div class="item-row">
                            <div class="item-image">
                                <img src="<?php echo !empty($item['image_path']) ? $item['image_path'] : 'images/placeholder.jpg'; ?>" alt="<?php echo $item['name']; ?>">
                            </div>
                            <div class="item-details">
                                <div class="item-name"><?php echo $item['name']; ?></div>
                                <?php if (!empty($item['sku'])): ?>
                                <div class="item-sku">SKU: <?php echo $item['sku']; ?></div>
                                <?php endif; ?>
                                <div class="item-price">$<?php echo number_format($item['price_at_purchase'], 2); ?></div>
                            </div>
                            <div class="item-quantity">
                                <div class="item-quantity-value"><?php echo $item['quantity']; ?></div>
                                <div class="item-quantity-label">Qty</div>
                            </div>
                            <div class="item-total">
                                $<?php echo number_format($item['price_at_purchase'] * $item['quantity'], 2); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="order-totals">
                            <div class="totals-row">
                                <div>Subtotal</div>
                                <div>$<?php echo number_format($subtotal, 2); ?></div>
                            </div>
                            <div class="totals-row">
                                <div>Shipping</div>
                                <div>$<?php echo number_format($shipping_cost, 2); ?></div>
                            </div>
                            <div class="totals-row">
                                <div>Tax (7%)</div>
                                <div>$<?php echo number_format($tax_amount, 2); ?></div>
                            </div>
                            <div class="totals-row grand-total">
                                <div>Total</div>
                                <div>$<?php echo number_format($order_total, 2); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="confirmation-actions">
                        <?php if ($is_logged_in): ?>
                        <a href="orders.php" class="btn btn-outline-primary">View All Orders</a>
                        <?php endif; ?>
                        <a href="index.php" class="btn btn-primary">Continue Shopping</a>
                    </div>
                </div>
            </div>
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