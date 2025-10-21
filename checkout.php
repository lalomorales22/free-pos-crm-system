<?php
session_start();
require_once __DIR__ . '/applications/config.php';


// Check login status
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';

// Check for age verification
$age_verified = isset($_SESSION['age_verified']) && $_SESSION['age_verified'] === true;

// Redirect if not age verified
if (!$age_verified) {
    header('Location: index.php');
    exit;
}

// Check if cart is empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: index.php');
    exit;
}

// Calculate cart totals
$subtotal = 0;
$cart_items = [];
$cart_items_count = 0;

foreach ($_SESSION['cart'] as $item_id => $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $cart_items_count += $item['quantity'];
    
    // Get updated product info
    $product_query = "SELECT * FROM products WHERE product_id = {$item['id']}";
    $product_result = $conn->query($product_query);
    
    if ($product_result->num_rows > 0) {
        $product = $product_result->fetch_assoc();
        
        // Check if product is still in stock
        if ($product['quantity'] < $item['quantity']) {
            // Update cart item quantity if needed
            if ($product['quantity'] > 0) {
                $_SESSION['cart'][$item_id]['quantity'] = $product['quantity'];
                $_SESSION['cart'][$item_id]['stock_warning'] = true;
                // Re-fetch item after modification for $cart_items array
                $cart_items[] = $_SESSION['cart'][$item_id];
            } else {
                // Remove from cart if out of stock
                unset($_SESSION['cart'][$item_id]);
                // Do not add to $cart_items, continue to next item
                continue; 
            }
        } else {
            $cart_items[] = $item; // Add original item if stock is sufficient
        }
    } else {
        // Product not found, remove from cart
        unset($_SESSION['cart'][$item_id]);
        continue;
    }
}

// If all items were out of stock, redirect back to index
if (empty($_SESSION['cart'])) {
    header('Location: index.php?cart_empty=1');
    exit;
}

// Re-populate cart_items from the potentially modified $_SESSION['cart']
// This ensures $cart_items reflects the true state of the cart for display
if (empty($cart_items) && !empty($_SESSION['cart'])) {
    $cart_items = array_values($_SESSION['cart']); // Rebuild $cart_items if it became empty due to product removal logic
}

// Set shipping and tax rates
$shipping_cost = 10.00;
$tax_rate = 0.07; // 7% tax rate

// Calculate totals
$tax_amount = $subtotal * $tax_rate;
$order_total = $subtotal + $shipping_cost + $tax_amount;

// Get user data for pre-filling checkout form
$user_data = [];
if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $user_query = "SELECT * FROM users WHERE user_id = $user_id";
    $user_result = $conn->query($user_query);
    $user_data = $user_result->fetch_assoc();
}

// Process checkout form
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    // Validate form data
    $first_name = $conn->real_escape_string(trim($_POST['first_name']));
    $last_name = $conn->real_escape_string(trim($_POST['last_name']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $phone = $conn->real_escape_string(trim($_POST['phone']));
    $shipping_address = $conn->real_escape_string(trim($_POST['shipping_address']));
    $shipping_city = $conn->real_escape_string(trim($_POST['shipping_city']));
    $shipping_state = $conn->real_escape_string(trim($_POST['shipping_state']));
    $shipping_zip = $conn->real_escape_string(trim($_POST['shipping_zip']));
    $payment_method = $conn->real_escape_string(trim($_POST['payment_method']));
    
    // Same billing as shipping?
    $same_billing = isset($_POST['same_billing']) ? true : false;
    
    if ($same_billing) {
        $billing_address = $shipping_address;
        $billing_city = $shipping_city;
        $billing_state = $shipping_state;
        $billing_zip = $shipping_zip;
    } else {
        $billing_address = $conn->real_escape_string(trim($_POST['billing_address']));
        $billing_city = $conn->real_escape_string(trim($_POST['billing_city']));
        $billing_state = $conn->real_escape_string(trim($_POST['billing_state']));
        $billing_zip = $conn->real_escape_string(trim($_POST['billing_zip']));
    }
    
    // Format address strings
    $shipping_address_full = "$shipping_address, $shipping_city, $shipping_state $shipping_zip";
    $billing_address_full = "$billing_address, $billing_city, $billing_state $billing_zip";
    
    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email) || empty($shipping_address) 
        || empty($shipping_city) || empty($shipping_state) || empty($shipping_zip) 
        || empty($payment_method) || (!$same_billing && (empty($billing_address) 
        || empty($billing_city) || empty($billing_state) || empty($billing_zip)))) {
        $error_message = "All required fields must be filled out.";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Create order
            $order_query = "INSERT INTO orders (user_id, total_amount, shipping_address, billing_address, 
                           payment_method, order_status, created_at) 
                           VALUES (" . ($is_logged_in ? $user_id : "NULL") . ", 
                           $order_total, '$shipping_address_full', '$billing_address_full', 
                           '$payment_method', 'pending', NOW())";
            
            if ($conn->query($order_query)) {
                $order_id = $conn->insert_id;
                
                // Insert order items and update inventory
                // Re-iterate over $_SESSION['cart'] to ensure we process the final state of the cart
                $current_cart_for_order = [];
                $order_subtotal_recalc = 0; // Recalculate subtotal based on final cart state
                foreach ($_SESSION['cart'] as $cart_item_for_order) {
                    $item_price = $cart_item_for_order['price'];
                    $item_quantity = $cart_item_for_order['quantity'];
                    $product_id = $cart_item_for_order['id'];
                    $order_subtotal_recalc += $item_price * $item_quantity;

                    // Fetch current product name and SKU for snapshot
                    $product_snapshot_query = "SELECT name, sku FROM products WHERE product_id = $product_id";
                    $product_snapshot_result = $conn->query($product_snapshot_query);
                    $product_snapshot = $product_snapshot_result->fetch_assoc();
                    $product_name_snapshot = $conn->real_escape_string($product_snapshot['name']);
                    $product_sku_snapshot = $conn->real_escape_string($product_snapshot['sku']);

                    // Add to order items
                    $item_query = "INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase, product_name, product_sku, created_at) 
                                  VALUES ($order_id, $product_id, $item_quantity, $item_price, '$product_name_snapshot', '$product_sku_snapshot', NOW())";
                    $conn->query($item_query);
                    
                    // Update inventory
                    $inventory_query = "UPDATE products SET quantity = quantity - $item_quantity 
                                       WHERE product_id = $product_id";
                    $conn->query($inventory_query);
                }

                // Recalculate final order total if subtotal changed due to stock adjustments
                $final_tax_amount = $order_subtotal_recalc * $tax_rate;
                $final_order_total = $order_subtotal_recalc + $shipping_cost + $final_tax_amount;

                // Update order total if it changed
                if ($final_order_total != $order_total) {
                    $update_order_total_query = "UPDATE orders SET total_amount = $final_order_total WHERE order_id = $order_id";
                    $conn->query($update_order_total_query);
                }

                // Update user info if logged in
                if ($is_logged_in) {
                    $update_user = "UPDATE users SET 
                                   first_name = '$first_name', 
                                   last_name = '$last_name', 
                                   email = '$email', 
                                   phone = '$phone', 
                                   address = '$shipping_address', 
                                   city = '$shipping_city', 
                                   state = '$shipping_state', 
                                   zip_code = '$shipping_zip'
                                   WHERE user_id = $user_id";
                    $conn->query($update_user);
                }
                
                // Commit transaction
                $conn->commit();
                
                // Clear cart
                $_SESSION['cart'] = [];
                
                // Set success message
                $success_message = "Order placed successfully! Your order number is #$order_id.";
                
                // Redirect to order confirmation
                header("Location: order-confirmation.php?id=$order_id");
                exit;
            } else {
                throw new Exception("Error creating order: " . $conn->error);
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = $e->getMessage();
        }
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
    <title>Checkout - Your App Your Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Checkout-specific styles */
        .checkout-section {
            background-color: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }
        
        .checkout-section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--foreground);
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.75rem;
        }
        
        .order-summary {
            background-color: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 20px;
        }
        
        .order-summary h3 {
            color: var(--foreground);
            margin-bottom: 1.5rem;
            font-weight: 600;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border);
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-item-image {
            width: 60px;
            height: 60px;
            border-radius: var(--radius);
            overflow: hidden;
            margin-right: 1rem;
            border: 1px solid var(--border);
        }
        
        .order-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .order-item-details {
            flex-grow: 1;
        }
        
        .order-item-title {
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
            color: var(--foreground);
        }
        
        .order-item-price {
            font-size: 0.75rem;
            color: var(--muted-foreground);
        }
        
        .order-item-total {
            font-weight: 600;
            text-align: right;
            color: var(--foreground);
        }
        
        .order-totals {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }
        
        .order-total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            color: var(--foreground);
        }
        
        .order-total-row.final {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--foreground);
            border-top: 1px solid var(--border);
            padding-top: 0.75rem;
            margin-top: 0.75rem;
        }
        
        .payment-methods {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .payment-method {
            flex: 1;
            padding: 1rem;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background-color: var(--background);
        }
        
        .payment-method:hover {
            border-color: var(--primary);
            background-color: var(--muted);
        }
        
        .payment-method.selected {
            border-color: var(--primary);
            background-color: var(--muted);
        }
        
        .payment-method i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .payment-method span {
            display: block;
            font-weight: 500;
            color: var(--foreground);
        }
        
        .security-notice {
            background-color: #dcfce7;
            border: 1px solid #86efac;
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .security-notice i {
            color: #166534;
            font-size: 1.25rem;
        }
        
        .security-notice span {
            color: #166534;
            font-weight: 500;
        }
        
        .place-order-btn {
            width: 100%;
            padding: 1rem;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        @media (max-width: 991px) {
            .order-summary {
                position: static;
                margin-top: 2rem;
            }
        }
        
        @media (max-width: 767px) {
            .checkout-section,
            .order-summary {
                padding: 1.5rem;
            }
            
            .payment-methods {
                flex-direction: column;
            }
            
            .order-item {
                flex-wrap: wrap;
            }
            
            .order-item-total {
                width: 100%;
                text-align: left;
                margin-top: 0.5rem;
            }
        }
        
        .checkout-section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.75rem;
        }
        
        .checkout-section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--primary-color);
        }
        
        .checkout-form .form-control,
        .checkout-form .form-select {
            background-color: white;
            border-color: var(--border-color);
            color: black;
            padding: 0.75rem 1rem;
        }
        
        .checkout-form .form-control:focus,
        .checkout-form .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(30, 136, 229, 0.25);
        }
        
        .checkout-form .form-control::placeholder {
            color: #6c757d;
            opacity: 1;
        }
        
        .checkout-form label {
            margin-bottom: 0.5rem;
        }
        
        .checkout-summary {
            position: sticky;
            top: 2rem;
        }
        
        .cart-items {
            margin-bottom: 1.5rem;
        }
        
        .cart-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .cart-item-image {
            width: 60px;
            height: 60px;
            overflow: hidden;
            border-radius: 8px;
            margin-right: 15px;
        }
        
        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .cart-item-details {
            flex-grow: 1;
        }
        
        .cart-item-title {
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .cart-item-price {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        .cart-item-quantity {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        .cart-item-total {
            font-weight: 600;
            margin-left: 15px;
            text-align: right;
            min-width: 70px;
        }
        
        .cart-totals {
            margin-top: 1.5rem;
            border-top: 1px solid var(--border-color);
            padding-top: 1.5rem;
        }
        
        .totals-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }
        
        .totals-row.grand-total {
            font-size: 1.25rem;
            font-weight: 600;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        
        .payment-methods .form-check {
            padding: 1rem;
            margin-bottom: 0.75rem;
            background-color: var(--dark-surface-1);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .payment-methods .form-check:hover {
            border-color: var(--primary-color);
        }
        
        .payment-methods .form-check-input:checked + .form-check-label {
            color: var(--primary-light);
        }
        
        .payment-methods .form-check-input:checked ~ .payment-description {
            display: block;
        }
        
        .payment-description {
            display: none;
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .order-button {
            margin-top: 1.5rem;
        }
        
        .stock-warning {
            color: #ffc107;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
        
        #billingAddressSection {
            display: none;
        }
    </style>
</head>
<body>
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

    <!-- Checkout Content -->
    <div class="container main-container">
        <h1 class="mb-4">Checkout</h1>
        
        <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger mb-4">
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success mb-4">
            <?php echo $success_message; ?>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-lg-8">
                <form class="checkout-form" method="post">
                    <!-- Customer Information -->
                    <div class="checkout-section">
                        <h3 class="checkout-section-title">Customer Information</h3>
                        
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="first_name">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required value="<?php echo isset($user_data['first_name']) ? htmlspecialchars($user_data['first_name']) : ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="last_name">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required value="<?php echo isset($user_data['last_name']) ? htmlspecialchars($user_data['last_name']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="email">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" required value="<?php echo isset($user_data['email']) ? htmlspecialchars($user_data['email']) : ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="phone">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo isset($user_data['phone']) ? htmlspecialchars($user_data['phone']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Shipping Address -->
                    <div class="checkout-section">
                        <h3 class="checkout-section-title">Shipping Address</h3>
                        
                        <div class="mb-3">
                            <label for="shipping_address">Street Address *</label>
                            <input type="text" class="form-control" id="shipping_address" name="shipping_address" required value="<?php echo isset($user_data['address']) ? htmlspecialchars($user_data['address']) : ''; ?>">
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="shipping_city">City *</label>
                                <input type="text" class="form-control" id="shipping_city" name="shipping_city" required value="<?php echo isset($user_data['city']) ? htmlspecialchars($user_data['city']) : ''; ?>">
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <label for="shipping_state">State *</label>
                                <input type="text" class="form-control" id="shipping_state" name="shipping_state" required value="<?php echo isset($user_data['state']) ? htmlspecialchars($user_data['state']) : ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="shipping_zip">ZIP Code *</label>
                                <input type="text" class="form-control" id="shipping_zip" name="shipping_zip" required value="<?php echo isset($user_data['zip_code']) ? htmlspecialchars($user_data['zip_code']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="same_billing" name="same_billing" checked>
                            <label class="form-check-label" for="same_billing">
                                Billing address is the same as shipping address
                            </label>
                        </div>
                    </div>
                    
                    <!-- Billing Address (hidden by default) -->
                    <div class="checkout-section" id="billingAddressSection">
                        <h3 class="checkout-section-title">Billing Address</h3>
                        
                        <div class="mb-3">
                            <label for="billing_address">Street Address *</label>
                            <input type="text" class="form-control" id="billing_address" name="billing_address">
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="billing_city">City *</label>
                                <input type="text" class="form-control" id="billing_city" name="billing_city">
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <label for="billing_state">State *</label>
                                <input type="text" class="form-control" id="billing_state" name="billing_state">
                            </div>
                            <div class="col-md-3">
                                <label for="billing_zip">ZIP Code *</label>
                                <input type="text" class="form-control" id="billing_zip" name="billing_zip">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Method -->
                    <div class="checkout-section">
                        <h3 class="checkout-section-title">Payment Method</h3>
                        
                        <div class="payment-methods">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="payment_credit" value="credit_card" checked>
                                <label class="form-check-label" for="payment_credit">
                                    Credit Card
                                </label>
                                <div class="payment-description">
                                    <p>Pay securely with your credit card. We accept Visa, MasterCard, American Express, and Discover.</p>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="card_number">Card Number</label>
                                            <input type="text" class="form-control" id="card_number" placeholder="**** **** **** ****">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="card_expiry">Expiry</label>
                                            <input type="text" class="form-control" id="card_expiry" placeholder="MM/YY">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="card_cvv">CVV</label>
                                            <input type="text" class="form-control" id="card_cvv" placeholder="***">
                                        </div>
                                    </div>
                                    <div class="text-muted">Note: This is a demo site. No actual payment will be processed.</div>
                                </div>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="payment_paypal" value="paypal">
                                <label class="form-check-label" for="payment_paypal">
                                    PayPal
                                </label>
                                <div class="payment-description">
                                    <p>You will be redirected to PayPal to complete your payment.</p>
                                    <div class="text-muted">Note: This is a demo site. No actual payment will be processed.</div>
                                </div>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="payment_cash" value="cash_on_delivery">
                                <label class="form-check-label" for="payment_cash">
                                    Cash on Delivery
                                </label>
                                <div class="payment-description">
                                    <p>Pay with cash when your order is delivered.</p>
                                    <div class="text-muted">Note: This option is only available for certain locations.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Button -->
                    <div class="order-button">
                        <button type="submit" name="place_order" class="btn btn-primary btn-lg w-100">Place Order</button>
                    </div>
                </form>
            </div>
            
            <div class="col-lg-4">
                <div class="checkout-summary checkout-section">
                    <h3 class="checkout-section-title">Order Summary</h3>
                    
                    <div class="cart-items">
                        <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <div class="cart-item-image">
                                <img src="<?php echo !empty($item['image']) ? $item['image'] : 'images/placeholder.jpg'; ?>" alt="<?php echo $item['name']; ?>">
                            </div>
                            <div class="cart-item-details">
                                <div class="cart-item-title"><?php echo $item['name']; ?></div>
                                <div class="cart-item-price">$<?php echo number_format($item['price'], 2); ?></div>
                                <div class="cart-item-quantity">Qty: <?php echo $item['quantity']; ?></div>
                                <?php if (isset($item['stock_warning'])): ?>
                                <div class="stock-warning">Quantity adjusted due to stock limitations</div>
                                <?php endif; ?>
                            </div>
                            <div class="cart-item-total">
                                $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="cart-totals">
                        <div class="totals-row">
                            <div>Subtotal</div>
                            <div>$<?php echo number_format($subtotal, 2); ?></div>
                        </div>
                        <div class="totals-row">
                            <div>Shipping</div>
                            <div>$<?php echo number_format($shipping_cost, 2); ?></div>
                        </div>
                        <div class="totals-row">
                            <div>Tax (7%)</div>
                            <div>$<?php echo number_format($tax_amount, 2); ?></div>
                        </div>
                        <div class="totals-row grand-total">
                            <div>Total</div>
                            <div>$<?php echo number_format($order_total, 2); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Continue Shopping
                    </a>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle billing address toggle
            const sameBillingCheckbox = document.getElementById('same_billing');
            const billingAddressSection = document.getElementById('billingAddressSection');
            
            sameBillingCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    billingAddressSection.style.display = 'none';
                } else {
                    billingAddressSection.style.display = 'block';
                }
            });
            
            // Handle payment method radios
            const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
            paymentMethods.forEach(method => {
                method.addEventListener('change', function() {
                    // Hide all payment descriptions
                    document.querySelectorAll('.payment-description').forEach(desc => {
                        desc.style.display = 'none';
                    });
                    
                    // Show selected payment description
                    if (this.checked) {
                        this.closest('.form-check').querySelector('.payment-description').style.display = 'block';
                    }
                });
            });
            
            // Show initial payment description
            document.querySelector('input[name="payment_method"]:checked')
                .closest('.form-check').querySelector('.payment-description').style.display = 'block';
        });
    </script>
</body>
</html>
