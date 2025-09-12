<?php
session_start();
require_once __DIR__ . '/applications/denglass-config.php';

// Check login status
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';

// If not logged in, redirect to login page
if (!$is_logged_in) {
    header('Location: login.php');
    exit;
}

// Check for age verification
$age_verified = isset($_SESSION['age_verified']) && $_SESSION['age_verified'] === true;

// Get user data
$user_id = $_SESSION['user_id'];
$user_query = "SELECT * FROM users WHERE user_id = $user_id";
$user_result = $conn->query($user_query);
$user = $user_result->fetch_assoc();

// Handle profile update
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = $conn->real_escape_string(trim($_POST['first_name']));
    $last_name = $conn->real_escape_string(trim($_POST['last_name']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $address = $conn->real_escape_string(trim($_POST['address']));
    $city = $conn->real_escape_string(trim($_POST['city']));
    $state = $conn->real_escape_string(trim($_POST['state']));
    $zip_code = $conn->real_escape_string(trim($_POST['zip_code']));
    $phone = $conn->real_escape_string(trim($_POST['phone']));
    
    // Check if email is already taken by another user
    $email_check = "SELECT * FROM users WHERE email = '$email' AND user_id != $user_id";
    $email_result = $conn->query($email_check);
    
    if ($email_result->num_rows > 0) {
        $error_message = "Email is already in use by another account.";
    } else {
        // Update user info
        $update_query = "UPDATE users SET 
            email = '$email',
            first_name = '$first_name', 
            last_name = '$last_name', 
            address = '$address', 
            city = '$city', 
            state = '$state', 
            zip_code = '$zip_code', 
            phone = '$phone'
            WHERE user_id = $user_id";
        
        if ($conn->query($update_query)) {
            $success_message = "Profile updated successfully!";
            
            // Refresh user data
            $user_result = $conn->query($user_query);
            $user = $user_result->fetch_assoc();
        } else {
            $error_message = "Error updating profile: " . $conn->error;
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate password
    if ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match.";
    } else if (strlen($new_password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } else {
        // Verify current password
        if (password_verify($current_password, $user['password_hash'])) {
            
            // Hash the new password
            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $update_password_query = "UPDATE users SET password_hash = '$hashed_new_password' WHERE user_id = $user_id";
            
            if ($conn->query($update_password_query)) {
                $success_message = "Password changed successfully!";
            } else {
                $error_message = "Error changing password: " . $conn->error;
            }
        } else {
            $error_message = "Current password is incorrect.";
        }
    }
}

// Get recent orders
$orders_query = "SELECT * FROM orders WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 5";
$orders_result = $conn->query($orders_query);
$recent_orders = $orders_result->fetch_all(MYSQLI_ASSOC);

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
    <title>My Account - 710 Den Glass</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
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

        .account-section {
            background-color: var(--dark-surface-2);
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .account-heading {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.75rem;
        }
        
        .account-heading::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--primary-color);
        }
        
        .account-nav .nav-link {
            padding: 1rem;
            border-radius: 5px;
            transition: all 0.3s ease;
            color: white;
        }
        
        .account-nav .nav-link:hover,
        .account-nav .nav-link.active {
            background-color: rgba(30, 136, 229, 0.1);
            color: var(--primary-light);
        }
        
        .account-nav .nav-link i {
            margin-right: 0.5rem;
        }
        
        .profile-form .form-control,
        .profile-form .form-select {
            background-color: var(--dark-surface-1);
            border-color: var(--border-color);
            color: white;
            padding: 0.75rem 1rem;
        }
        
        .profile-form .form-control:focus,
        .profile-form .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(30, 136, 229, 0.25);
        }
        
        .profile-form label {
            margin-bottom: 0.5rem;
        }
        
        .order-card {
            background-color: var(--dark-surface-1);
            border-radius: 10px;
            margin-bottom: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }
        
        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .order-header {
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid var(--border-color);
        }
        
        .order-id {
            font-weight: 600;
            color: var(--primary-light);
        }
        
        .order-date {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .order-body {
            padding: 1rem;
        }
        
        .order-total {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .order-status {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-pending {
            background-color: rgba(255, 193, 7, 0.15);
            color: #ffc107;
        }
        
        .status-processing {
            background-color: rgba(0, 123, 255, 0.15);
            color: #007bff;
        }
        
        .status-shipped {
            background-color: rgba(102, 16, 242, 0.15);
            color: #6610f2;
        }
        
        .status-delivered {
            background-color: rgba(40, 167, 69, 0.15);
            color: #28a745;
        }
        
        .status-canceled {
            background-color: rgba(220, 53, 69, 0.15);
            color: #dc3545;
        }
        
        .btn-view-order {
            padding: 0.35rem 0.75rem;
            font-size: 0.85rem;
        }
        
        .empty-orders {
            text-align: center;
            padding: 2rem 0;
            color: var(--text-muted);
        }
        
        .empty-orders i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="images/icon.png" alt="710 Den Glass Logo" class="logo">
                <span>710 Den Glass</span>
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
                    <a href="index.php?logout=1" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Account Content -->
    <div class="container main-container">
        <h1 class="mb-4">My Account</h1>
        
        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success mb-4">
            <?php echo $success_message; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger mb-4">
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-lg-3 mb-4">
                <div class="account-section">
                    <h3 class="account-heading">My Account</h3>
                    <nav class="account-nav">
                        <a class="nav-link active" href="#profile" data-bs-toggle="tab">
                            <i class="bi bi-person"></i> Profile
                        </a>
                        <a class="nav-link" href="orders.php" data-bs-toggle="tab">
                            <i class="bi bi-box"></i> Orders
                        </a>
                        <a class="nav-link" href="#password" data-bs-toggle="tab">
                            <i class="bi bi-shield-lock"></i> Change Password
                        </a>
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-shop"></i> Continue Shopping
                        </a>
                        <a class="nav-link" href="index.php?logout=1">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </nav>
                </div>
            </div>
            
            <div class="col-lg-9">
                <div class="tab-content">
                    <!-- Profile Tab -->
                    <div class="tab-pane fade show active" id="profile">
                        <div class="account-section">
                            <h3 class="account-heading">Profile Information</h3>
                            <form class="profile-form" method="post">
                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label for="first_name">First Name</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="last_name">Last Name</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="address">Address</label>
                                    <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label for="city">City</label>
                                        <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-3 mb-3 mb-md-0">
                                        <label for="state">State</label>
                                        <input type="text" class="form-control" id="state" name="state" value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="zip_code">ZIP Code</label>
                                        <input type="text" class="form-control" id="zip_code" name="zip_code" value="<?php echo htmlspecialchars($user['zip_code'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                                
                                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Orders Tab -->
                    <div class="tab-pane fade" id="orders">
                        <div class="account-section">
                            <h3 class="account-heading">Recent Orders</h3>
                            
                            <?php if (!empty($recent_orders)): ?>
                                <?php foreach ($recent_orders as $order): ?>
                                <div class="order-card">
                                    <div class="order-header">
                                        <div class="order-id">Order #<?php echo $order['order_id']; ?></div>
                                        <div class="order-date"><?php echo date('F j, Y', strtotime($order['created_at'])); ?></div>
                                    </div>
                                    <div class="order-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-4 mb-2 mb-md-0">
                                                <div class="order-total">$<?php echo number_format($order['total_amount'], 2); ?></div>
                                            </div>
                                            <div class="col-md-4 mb-2 mb-md-0 text-md-center">
                                                <span class="order-status status-<?php echo $order['order_status']; ?>">
                                                    <?php echo ucfirst($order['order_status']); ?>
                                                </span>
                                            </div>
                                            <div class="col-md-4 text-md-end">
                                                <a href="order-details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-outline-primary btn-sm btn-view-order">
                                                    View Details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                                <div class="mt-3">
                                    <a href="orders.php" class="btn btn-outline-secondary btn-sm">
                                        View All Orders
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="empty-orders">
                                    <i class="bi bi-box"></i>
                                    <p>You haven't placed any orders yet.</p>
                                    <a href="index.php" class="btn btn-primary btn-sm">Start Shopping</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Change Password Tab -->
                    <div class="tab-pane fade" id="password">
                        <div class="account-section">
                            <h3 class="account-heading">Change Password</h3>
                            <form class="profile-form" method="post">
                                <div class="mb-3">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <div class="form-text text-muted">Password must be at least 8 characters long.</div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
                        <li><a href="index.php">Home</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
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
