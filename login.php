<?php
session_start();
require_once __DIR__ . '/applications/config.php';

$error_message = '';

// Check login status
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

// If already logged in, redirect to account page
if ($is_logged_in) {
    header('Location: account.php');
    exit;
}

// Check for age verification
$age_verified = isset($_SESSION['age_verified']) && $_SESSION['age_verified'] === true;

// Handle login form submission
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = $_POST['password'];
    
    // Get user from database
    $sql = "SELECT * FROM users WHERE username = '$username'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Verify password against the hash
        if (password_verify($password, $user['password_hash'])) { 
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = (bool)$user['is_admin']; // Explicitly cast to boolean
            
            // Debug information
            error_log("Login attempt - User: " . $user['username'] . ", Is Admin: " . ($user['is_admin'] ? 'true' : 'false'));
            
            // Set age verification if user is already verified
            if ($user['age_verified']) {
                $_SESSION['age_verified'] = true;
            }
            
            // Redirect to backend if admin, otherwise to account page
            if ($user['is_admin']) {
                error_log("Redirecting admin user to backend.php");
                header("Location: backend.php");
            } else {
                error_log("Redirecting non-admin user to account.php");
                header("Location: account.php");
            }
            exit;
        } else {
            $login_error = "Invalid password";
        }
    } else {
        $login_error = "User not found";
    }
}

// Handle registration form submission
$register_error = '';
$register_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_submit'])) {
    $username = $conn->real_escape_string(trim($_POST['reg_username']));
    $email = $conn->real_escape_string(trim($_POST['reg_email']));
    $password = $_POST['reg_password'];
    $confirm_password = $_POST['reg_confirm_password'];
    
    // Validate form data
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $register_error = "All fields are required";
    } else if ($password !== $confirm_password) {
        $register_error = "Passwords do not match";
    } else if (strlen($password) < 8) {
        $register_error = "Password must be at least 8 characters long";
    } else {
        // Check if username already exists
        $check_username = "SELECT * FROM users WHERE username = '$username'";
        $username_result = $conn->query($check_username);
        
        // Check if email already exists
        $check_email = "SELECT * FROM users WHERE email = '$email'";
        $email_result = $conn->query($check_email);
        
        if ($username_result->num_rows > 0) {
            $register_error = "Username already exists";
        } else if ($email_result->num_rows > 0) {
            $register_error = "Email address already in use";
        } else {
            // Hash the password for storage
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Set age verification status based on session
            $age_verified_value = isset($_SESSION['age_verified']) && $_SESSION['age_verified'] ? 1 : 0;
            
            // Insert new user
            $insert_query = "INSERT INTO users (username, email, password_hash, age_verified, created_at) 
                             VALUES ('$username', '$email', '$hashed_password', $age_verified_value, NOW())";
            
            if ($conn->query($insert_query)) {
                $register_success = "Registration successful! You can now log in with your credentials.";
            } else {
                $register_error = "Registration failed: " . $conn->error;
            }
        }
    }
}

// Age verification
if (isset($_POST['verify_age'])) {
    if (isset($_POST['confirm_age']) && $_POST['confirm_age'] === 'yes') {
        $_SESSION['age_verified'] = true;
    } else {
        // Redirect to an age restriction page
        header("Location: age-restriction.php");
        exit;
    }
}

// Get all categories for menu
$categories_query = "SELECT c.*, COUNT(p.product_id) as product_count 
                   FROM categories c 
                   LEFT JOIN products p ON c.category_id = p.category_id 
                   WHERE c.parent_id IS NULL 
                   GROUP BY c.category_id 
                   ORDER BY c.name";
$categories_result = $conn->query($categories_query);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login/Register - Your App Your Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
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

    <!-- Login/Register Content -->
    <div class="container main-container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="row g-4">
                    <!-- Login Column -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header text-center">
                                <h2>Login to Your Account</h2>
                            </div>
                            <div class="card-content">
                                <?php if (!empty($login_error)): ?>
                                <div class="alert alert-danger mb-4">
                                    <?php echo $login_error; ?>
                                </div>
                                <?php endif; ?>
                                
                                <form method="post">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" name="username" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
                                        <label class="form-check-label" for="rememberMe">Remember me</label>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" name="login_submit" class="btn btn-primary">Login</button>
                                    </div>
                                </form>
                                
                                <div class="text-center mt-3">
                                    <a href="forgot-password.php" class="text-muted">Forgot your password?</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Register Column -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header text-center">
                                <h2>Create an Account</h2>
                            </div>
                            <div class="card-content">
                                <?php if (!empty($register_error)): ?>
                                <div class="alert alert-danger mb-4">
                                    <?php echo $register_error; ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($register_success)): ?>
                                <div class="alert alert-success mb-4">
                                    <?php echo $register_success; ?>
                                </div>
                                <?php endif; ?>
                                
                                <form method="post">
                                    <div class="mb-3">
                                        <label for="reg_username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="reg_username" name="reg_username" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="reg_email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="reg_email" name="reg_email" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="reg_password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="reg_password" name="reg_password" required>
                                        <div class="form-text text-muted">Password must be at least 8 characters long.</div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="reg_confirm_password" class="form-label">Confirm Password</label>
                                        <input type="password" class="form-control" id="reg_confirm_password" name="reg_confirm_password" required>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" name="register_submit" class="btn btn-primary">Register</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
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
                                <?php echo $category['name']; ?>
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