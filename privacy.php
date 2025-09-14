<?php
session_start();
require_once __DIR__ . '/applications/denglass-config.php';

$page_title = "Privacy Policy - Den Glass Shop";

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
    <title>Privacy Policy - 710 Den Glass</title>
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

    <!-- Privacy Policy Content -->
    <div class="container main-container">
        <div class="content-section">
            <div class="privacy-header">
                <h1>Privacy Policy</h1>
                <p class="last-updated">Last Updated: <?php echo date('F j, Y'); ?></p>
            </div>
            
            <div class="privacy-content">
                <p>At 710 Den Glass, we are committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you visit our website or make a purchase. Please read this Privacy Policy carefully. If you do not agree with the terms of this Privacy Policy, please do not access the site.</p>
                
                <h3>Information We Collect</h3>
                <p>We collect information that you provide directly to us, such as when you create an account, make a purchase, sign up for our newsletter, or contact us. This information may include:</p>
                <ul>
                    <li>Personal identifiers (name, email address, phone number, date of birth)</li>
                    <li>Billing and shipping information (address, payment information)</li>
                    <li>Account credentials (username, password)</li>
                    <li>Order history and preferences</li>
                    <li>Communications with our customer service team</li>
                </ul>
                
                <p>We may also automatically collect certain information about your device, including:</p>
                <ul>
                    <li>IP address</li>
                    <li>Device type and operating system</li>
                    <li>Browser type</li>
                    <li>Pages visited and interactions with our website</li>
                    <li>Referring website or source</li>
                </ul>
                
                <h3>How We Use Your Information</h3>
                <p>We use the information we collect to:</p>
                <ul>
                    <li>Process and fulfill your orders</li>
                    <li>Create and maintain your account</li>
                    <li>Verify your age and identity</li>
                    <li>Communicate with you about orders, products, and services</li>
                    <li>Respond to your inquiries and provide customer support</li>
                    <li>Send you marketing communications (if you've opted in)</li>
                    <li>Improve our website, products, and services</li>
                    <li>Protect against fraud and unauthorized transactions</li>
                    <li>Comply with legal obligations</li>
                </ul>
                
                <h3>Age Verification</h3>
                <p>Our products are intended for adults 21 years of age or older. We collect date of birth information to verify your age and comply with legal requirements. We may use third-party age verification services to confirm your age.</p>
                
                <h3>Cookies and Similar Technologies</h3>
                <p>We use cookies, web beacons, and similar technologies to track activity on our website and to enhance your experience. Cookies are small data files stored on your device that help us improve our website and your experience, see which areas and features of our website are popular, and count visits.</p>
                <p>Most web browsers are set to accept cookies by default. If you prefer, you can usually choose to set your browser to remove or reject cookies. Please note that if you choose to remove or reject cookies, this could affect certain features of our website.</p>
                
                <h3>Information Sharing</h3>
                <p>We may share your information with:</p>
                <ul>
                    <li>Service providers who help us operate our business (payment processors, shipping companies, etc.)</li>
                    <li>Professional advisors (lawyers, accountants, etc.)</li>
                    <li>Government authorities when required by law</li>
                    <li>Other third parties with your consent or direction</li>
                </ul>
                <p>We do not sell your personal information to third parties.</p>
                
                <h3>Data Security</h3>
                <p>We implement reasonable security measures to protect your personal information from unauthorized access, alteration, disclosure, or destruction. However, no website or internet transmission is completely secure, and we cannot guarantee that unauthorized access, hacking, data loss, or other breaches will never occur.</p>
                
                <h3>Data Retention</h3>
                <p>We will retain your personal information for as long as necessary to fulfill the purposes outlined in this Privacy Policy, unless a longer retention period is required or permitted by law.</p>
                
                <h3>Your Rights and Choices</h3>
                <p>Depending on your location, you may have certain rights regarding your personal information, including:</p>
                <ul>
                    <li>Access to your personal information</li>
                    <li>Correction of inaccurate or incomplete information</li>
                    <li>Deletion of your personal information</li>
                    <li>Restriction or objection to processing</li>
                    <li>Data portability</li>
                    <li>Withdrawal of consent</li>
                </ul>
                <p>To exercise these rights, please contact us using the information provided below.</p>
                
                <h3>Do Not Track Signals</h3>
                <p>Some browsers have a "Do Not Track" feature that lets you tell websites that you do not want to have your online activities tracked. Our website currently does not respond to "Do Not Track" signals.</p>
                
                <h3>Third-Party Links</h3>
                <p>Our website may contain links to third-party websites. We have no control over and assume no responsibility for the content, privacy policies, or practices of any third-party sites or services.</p>
                
                <h3>Changes to This Privacy Policy</h3>
                <p>We may update our Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Last Updated" date. You are advised to review this Privacy Policy periodically for any changes.</p>
                
                <h3>Contact Us</h3>
                <p>If you have questions or concerns about this Privacy Policy, please contact us at:</p>
                <p>
                    710 Den Glass<br>
                    Email: andrew@710denglass.com<br>
                    Phone: 408-529-5712<br>
                    Address: San Diego, CA 92101
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
</body>
</html>
