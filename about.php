<?php
session_start();
require_once __DIR__ . '/../applications/denglass-config.php';

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
    <title>About Andrew - 710 Den Glass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Andrew's Story - Black & White Design */
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
        
        /* Andrew's Journey Timeline */
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
                <h1>Meet Andrew</h1>
                <p class="lead">The visionary behind 710 Den Glass - crafting premium glass experiences since 2015</p>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container main-container">
        <!-- Andrew's Story -->
        <div class="story-section">
            <h2>Andrew's Story</h2>
            <p>710 Den Glass started as Andrew's passion project in 2015, born from his deep appreciation for glass artistry and craftsmanship. What began as a modest workshop has grown into a premier destination for glass enthusiasts and collectors.</p>
            <p>Andrew's commitment to quality and innovation has never wavered. He works with talented artists across the country to bring unique, functional pieces to customers. Every product in the collection is carefully selected for its quality, design, and exceptional craftsmanship.</p>
            <p>Today, 710 Den Glass has evolved into a community of glass enthusiasts, artists, and collectors united by a shared passion for this timeless art form. Andrew takes pride in offering a curated collection that represents the very best in contemporary glass design.</p>
        </div>

        <!-- Character Showcase -->
        <div class="story-section">
            <h2>Andrew's World</h2>
            <p>Get to know Andrew through these animated scenes that capture his passion for glass artistry and dedication to the craft.</p>
            
            <div class="character-showcase">
                <div class="character-card">
                    <img src="images/about-hero.jpg" alt="Andrew in his workshop" class="character-image"
                         onerror="this.src='images/placeholder.jpg';this.onerror='';">
                    <h3>The Craftsman</h3>
                    <p>Andrew in his element, working with precision and passion to curate the finest glass pieces for the 710 Den collection.</p>
                </div>
                
                <div class="character-card">
                    <img src="images/about-story.jpg" alt="Andrew discovering new pieces" class="character-image"
                         onerror="this.src='images/placeholder.jpg';this.onerror='';">
                    <h3>The Explorer</h3>
                    <p>Always on the hunt for unique pieces and innovative designs, Andrew travels to connect with artists and discover the next addition to the collection.</p>
                </div>
                
                <div class="character-card">
                    <img src="images/about-journey.jpg" alt="Andrew with the community" class="character-image"
                         onerror="this.src='images/placeholder.jpg';this.onerror='';">
                    <h3>The Community Builder</h3>
                    <p>Building connections and fostering a community of glass enthusiasts who share Andrew's passion for quality and artistry.</p>
                </div>
            </div>
        </div>

        <!-- Andrew's Journey Timeline -->
        <div class="story-section">
            <h2>The Journey</h2>
            <div class="journey-timeline">
                <div class="timeline-item">
                    <div class="timeline-content">
                        <h3>The Beginning</h3>
                        <p>Andrew founded 710 Den Glass in San Diego, California. His first workshop opened with a small collection of handcrafted pieces and a big dream.</p>
                    </div>
                    <div class="timeline-year">2015</div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-content">
                        <h3>Expanding Horizons</h3>
                        <p>Andrew expanded the collection to include collaborations with artists from across the country, bringing diverse styles and techniques to customers.</p>
                    </div>
                    <div class="timeline-year">2017</div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-content">
                        <h3>Going Digital</h3>
                        <p>The online store launched, allowing Andrew to share his passion for glass with customers nationwide. The 710 Den community began to grow beyond San Diego.</p>
                    </div>
                    <div class="timeline-year">2019</div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-content">
                        <h3>Premium Collections</h3>
                        <p>Andrew introduced the premium line of custom pieces, offering customers the opportunity to own truly unique, one-of-a-kind glass artwork.</p>
                    </div>
                    <div class="timeline-year">2021</div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-content">
                        <h3>The Future</h3>
                        <p>Andrew continues to grow 710 Den Glass while maintaining his commitment to quality, artistry, and community. He's excited to share his passion for glass with you.</p>
                    </div>
                    <div class="timeline-year">Today</div>
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
                    <a href="https://www.instagram.com/r17quartz/" class="social-link"><i class="bi bi-instagram"></i></a>
                    <a href="https://www.instagram.com/r17quartz/" class="social-link"><i class="bi bi-facebook"></i></a>
                    <a href="https://www.threads.com/@r17quartz" class="social-link"><i class="bi bi-twitter"></i></a>
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