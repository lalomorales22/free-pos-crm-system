<?php
session_start();

// Include the centralized database configuration file
require_once __DIR__ . '/applications/config.php';

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
    <title>Terms and Conditions - Your App Your Data</title>
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
        <div class="container d-flex justify-content-center align-items-center">
            <a class="navbar-brand" href="index.php">
                <img src="images/logo.png" alt="Your App Your Data logo" class="logo">
                <span>Your App Your Data</span>
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
        <section class="content-section">
            <div class="last-updated">
                <p>Last updated: January 15, 2025</p>
            </div>
            <h1>Terms and Conditions</h1>
            <p>These Terms and Conditions (the "Terms") explain how you may access and use the Your App Your Data demo experience, related documentation, and any services provided through the platform (collectively, the "Services"). By exploring the demo or creating an account, you agree to follow these Terms. If you do not agree, please discontinue use immediately.</p>

            <h3>1. Who can use the Services</h3>
            <p>The demo is designed for founders, makers, and teams evaluating our open POS + CRM toolkit. You must be at least 18 years old and have the authority to agree to these Terms on behalf of yourself or the organization you represent. You are responsible for safeguarding your login credentials and any activity performed under your account.</p>

            <h3>2. Sandbox experience</h3>
            <p>The hosted environment is a sandbox intended for exploration and testing only. Sample data may be reset without notice. Do not store live payment information, sensitive customer records, or regulated content in the sandbox. If you self-host the project, you are solely responsible for compliance, data security, and uptime.</p>

            <h3>3. Your data, your ownership</h3>
            <p>You retain ownership of any data you upload or generate while using the Services. We do not claim rights to your catalog, customer records, or business insights. When you use the hosted demo, you grant us permission to process the information solely to operate and improve the experience. Please review our Privacy Policy for details on data handling.</p>

            <h3>4. Integrations and third-party services</h3>
            <p>The project offers optional integrations (for example, payment gateways, analytics, messaging, or inventory providers). These integrations are provided for convenience, and each third-party service has its own terms. You are responsible for reviewing and complying with those terms and for any fees charged by the integration partners.</p>

            <h3>5. Acceptable use</h3>
            <p>You agree not to misuse the Services. Prohibited activities include reverse engineering without the required open-source notices, attempting to gain unauthorized access to infrastructure, uploading malicious code, or infringing on the intellectual property or privacy rights of others.</p>

            <h3>6. Intellectual property</h3>
            <p>We retain all rights in the Your App Your Data brand, documentation, and platform code that are not granted through open-source licenses. Some components are released under permissive licenses referenced within the repository. You may contribute improvements according to the applicable contribution guidelines.</p>

            <h3>7. Disclaimers and limitation of liability</h3>
            <p>The Services are provided on an "as is" and "as available" basis. We do not guarantee uninterrupted availability, accuracy of sample data, or suitability for a particular purpose. To the fullest extent permitted by law, Your App Your Data and its contributors are not liable for lost profits, lost data, or indirect damages arising from use of the Services.</p>

            <h3>8. Changes to the Services or Terms</h3>
            <p>We may update the platform, documentation, or these Terms at any time. When changes are material, we will highlight them within the changelog or notify registered users. Continued use of the Services after updates constitutes acceptance of the revised Terms.</p>

            <h3>9. Contact</h3>
            <p>If you have questions about these Terms or wish to report a concern, reach out to us at <a href="mailto:legal@yourappyourdata.com">legal@yourappyourdata.com</a>.</p>
        </section>
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
</body>
</html> 