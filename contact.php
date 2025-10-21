<?php
session_start();
require_once __DIR__ . '/applications/config.php';

$page_title = "Contact Us - Den Glass Shop";

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
        $user_id_to_log = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : "NULL";
        $conn->query("INSERT INTO age_verification_attempts (user_id, ip_address, attempt_successful, details, attempted_at) VALUES ($user_id_to_log, '$ip', 1, 'User confirmed age via popup on contact page', NOW())");
    } else {
        // Record failed verification attempt
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_id_to_log = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : "NULL";
        $conn->query("INSERT INTO age_verification_attempts (user_id, ip_address, attempt_successful, details, attempted_at) VALUES ($user_id_to_log, '$ip', 0, 'User did not confirm age via popup or exited on contact page', NOW())");
        
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

// Handle contact form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    // Get form data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message_content = trim($_POST['message']);
    
    // Simple validation
    if (empty($name) || empty($email) || empty($subject) || empty($message_content)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // You would typically send an email here
        // For demonstration, we'll just save to database
        
        // Sanitize inputs for database
        $name = $conn->real_escape_string($name);
        $email = $conn->real_escape_string($email);
        $subject = $conn->real_escape_string($subject);
        $message_content = $conn->real_escape_string($message_content);
        
        // Store in contact_messages table
        $ip = $_SERVER['REMOTE_ADDR'];
        
        $sql = "INSERT INTO contact_messages (name, email, subject, message, ip_address) 
                VALUES ('$name', '$email', '$subject', '$message_content', '$ip')";
        
        if ($conn->query($sql) === TRUE) {
            $message = "Thank you for your message! We'll get back to you soon.";
            // Clear form data
            $name = $email = $subject = $message_content = '';
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Your App Your Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Contact-specific styles */
        .contact-hero {
            background-color: var(--muted);
            padding: 4rem 0;
            text-align: center;
            border-bottom: 1px solid var(--border);
        }
        
        .contact-hero h1 {
            color: var(--foreground);
            margin-bottom: 1rem;
        }
        
        .contact-hero p {
            color: var(--muted-foreground);
            font-size: 1.125rem;
        }
        
        .contact-section {
            background-color: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }
        
        .contact-section h2 {
            color: var(--foreground);
            font-weight: 600;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.75rem;
        }
        
        .contact-info {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .contact-info i {
            color: var(--primary);
            margin-right: 1rem;
            font-size: 1.25rem;
            width: 2rem;
        }
        
        .contact-form .form-group {
            margin-bottom: 1.5rem;
        }
        
        .contact-form textarea {
            min-height: 120px;
            resize: vertical;
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

    <!-- Contact Hero -->
    <section class="contact-hero">
        <div class="container">
            <div class="text-center">
                <h1>Contact Us</h1>
                <p class="lead">We'd love to hear from you</p>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container main-container">
        <?php if (!empty($message)): ?>
        <div class="alert alert-success mb-4">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger mb-4">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <!-- Contact Info Cards -->
        <div class="row mb-5">
            <div class="col-md-4 mb-4 mb-md-0">
                <div class="card text-center">
                    <div class="card-content">
                        <div class="contact-info">
                            <i class="bi bi-geo-alt"></i>
                        </div>
                        <h3>Visit Us</h3>
                        <p>San Francisco, CA</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4 mb-md-0">
                <div class="card text-center">
                    <div class="card-content">
                        <div class="contact-info">
                            <i class="bi bi-telephone"></i>
                        </div>
                        <h3>Call Us</h3>
                        <p>Studio line: (415) 555-2048</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-content">
                        <div class="contact-info">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <h3>Email Us</h3>
                        <p>hello@yourappyourdata.com</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Contact Form -->
            <div class="col-lg-7 mb-5 mb-lg-0">
                <h2 class="section-title mb-4">Send Us a Message</h2>
                <div class="card">
                    <div class="card-content">
                    <form method="post" action="contact.php">
                        <div class="mb-3">
                            <label for="name" class="form-label">Your Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <select class="form-select" id="subject" name="subject" required>
                                <option value="" selected disabled>Select a subject</option>
                                <option value="Feature Request" <?php echo (isset($subject) && $subject == 'Feature Request') ? 'selected' : ''; ?>>Feature Request</option>
                                <option value="Deployment Help" <?php echo (isset($subject) && $subject == 'Deployment Help') ? 'selected' : ''; ?>>Deployment Help</option>
                                <option value="Partnership" <?php echo (isset($subject) && $subject == 'Partnership') ? 'selected' : ''; ?>>Partnership</option>
                                <option value="Press" <?php echo (isset($subject) && $subject == 'Press') ? 'selected' : ''; ?>>Press</option>
                                <option value="Other" <?php echo (isset($subject) && $subject == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label for="message" class="form-label">Your Message</label>
                            <textarea class="form-control" id="message" name="message" rows="5" required><?php echo isset($message_content) ? htmlspecialchars($message_content) : ''; ?></textarea>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="privacy" required>
                            <label class="form-check-label" for="privacy">I agree to the <a href="privacy.php">privacy policy</a></label>
                        </div>
                        <button type="submit" name="contact_submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Send Message
                        </button>
                    </form>
                    </div>
                </div>
            </div>
            
            <!-- Business Hours -->
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-content">
                    <h2 class="section-title mb-4">Business Hours</h2>
                    <ul class="hours-list">
                        <li>
                            <span class="day">Monday</span>
                            <span class="time">10:00 AM - 7:00 PM</span>
                        </li>
                        <li>
                            <span class="day">Tuesday</span>
                            <span class="time">10:00 AM - 7:00 PM</span>
                        </li>
                        <li>
                            <span class="day">Wednesday</span>
                            <span class="time">10:00 AM - 7:00 PM</span>
                        </li>
                        <li>
                            <span class="day">Thursday</span>
                            <span class="time">10:00 AM - 7:00 PM</span>
                        </li>
                        <li>
                            <span class="day">Friday</span>
                            <span class="time">10:00 AM - 8:00 PM</span>
                        </li>
                        <li>
                            <span class="day">Saturday</span>
                            <span class="time">11:00 AM - 8:00 PM</span>
                        </li>
                        <li>
                            <span class="day">Sunday</span>
                            <span class="time closed">Closed</span>
                        </li>
                    </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Map -->
        <div class="map-container">
            <!-- Replace with your own Google Maps or other map provider embed code -->
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d107399.93516773698!2d-117.2340910665244!3d32.823748949786616!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x80d9530fad921e4b%3A0xd3a21fdfd15df79!2sSan%20Diego%2C%20CA!5e0!3m2!1sen!2sus!4v1648765431228!5m2!1sen!2sus" allowfullscreen="" loading="lazy"></iframe>
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