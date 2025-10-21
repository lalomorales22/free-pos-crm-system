<?php
session_start();
require_once __DIR__ . '/applications/config.php';

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
    <title>Privacy Policy - Your App Your Data</title>
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
<!-- Privacy Policy Content -->
    <div class="container main-container">
        <div class="content-section">
            <div class="privacy-header">
                <h1>Privacy Policy</h1>
                <p class="last-updated">Last updated: January 15, 2025</p>
            </div>

            <div class="privacy-content">
                <p>Your App Your Data was built around the idea that your customer and transaction data should stay in your hands. This Privacy Policy explains what information we process when you explore the demo environment, self-host the project, or contact our team.</p>

                <h3>Information we process</h3>
                <p>Within the hosted sandbox we collect only the details necessary to operate the experience:</p>
                <ul>
                    <li>Account basics such as name, email, and password (hashed) when you sign up.</li>
                    <li>Sample store data that you create—products, orders, customers—so you can see workflows in action.</li>
                    <li>Technical diagnostics including IP address, browser type, and request logs to keep the service healthy.</li>
                </ul>
                <p>If you self-host the project, your deployment controls the data that is stored. We do not receive any information from self-hosted instances unless you explicitly choose to share feedback or telemetry.</p>

                <h3>How we use the information</h3>
                <ul>
                    <li>Operate and improve the sandbox experience.</li>
                    <li>Respond to support requests and share product updates you opt into.</li>
                    <li>Monitor stability, security, and abuse.</li>
                </ul>
                <p>We never sell your data or use it for third-party advertising.</p>

                <h3>Cookies and analytics</h3>
                <p>The sandbox uses functional cookies to maintain sessions and remember in-app preferences. We rely on first-party analytics to understand aggregate feature usage. You can disable cookies in your browser; the core experience will continue to work, though you may need to log in again more often.</p>

                <h3>Integrations</h3>
                <p>Optional integrations (payments, messaging, analytics) are disabled by default in the demo. When you enable an integration in a self-hosted deployment, the third-party service will process data according to its own policies. Review those terms carefully before connecting your production environment.</p>

                <h3>Data retention</h3>
                <p>Sandbox accounts and sample records may be cleared on a rolling basis. If you need a backup of your demo data, export it from the admin dashboard before the reset schedule. For self-hosted deployments you control retention policies.</p>

                <h3>Security</h3>
                <p>We apply industry-standard security practices to the hosted sandbox, including encrypted transport, hashed credentials, and access controls. No platform is perfectly secure—please avoid loading sensitive production data into the demo.</p>

                <h3>Your choices</h3>
                <ul>
                    <li>Update or delete your sandbox profile from the account settings page.</li>
                    <li>Request removal of demo data by emailing <a href="mailto:privacy@yourappyourdata.com">privacy@yourappyourdata.com</a>.</li>
                    <li>Unsubscribe from product emails using the link in any message.</li>
                </ul>

                <h3>Changes to this policy</h3>
                <p>We may update this Privacy Policy to reflect new features or legal requirements. Material updates will be announced in the changelog and through in-app notices.</p>

                <h3>Contact</h3>
                <p>Questions or requests? Email us at <a href="mailto:privacy@yourappyourdata.com">privacy@yourappyourdata.com</a> or write to Your App Your Data, 58 South Park St., San Francisco, CA 94107.</p>
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
