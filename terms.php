<?php
session_start();

// Include the centralized database configuration file
require_once __DIR__ . '/applications/denglass-config.php';
// The $conn variable is now available from the included den-config.php
// Ensure $conn is valid before proceeding (already checked in den-config.php)

// Check login status
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';

// Check for age verification
$age_verified = isset($_SESSION['age_verified']) && $_SESSION['age_verified'] === true;

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

// Age verification
if (isset($_POST['verify_age'])) {
    if (isset($_POST['confirm_age']) && $_POST['confirm_age'] === 'yes') {
        $_SESSION['age_verified'] = true;
        // Redirect to remove the form submission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        // Redirect to an age restriction page
        header("Location: age-restriction.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms and Conditions - 710 Den Glass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Content-specific styles */
        .content-section {
            background-color: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }
        
        .content-section h2 {
            color: var(--foreground);
            font-weight: 600;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }
        
        .content-section h3 {
            color: var(--foreground);
            font-weight: 500;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        
        .content-section p {
            color: var(--muted-foreground);
            line-height: 1.8;
            margin-bottom: 1.5rem;
        }
        
        .content-section ul {
            color: var(--muted-foreground);
            line-height: 1.8;
            margin-bottom: 1.5rem;
            padding-left: 1.5rem;
        }
        
        .content-section li {
            margin-bottom: 0.5rem;
        }
        
        .content-section strong {
            color: var(--foreground);
        }
        
        .content-section a {
            color: var(--primary);
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .content-section a:hover {
            color: var(--primary);
            opacity: 0.8;
        }
        
        .last-updated {
            background-color: var(--muted);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .last-updated p {
            margin: 0;
            color: var(--foreground);
            font-weight: 500;
        }
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
        
        .privacy-section {
            background-color: var(--dark-surface-2);
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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
        
        .privacy-content h3 {
            color: var(--primary-light);
            margin-top: 2rem;
            margin-bottom: 1rem;
            font-size: 1.25rem;
        }
        
        .privacy-content p {
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        
        .privacy-content ul {
            margin-bottom: 1.5rem;
            padding-left: 1.5rem;
        }
        
        .privacy-content ul li {
            margin-bottom: 0.5rem;
        }
        
        .privacy-content a {
            color: var(--primary-light);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .privacy-content a:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }
        
        .privacy-header {
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .last-updated {
            font-style: italic;
            color: var(--text-muted);
            margin-top: 0.5rem;
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
        <div class="container d-flex justify-content-center align-items-center">
            <a class="navbar-brand" href="index.php">
                <img src="images/logo.png" alt="710DenGlass Logo" class="logo">
            </a>
        </div>
    </nav>
    
    <!-- Hero Banner -->
    <section class="hero-banner">
        <div class="container">
            <div class="hero-content">
                <h1>Terms and Conditions</h1>
                <p>Please review our terms and conditions before using our services.</p>
            </div>
        </div>
    </section>

    <!-- Terms and Conditions Content -->
    <div class="container main-container">
        <div class="content-section">
            <div class="privacy-header">
                <h1>Terms and Conditions</h1>
                <p class="last-updated">Last Updated: <?php echo date('F j, Y'); ?></p>
            </div>
            
            <div class="privacy-content">
                <p>Welcome to 710 Den Glass ("we," "our," or "us"). By accessing or using our website, you agree to be bound by these Terms and Conditions.</p>
                
                <h3>1. Introduction</h3>
                <p>These Terms and Conditions govern your use of our website and the purchase of products. By using this website, you acknowledge that you have read, understood, and agree to be bound by these terms.</p>
                
                <h3>2. Age Restrictions</h3>
                <p>You must be 21 years of age or older to access our website and purchase products. By using this website, you confirm that you meet this age requirement.</p>
                
                <h3>3. Products and Services</h3>
                <p>All products sold on our website are intended for legal use only. Our products are designed for tobacco use only. Misuse of our products is strictly prohibited.</p>
                
                <h3>4. Orders and Payment</h3>
                <p>By placing an order, you're making an offer to purchase products. We reserve the right to refuse or cancel any order for any reason, including limitations on quantities available for purchase.</p>
                
                <h3>5. Shipping and Delivery</h3>
                <p>Shipping times are estimates and not guaranteed. We are not responsible for delays due to customs, postal services, or other factors beyond our control.</p>
                
                <h3>6. Returns and Refunds</h3>
                <p>Please review our Return Policy for information about returns, refunds, and exchanges. Some items may not be eligible for return due to health and safety regulations.</p>
                
                <h3>7. Intellectual Property</h3>
                <p>All content on this website, including text, graphics, logos, images, and software, is our property and is protected by copyright and other intellectual property laws.</p>
                
                <h3>8. Privacy Policy</h3>
                <p>Our Privacy Policy describes how we collect, use, and protect your information. By using our website, you consent to the practices described in our Privacy Policy.</p>
                
                <h3>9. Limitation of Liability</h3>
                <p>We are not liable for any damages arising from your use of, or inability to use, our website or products. This includes direct, indirect, incidental, punitive, and consequential damages.</p>
                
                <h3>10. Changes to Terms</h3>
                <p>We may update these Terms and Conditions at any time without notice. Your continued use of our website after changes indicates your acceptance of the new Terms.</p>
                
                <h3>11. Contact Information</h3>
                <p>If you have any questions about these Terms and Conditions, please contact us at:</p>
                <p>
                    710 Den Glass<br>
                    Email: andrew@710denglass.com<br>
                    Phone: 408-529-5712
                </p>
            </div>
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
                        <a href="https://www.instagram.com/710denglass/" class="social-link"><i class="bi bi-instagram"></i></a>
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
</body>
</html> 