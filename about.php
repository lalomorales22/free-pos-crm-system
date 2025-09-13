<?php
session_start();
require_once __DIR__ . '/applications/denglass-config.php';

$page_title = "About Us - Den Glass Shop";

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
    <title>About Us - 710 Den Glass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* About-specific styles */
        .about-hero {
            background-color: var(--muted);
            padding: 4rem 0;
            text-align: center;
            border-bottom: 1px solid var(--border);
        }
        
        .about-hero h1 {
            color: var(--foreground);
            margin-bottom: 1rem;
        }
        
        .about-hero p {
            color: var(--muted-foreground);
            font-size: 1.125rem;
        }
        
        .about-section {
            background-color: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }
        
        .about-section h2 {
            color: var(--foreground);
            font-weight: 600;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.75rem;
        }
        
        .about-section p {
            color: var(--muted-foreground);
            line-height: 1.8;
            margin-bottom: 1.5rem;
        }
        
        .team-member {
            text-align: center;
            padding: 1.5rem;
            background-color: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
        }
        
        .team-member img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 1rem;
            border: 3px solid var(--border);
        }
        
        .team-member h4 {
            color: var(--foreground);
            margin-bottom: 0.5rem;
        }
        
        .team-member .role {
            color: var(--primary);
            font-weight: 500;
            margin-bottom: 1rem;
        }
        
        .team-member p {
            color: var(--muted-foreground);
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <?php if (!$age_verified): ?>
    <!-- Age Verification Modal -->
    <div class="age-verification-overlay" id="ageVerificationModal">
        <div class="age-verification-modal">
            <div class="age-verification-content">
                <img src="images/icon.png" alt="710 Den Glass Logo" class="age-verification-logo">
                <h2>Age Verification</h2>
                <p>Welcome to 710 Den Glass. You must be 21 years or older to enter this site.</p>
                <p>By clicking "I AM 21 OR OLDER", you confirm that you are of legal age to view our products.</p>
                
                <form method="post" class="age-verification-form">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmAge" name="confirm_age" value="yes" required>
                        <label class="form-check-label" for="confirmAge">
                            I confirm that I am 21 years of age or older
                        </label>
                    </div>
                    <div class="age-verification-buttons">
                        <button type="submit" name="verify_age" class="btn btn-primary">I AM 21 OR OLDER</button>
                        <a href="https://www.google.com" class="btn btn-outline-secondary">EXIT</a>
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
                <img src="images/logo.png" alt="710DenGlass Logo" class="logo">
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
                <h1>About 710 Den Glass</h1>
                <p class="lead">Handling premium glass since 2015</p>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container main-container">
        <!-- Our Story -->
        <div class="card mb-5">
            <div class="card-content">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <h2 class="section-title">Our Story</h2>
                    <p>710 Den Glass started as a small passion project in 2015, born from our founder's deep appreciation for glass artistry and craftsmanship. What began as a modest workshop has grown into a premier destination for glass enthusiasts and collectors.</p>
                    <p>Our commitment to quality and innovation has never wavered. We work with talented artists across the country to bring unique, functional pieces to our customers. Every product in our collection is carefully selected for its quality, design, and exceptional craftsmanship.</p>
                    <p>Today, 710 Den Glass has evolved into a community of glass enthusiasts, artists, and collectors united by a shared passion for this timeless art form. We take pride in offering a curated collection that represents the very best in contemporary glass design.</p>
                </div>
                <div class="col-lg-6">
                    <div class="about-image">
                        <img src="images/about-story.jpg" alt="710 Den Glass Workshop" 
                             onerror="this.src='images/placeholder.jpg';this.onerror='';">
                    </div>
                </div>
            </div>
            </div>
        </div>

        <!-- Our Values -->
        <div class="card mb-5">
            <div class="card-content">
            <h2 class="section-title text-center mb-4">Our Core Values</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card text-center">
                        <div class="card-content">
                        <div class="values-icon">
                            <i class="bi bi-gem"></i>
                        </div>
                        <h3>Quality</h3>
                        <p>We never compromise on quality. Every piece in our collection undergoes rigorous inspection to ensure it meets our exacting standards. We believe in creating pieces that stand the test of time.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card text-center">
                        <div class="card-content">
                        <div class="values-icon">
                            <i class="bi bi-brush"></i>
                        </div>
                        <h3>Artistry</h3>
                        <p>We celebrate the unique art of glass craftsmanship. Our products combine traditional techniques with innovative designs, resulting in pieces that are both functional and beautiful.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card text-center">
                        <div class="card-content">
                        <div class="values-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <h3>Community</h3>
                        <p>We believe in fostering a community of glass enthusiasts. We support artists, educate customers, and create spaces where people can connect through their shared passion for glass.</p>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>

        <!-- Our Journey -->
        <div class="card mb-5">
            <div class="card-content">
            <div class="row">
                <div class="col-lg-6 order-lg-2 mb-4 mb-lg-0">
                    <h2 class="section-title">Our Journey</h2>
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-year">2015</div>
                            <p>710 Den Glass was founded in San Diego, California. Our first workshop opened with a small collection of handcrafted pieces.</p>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-year">2017</div>
                            <p>We expanded our collection to include collaborations with artists from across the country, bringing diverse styles and techniques to our customers.</p>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-year">2019</div>
                            <p>Our online store launched, allowing us to share our passion for glass with customers nationwide. The 710 Den community began to grow beyond San Diego.</p>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-year">2021</div>
                            <p>We introduced our premium line of custom pieces, offering customers the opportunity to own truly unique, one-of-a-kind glass artwork.</p>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-year">Today</div>
                            <p>We continue to grow while maintaining our commitment to quality, artistry, and community. We're excited to share our passion for glass with you.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 order-lg-1">
                    <div class="about-image">
                        <img src="images/about-journey.jpg" alt="710 Den Glass Journey"
                             onerror="this.src='images/placeholder.jpg';this.onerror='';">
                    </div>
                </div>
            </div>
            </div>
        </div>

        <!-- Our Team -->
        <div class="card mb-5">
            <div class="card-content">
            <h2 class="section-title text-center mb-4">Meet Our Team</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card text-center">
                        <div class="card-content">
                        <img src="images/team-1.jpg" class="team-member-image" alt="Team Member"
                             onerror="this.src='images/placeholder.jpg';this.onerror='';">
                        <div class="team-member-info">
                            <h3 class="team-member-name">Andrew Friedman</h3>
                            <div class="team-member-position">Founder & Creative Director</div>
                            <p>Andrew founded 710 Den Glass with a vision of bringing exceptional glass art to enthusiasts everywhere. His expertise in glass techniques and keen eye for design guide our collection.</p>
                        </div>
                        </div>
                    </div>
                </div>
                
            </div>
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
                        <img src="images/icon.png" alt="710 Den Glass Logo">
                        <h3>710 Den Glass</h3>
                    </div>
                    <p>Premium glass pieces for discerning collectors. We pride ourselves on quality craftsmanship and exceptional customer service.</p>
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
                        <a href="#" class="social-link"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="social-link"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="social-link"><i class="bi bi-twitter"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> 710 Den Glass. All Rights Reserved.</p>
                <p class="age-disclaimer">You must be 21 years or older to purchase from this website.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>