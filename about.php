<?php
session_start();
require_once __DIR__ . '/applications/config.php';

$page_title = "About - Your App Your Data";

// Check login status
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';

// Check for age verification
$age_verified = isset($_SESSION['age_verified']) && $_SESSION['age_verified'] === true;

// Age verification
if (isset($_POST['verify_age'])) {
    if (isset($_POST['confirm_age']) && $_POST['confirm_age'] === 'yes') {
        $_SESSION['age_verified'] = true;
        
        // If user is logged in, update their age verification status
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $conn->query("UPDATE users SET age_verified = 1 WHERE user_id = $user_id");
        }
        
        // Record verification attempt
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : "NULL";
        $conn->query("INSERT INTO age_verification (user_id, ip_address, verified) VALUES ($user_id, '$ip', 1)");
    } else {
        // Record failed verification attempt
        $ip = $_SERVER['REMOTE_ADDR'];
        $conn->query("INSERT INTO age_verification (ip_address, verified) VALUES ('$ip', 0)");
        
        // Redirect to an age restriction page
        header("Location: age-restriction.php");
        exit;
    }
}

// Get all categories for menu and footer
$categories_query = "SELECT c.*, COUNT(p.product_id) as product_count 
                   FROM categories c 
                   LEFT JOIN products p ON c.category_id = p.category_id 
                   WHERE c.parent_id IS NULL 
                   GROUP BY c.category_id 
                   ORDER BY c.name";
$categories_result = $conn->query($categories_query);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Calculate cart total
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cart_total = 0;
$cart_items_count = 0;
foreach ($_SESSION['cart'] as $item) {
    $cart_total += $item['price'] * $item['quantity'];
    $cart_items_count += $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Your App Your Data - Build It Your Way</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* About journey styling */
        .about-hero {
            background: #FFFFFF;
            padding: 5rem 0;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .about-hero .container {
            position: relative;
            z-index: 2;
        }
        
        .about-hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .about-hero p {
            font-size: 1.25rem;
            opacity: 0.95;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Story Sections */
        .story-section {
            background-color: white;
            border-radius: 20px;
            padding: 3rem;
            margin-bottom: 3rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .story-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: #000000;
        }
        
        .story-section h2 {
            color: #333;
            font-weight: 700;
            margin-bottom: 2rem;
            font-size: 2.5rem;
            position: relative;
        }
        
        .story-section p {
            color: #555;
            line-height: 1.8;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }
        
        /* Animated Character Image Layout */
        .character-showcase {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin: 3rem 0;
        }
        
        .character-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .character-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #000000;
        }
        
        .character-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }
        
        .character-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }
        
        .character-card:hover .character-image {
            transform: scale(1.05);
        }
        
        .character-card h3 {
            color: #333;
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .character-card p {
            color: #666;
            line-height: 1.6;
            font-size: 1rem;
        }
        
        /* Project timeline styling */
        .journey-timeline {
            position: relative;
            padding: 2rem 0;
        }
        
        .journey-timeline::before {
            content: '';
            position: absolute;
            left: 50%;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #000000;
            transform: translateX(-50%);
        }
        
        .timeline-item {
            display: flex;
            align-items: center;
            margin-bottom: 3rem;
            position: relative;
        }
        
        .timeline-item:nth-child(even) {
            flex-direction: row-reverse;
        }
        
        .timeline-content {
            flex: 1;
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            margin: 0 2rem;
            position: relative;
        }
        
        .timeline-year {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            background: #000000;
            color: white;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            z-index: 2;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .timeline-content h3 {
            color: #333;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .timeline-content p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 0;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .about-hero h1 {
                font-size: 2.5rem;
            }
            
            .journey-timeline::before {
                left: 20px;
            }
            
            .timeline-item {
                flex-direction: column !important;
                text-align: center;
            }
            
            .timeline-item:nth-child(even) {
                flex-direction: column !important;
            }
            
            .timeline-content {
                margin: 0 0 0 3rem;
            }
            
            .timeline-year {
                left: 20px;
                position: relative;
                transform: none;
                margin-bottom: 1rem;
            }
            
            .character-showcase {
                grid-template-columns: 1fr;
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

    <!-- About Hero -->
    <section class="about-hero">
        <div class="container">
            <div class="text-center">
                <h1>Build Your Own Retail Stack</h1>
                <p class="lead">Your App Your Data is the free playground for experimenting with modern POS and CRM workflows without giving up ownership of your customer data.</p>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container main-container">
        <!-- About Narrative -->
        <div class="story-section">
            <h2>Why Your App Your Data exists</h2>
            <p>Retailers, creators, and pop-up teams told us the same story: powerful POS and CRM tooling exists, but it often means surrendering ownership of customer records or paying more than a growing business can afford.</p>
            <p>Your App Your Data started as a weekend experiment to prove that a flexible stack could be playful, privacy-first, and entirely under your control. Every module in this demo mirrors features we build for teams that value agility over lock-in.</p>
            <p>Today the project is shaped by an open community of operators, engineers, and designers. Together we prototype flows, stress test data models, and keep everything transparent so you can remix it for your own business.</p>
        </div>

        <!-- Feature Showcase -->
        <div class="story-section">
            <h2>What you can launch in minutes</h2>
            <p>See how teams plug in the modules they need while keeping data portable and accessible.</p>
            <div class="character-showcase">
                <div class="character-card">
                    <img src="images/about-hero.jpg" alt="Unified checkout workflows" class="character-image" onerror="this.src='images/placeholder.jpg';this.onerror='';">
                    <h3>Unified Checkout</h3>
                    <p>Design tactile checkout experiences across mobile, desktop, and countertop hardware while staying offline-ready and fully synced.</p>
                </div>
                <div class="character-card">
                    <img src="images/about-story.jpg" alt="Customer relationship hub" class="character-image" onerror="this.src='images/placeholder.jpg';this.onerror='';">
                    <h3>Customer HQ</h3>
                    <p>Segment customers, launch loyalty campaigns, and log every interaction without worrying about losing visibility across channels.</p>
                </div>
                <div class="character-card">
                    <img src="images/about-journey.jpg" alt="Analytics and automation lab" class="character-image" onerror="this.src='images/placeholder.jpg';this.onerror='';">
                    <h3>Insights Lab</h3>
                    <p>Track sales, pull cohort dashboards, and automate nudges with a data warehouse you can audit and export at any time.</p>
                </div>
            </div>
        </div>

        <!-- Timeline -->
        <div class="story-section">
            <h2>Milestones that shaped the project</h2>
            <div class="journey-timeline">
                <div class="timeline-item">
                    <div class="timeline-content">
                        <h3>Proof of concept</h3>
                        <p>Your App Your Data launched as a weekend repo to show that POS, inventory, and customer data could live together without licensing fees or vendor lock-in.</p>
                    </div>
                    <div class="timeline-year">2020</div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-content">
                        <h3>Offline-first POS kit</h3>
                        <p>We introduced robust offline syncing, barcode support, and quick-serve layouts so crews could keep selling even when Wi-Fi drops.</p>
                    </div>
                    <div class="timeline-year">2021</div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-content">
                        <h3>CRM automations land</h3>
                        <p>We wired customer journeys, loyalty rewards, and webhook automations into the core so teams could personalize every touchpoint without third-party lock-in.</p>
                    </div>
                    <div class="timeline-year">2022</div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-content">
                        <h3>Open APIs & integrations</h3>
                        <p>We opened the platform with GraphQL and REST endpoints plus hardware drivers so you can integrate kiosks, ecommerce, and accounting stacks without friction.</p>
                    </div>
                    <div class="timeline-year">2023</div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-content">
                        <h3>Community-led future</h3>
                        <p>Every release now ships with community feedback, transparent changelogs, and migration guides so you can deploy updates on your own terms.</p>
                    </div>
                    <div class="timeline-year">Today</div>
                </div>
            </div>
        </div>

        <!-- Cart Sidebar -->
    <div class="offcanvas offcanvas-end" id="cartOffcanvas">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">Shopping Cart</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <?php if (!empty($_SESSION['cart'])): ?>
            <form method="post" action="index.php">
                <div class="cart-items">
                    <?php foreach ($_SESSION['cart'] as $item): ?>
                    <div class="cart-item">
                        <div class="cart-item-image">
                            <img src="<?php echo !empty($item['image']) ? $item['image'] : 'images/placeholder.jpg'; ?>" alt="<?php echo $item['name']; ?>">
                        </div>
                        <div class="cart-item-details">
                            <h5 class="cart-item-title"><?php echo $item['name']; ?></h5>
                            <div class="cart-item-price">$<?php echo number_format($item['price'], 2); ?></div>
                            <div class="cart-item-quantity">
                                <div class="input-group input-group-sm">
                                    <input type="number" name="cart_quantity[<?php echo $item['id']; ?>]" value="<?php echo $item['quantity']; ?>" min="1" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="cart-item-total">
                            $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                        </div>
                        <a href="index.php?remove_from_cart=<?php echo $item['id']; ?>" class="cart-item-remove" title="Remove Item">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="cart-summary">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span>$<?php echo number_format($cart_total, 2); ?></span>
                    </div>
                    <button type="submit" name="update_cart" class="btn btn-outline-primary btn-sm w-100 mb-2">
                        <i class="bi bi-arrow-repeat"></i> Update Cart
                    </button>
                    <a href="checkout.php" class="btn btn-primary w-100">
                        <i class="bi bi-credit-card"></i> Checkout
                    </a>
                </div>
            </form>
            <?php else: ?>
            <div class="empty-cart">
                <i class="bi bi-cart-x"></i>
                <p>Your cart is empty</p>
                <a href="index.php" class="btn btn-primary">
                    Start Shopping
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Floating AI Chat Icon -->
    <div class="floating-chat-icon">
        <a href="chat.php" class="btn btn-primary rounded-circle" title="AI Chat Assistant">
            <i class="bi bi-chat-dots"></i>
        </a>
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
                        <li><a href="index.php">Shop</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="chat.php">AI Chat</a></li>
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
                                <?php echo htmlspecialchars($category['name']); ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <li><a href="index.php">All Categories</a></li>
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
    <script src="script.js"></script>
</body>
</html>