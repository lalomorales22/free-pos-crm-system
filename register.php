<?php
session_start();
require_once __DIR__ . '/applications/config.php';

$error_message = '';

// Check for age verification
$age_verified = isset($_SESSION['age_verified']) && $_SESSION['age_verified'] === true;

// Handle registration form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_submit'])) {
    $username = $conn->real_escape_string(trim($_POST['username']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = $conn->real_escape_string(trim($_POST['first_name']));
    $last_name = $conn->real_escape_string(trim($_POST['last_name']));
    $phone = $conn->real_escape_string(trim($_POST['phone']));
    
    // Validate form data
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All required fields must be filled out";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } else {
        // Check if username or email already exists
        $check_query = "SELECT * FROM users WHERE username = '$username' OR email = '$email'";
        $result = $conn->query($check_query);
        
        if ($result->num_rows > 0) {
            $error = "Username or email already exists";
        } else {
            // Hash password for security (using PHP's built-in password_hash)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user into database
            $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, phone, age_verified) 
                    VALUES ('$username', '$email', '$hashed_password', '$first_name', '$last_name', '$phone', " . ($age_verified ? 1 : 0) . ")";
            
            if ($conn->query($sql) === TRUE) {
                // Set session variables
                $user_id = $conn->insert_id;
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['is_admin'] = false;
                $_SESSION['age_verified'] = $age_verified;
                
                // Redirect to account page or home
                $message = "Registration successful! Redirecting...";
                header("refresh:2;url=account.php");
            } else {
                $error = "Error: " . $conn->error;
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

// Calculate cart items count
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cart_items_count = 0;
foreach ($_SESSION['cart'] as $item) {
    $cart_items_count += $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Your App Your Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .register-container {
            max-width: 700px;
            margin: 0 auto;
        }
        
        .register-card {
            background-color: var(--dark-surface);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .register-title {
            color: var(--primary-light);
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .register-form .form-control {
            background-color: var(--dark-surface-2);
            border-color: var(--border-color);
            color: var(--text-color);
            padding: 0.75rem 1rem;
        }
        
        .register-form .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(108, 52, 131, 0.25);
        }
        
        .register-links {
            margin-top: 1rem;
            text-align: center;
        }
        
        .register-links a {
            color: var(--text-muted);
            text-decoration: none;
        }
        
        .register-links a:hover {
            color: var(--primary-light);
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
                    <a href="login.php" class="btn btn-icon" title="Login / Register">
                        <i class="bi bi-person-circle"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Register Content -->
    <div class="container main-container my-4">
        <div class="register-container">
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
            
            <div class="register-card">
                <h2 class="register-title">Create an Account</h2>
                
                <form class="register-form" method="post">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username*</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address*</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password*</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="form-text text-muted">Minimum 8 characters</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password*</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone">
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">
                            I agree to the <a href="terms.php">Terms & Conditions</a> and <a href="privacy.php">Privacy Policy</a>
                        </label>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="marketing" name="marketing">
                        <label class="form-check-label" for="marketing">
                            Subscribe to our newsletter for exclusive offers and updates
                        </label>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="register_submit" class="btn btn-primary">
                            <i class="bi bi-person-plus"></i> Create Account
                        </button>
                    </div>
                </form>
                
                <div class="register-links mt-4">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Your App Your Data. All Rights Reserved.</p>
                <p class="demo-disclaimer">This open sandbox is for demo purposes only—keep backups of anything you love.</p>
            </div>
        </div>
    </footer>

    <!-- Cart Sidebar Offcanvas -->
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
                        <span>$<?php echo number_format(array_sum(array_map(function($item) { return $item['price'] * $item['quantity']; }, $_SESSION['cart'])), 2); ?></span>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
    <script>
        // Password strength validation
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const minLength = 8;
            const hasLowerCase = /[a-z]/.test(password);
            const hasUpperCase = /[A-Z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>]/.test(password);
            
            let strength = 0;
            let feedback = '';
            
            if (password.length >= minLength) strength++;
            if (hasLowerCase && hasUpperCase) strength++;
            if (hasNumber) strength++;
            if (hasSpecialChar) strength++;
            
            const strengthElement = this.nextElementSibling;
            
            if (password.length === 0) {
                strengthElement.textContent = 'Minimum 8 characters';
                strengthElement.classList.remove('text-danger', 'text-warning', 'text-success');
                strengthElement.classList.add('text-muted');
            } else if (strength === 0 || strength === 1) {
                strengthElement.textContent = 'Weak password';
                strengthElement.classList.remove('text-muted', 'text-warning', 'text-success');
                strengthElement.classList.add('text-danger');
            } else if (strength === 2 || strength === 3) {
                strengthElement.textContent = 'Moderate password';
                strengthElement.classList.remove('text-muted', 'text-danger', 'text-success');
                strengthElement.classList.add('text-warning');
            } else {
                strengthElement.textContent = 'Strong password';
                strengthElement.classList.remove('text-muted', 'text-danger', 'text-warning');
                strengthElement.classList.add('text-success');
            }
        });
        
        // Password matching validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword === '') {
                this.setCustomValidity('');
            } else if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>