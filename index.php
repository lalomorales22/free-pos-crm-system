<?php
session_start();
require_once __DIR__ . '/applications/config.php';

// Check login status
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';

// Handle logout
if (isset($_GET['logout'])) {
    $cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
    $age_verified = isset($_SESSION['age_verified']) ? $_SESSION['age_verified'] : false;
    $_SESSION = [];
    $_SESSION['cart'] = $cart;
    $_SESSION['age_verified'] = $age_verified;
    header('Location: index.php');
    exit;
}

// Check for age verification
$age_verified = isset($_SESSION['age_verified']) && $_SESSION['age_verified'] === true;

// Get all categories for sidebar
$categories_query = "SELECT c.*, COUNT(p.product_id) as product_count 
                   FROM categories c 
                   LEFT JOIN products p ON c.category_id = p.category_id 
                   WHERE c.parent_id IS NULL 
                   GROUP BY c.category_id 
                   ORDER BY c.name";
$categories_result = $conn->query($categories_query);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Get subcategories
$subcategories = [];
if (!empty($categories)) {
    foreach ($categories as $category) {
        $subcat_query = "SELECT c.*, COUNT(p.product_id) as product_count 
                        FROM categories c 
                        LEFT JOIN products p ON c.category_id = p.category_id 
                        WHERE c.parent_id = " . $category['category_id'] . " 
                        GROUP BY c.category_id 
                        ORDER BY c.name";
        $subcat_result = $conn->query($subcat_query);
        if ($subcat_result->num_rows > 0) {
            $subcategories[$category['category_id']] = $subcat_result->fetch_all(MYSQLI_ASSOC);
        }
    }
}

// Get all tags for filtering
$tags_query = "SELECT * FROM tags ORDER BY name";
$tags_result = $conn->query($tags_query);
$tags = $tags_result->fetch_all(MYSQLI_ASSOC);

// Handle filters
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$category_name = '';
$tag_filter = isset($_GET['tag']) ? intval($_GET['tag']) : 0;
$tag_name = '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) && $_GET['min_price'] !== '' ? floatval($_GET['min_price']) : null;
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) && $_GET['max_price'] !== '' ? floatval($_GET['max_price']) : null;

// Build product query based on filters
$products_query = "SELECT p.*, c.name as category_name, 
                 (SELECT image_path FROM product_images WHERE product_id = p.product_id AND is_primary = 1 LIMIT 1) as primary_image 
                 FROM products p 
                 LEFT JOIN categories c ON p.category_id = c.category_id";
$where_clauses = [];
$current_query_params = $_GET; // Used for building filter links and preserving state

if ($category_filter > 0) {
    $cat_name_query = "SELECT name FROM categories WHERE category_id = $category_filter";
    $cat_name_result = $conn->query($cat_name_query);
    if ($cat_name_result->num_rows > 0) {
        $category_name = $cat_name_result->fetch_assoc()['name'];
    }
    $subcategory_ids_for_filter = [];
    $subcat_filter_query = "SELECT category_id FROM categories WHERE parent_id = $category_filter";
    $subcat_filter_result = $conn->query($subcat_filter_query);
    if ($subcat_filter_result->num_rows > 0) {
        while ($subcat = $subcat_filter_result->fetch_assoc()) {
            $subcategory_ids_for_filter[] = $subcat['category_id'];
        }
    }
    if (!empty($subcategory_ids_for_filter)) {
        $where_clauses[] = "(p.category_id = $category_filter OR p.category_id IN (" . implode(',', $subcategory_ids_for_filter) . "))";
    } else {
        $where_clauses[] = "p.category_id = $category_filter";
    }
}

if ($tag_filter > 0) {
    $tag_name_query = "SELECT name FROM tags WHERE tag_id = $tag_filter";
    $tag_name_result = $conn->query($tag_name_query);
    if ($tag_name_result->num_rows > 0) {
        $tag_name = $tag_name_result->fetch_assoc()['name'];
    }
    if (strpos($products_query, 'product_tags pt') === false) {
        $products_query .= " JOIN product_tags pt ON p.product_id = pt.product_id";
    }
    $where_clauses[] = "pt.tag_id = $tag_filter";
}

if (!empty($search_term)) {
    $escaped_search_term = mysqli_real_escape_string($conn, $search_term);
    $where_clauses[] = "(p.name LIKE '%$escaped_search_term%' OR p.description LIKE '%$escaped_search_term%')";
}

if ($min_price !== null) {
    $where_clauses[] = "COALESCE(p.sale_price, p.price) >= $min_price";
}
if ($max_price !== null) {
    $where_clauses[] = "COALESCE(p.sale_price, p.price) <= $max_price";
}

if (!empty($where_clauses)) {
    $products_query .= " WHERE " . implode(' AND ', $where_clauses);
}

$products_query .= " ORDER BY p.featured DESC, p.created_at DESC";
$products_result = $conn->query($products_query);
$products = [];
if ($products_result) {
    $products = $products_result->fetch_all(MYSQLI_ASSOC);
}

// Get featured products for right sidebar
$featured_query = "SELECT p.*, c.name as category_name, 
                 (SELECT image_path FROM product_images WHERE product_id = p.product_id AND is_primary = 1 LIMIT 1) as primary_image 
                 FROM products p 
                 LEFT JOIN categories c ON p.category_id = c.category_id 
                 WHERE p.featured = 1 
                 ORDER BY p.created_at DESC 
                 LIMIT 6";
$featured_result = $conn->query($featured_query);
$featured_products = $featured_result->fetch_all(MYSQLI_ASSOC);

// Get Top Sellers for right sidebar
$top_sellers_query = "SELECT p.*, c.name as category_name,
                        (SELECT image_path FROM product_images WHERE product_id = p.product_id AND is_primary = 1 LIMIT 1) as primary_image,
                        SUM(oi.quantity) AS total_sold
                     FROM products p
                     LEFT JOIN categories c ON p.category_id = c.category_id
                     JOIN order_items oi ON p.product_id = oi.product_id
                     GROUP BY p.product_id
                     ORDER BY total_sold DESC
                     LIMIT 6";
$top_sellers_result = $conn->query($top_sellers_query);
$top_sellers = $top_sellers_result->fetch_all(MYSQLI_ASSOC);

// --- Cart Handling ---
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

function get_redirect_url_with_filters($base_page = 'index.php') {
    $params_to_keep = ['category', 'tag', 'search', 'min_price', 'max_price'];
    $filtered_get = array_intersect_key($_GET, array_flip($params_to_keep));
    // Remove empty parameters to keep URL clean
    $filtered_get = array_filter($filtered_get, function($value) { return $value !== null && $value !== ''; });
    if (!empty($filtered_get)) {
        return $base_page . '?' . http_build_query($filtered_get);
    }
    return $base_page;
}

if (isset($_POST['add_to_cart']) && isset($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $check_query = "SELECT product_id, name, price, sale_price FROM products WHERE product_id = $product_id";
    $check_result = $conn->query($check_query);
    if ($check_result->num_rows > 0) {
        $product_data = $check_result->fetch_assoc();
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = [
                'id' => $product_id,
                'name' => $product_data['name'],
                'price' => !empty($product_data['sale_price']) ? $product_data['sale_price'] : $product_data['price'],
                'quantity' => $quantity,
                'image' => ''
            ];
            $image_query = "SELECT image_path FROM product_images WHERE product_id = $product_id AND is_primary = 1 LIMIT 1";
            $image_result = $conn->query($image_query);
            if ($image_result->num_rows > 0) {
                $_SESSION['cart'][$product_id]['image'] = $image_result->fetch_assoc()['image_path'];
            }
        }
        header("Location: " . get_redirect_url_with_filters());
        exit;
    }
}
if (isset($_GET['remove_from_cart']) && isset($_SESSION['cart'][$_GET['remove_from_cart']])) {
    unset($_SESSION['cart'][$_GET['remove_from_cart']]);
    header("Location: " . get_redirect_url_with_filters());
    exit;
}
if (isset($_POST['update_cart'])) {
    foreach ($_POST['cart_quantity'] as $product_id => $quantity) {
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] = max(1, intval($quantity));
        }
    }
    header("Location: " . get_redirect_url_with_filters());
    exit;
}
// --- End Cart Handling ---

// Age verification (same logic as before)
if (isset($_POST['verify_age'])) {
    if (isset($_POST['confirm_age']) && $_POST['confirm_age'] === 'yes') {
        $_SESSION['age_verified'] = true;
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $conn->query("UPDATE users SET age_verified = 1 WHERE user_id = $user_id");
        }
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_id_to_log = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : "NULL";
        $conn->query("INSERT INTO age_verification_attempts (user_id, ip_address, attempt_successful, details, attempted_at) VALUES ($user_id_to_log, '$ip', 1, 'User confirmed age via popup (index.php)', NOW())");
        header('Location: index.php'); // Refresh page
        exit;
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_id_to_log = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : "NULL";
        $conn->query("INSERT INTO age_verification_attempts (user_id, ip_address, attempt_successful, details, attempted_at) VALUES ($user_id_to_log, '$ip', 0, 'User did not confirm age via popup or exited (index.php)', NOW())");
        header("Location: age-restriction.php");
        exit;
    }
}

// Calculate cart total
$cart_total = 0;
$cart_items_count = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_total += $item['price'] * $item['quantity'];
        $cart_items_count += $item['quantity'];
    }
}

// Function to build query string for filter links, preserving other active filters
function build_filter_link($params_to_add_or_update) {
    global $current_query_params;
    $merged_params = array_merge($current_query_params, $params_to_add_or_update);
    foreach ($params_to_add_or_update as $key => $value) {
        if ($value === null || $value === '' || (is_numeric($value) && $value == 0) ) {
            unset($merged_params[$key]);
        }
    }
    $merged_params = array_filter($merged_params, function($value) { return $value !== null && $value !== ''; });
    return 'index.php?' . http_build_query($merged_params);
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your App Your Data - Free POS &amp; CRM Toolkit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php if (!$age_verified): ?>
    <div class="age-verification-overlay" id="ageVerificationModal">
        <div class="age-verification-modal">
            <div class="age-verification-content">
                <img src="images/icon.png" alt="Your App Your Data emblem" class="age-verification-logo">
                <h2>Welcome to Your App Your Data</h2>
                <p>Spin up a free, modern POS + CRM sandbox that keeps every action and insight under your control.</p>
                <p>Before you dive in, please confirm that you understand this workspace is a demo environment and any sample data lives on your device.</p>
                <form method="post" action="index.php" class="age-verification-form">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmAgeModal" name="confirm_age" value="yes" required>
                        <label class="form-check-label" for="confirmAgeModal">I understand this sandbox is for testing and stores information locally.</label>
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

    <div class="container main-container">
        <?php if ($is_logged_in): ?>
        <div class="card mb-4">
            <div class="card-content">
                <h4>Welcome back, <?php echo htmlspecialchars($username); ?>!</h4>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($is_admin): ?>
                    <a href="backend.php" class="btn btn-primary btn-sm"><i class="bi bi-gear"></i> Admin Dashboard</a>
                    <?php endif; ?>
                    <a href="account.php" class="btn btn-secondary btn-sm"><i class="bi bi-person"></i> My Account</a>
                    <a href="orders.php" class="btn btn-secondary btn-sm"><i class="bi bi-box"></i> My Orders</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Left Sidebar - Categories, Search, Filters -->
            <div class="col-lg-3">
                <div class="sidebar">
                    <div class="sidebar-section">
                         <h4>Search Products</h4>
                        <form action="index.php" method="get" class="sidebar-search-form">
                            <div class="input-group mb-2">
                                <input type="text" class="form-control" name="search" placeholder="Enter keyword..." value="<?php echo htmlspecialchars($search_term); ?>">
                            </div>
                             <?php if ($category_filter): ?><input type="hidden" name="category" value="<?php echo $category_filter; ?>"><?php endif; ?>
                             <?php if ($tag_filter): ?><input type="hidden" name="tag" value="<?php echo $tag_filter; ?>"><?php endif; ?>
                             <?php if ($min_price !== null): ?><input type="hidden" name="min_price" value="<?php echo htmlspecialchars($min_price); ?>"><?php endif; ?>
                             <?php if ($max_price !== null): ?><input type="hidden" name="max_price" value="<?php echo htmlspecialchars($max_price); ?>"><?php endif; ?>
                            <button class="btn btn-primary btn-sm w-100" type="submit"><i class="bi bi-search"></i> Search</button>
                        </form>
                    </div>

                    <div class="sidebar-section">
                        <h4>Categories</h4>
                        <ul class="sidebar-categories">
                            <li class="<?php echo ($category_filter == 0) ? 'active' : ''; ?>">
                                <a href="<?php echo build_filter_link(['category' => null]); ?>">All Categories</a>
                            </li>
                            <?php foreach ($categories as $category_item): ?>
                            <li class="<?php echo ($category_filter == $category_item['category_id']) ? 'active' : ''; ?>">
                                <a href="<?php echo build_filter_link(['category' => $category_item['category_id']]); ?>">
                                    <?php echo htmlspecialchars($category_item['name']); ?>
                                    <span class="count"><?php echo $category_item['product_count']; ?></span>
                                </a>
                                <?php if (isset($subcategories[$category_item['category_id']])): ?>
                                <ul class="sidebar-subcategories">
                                    <?php foreach ($subcategories[$category_item['category_id']] as $subcategory_item): ?>
                                    <li class="<?php echo ($category_filter == $subcategory_item['category_id']) ? 'active' : ''; ?>">
                                        <a href="<?php echo build_filter_link(['category' => $subcategory_item['category_id']]); ?>">
                                            <?php echo htmlspecialchars($subcategory_item['name']); ?>
                                            <span class="count"><?php echo $subcategory_item['product_count']; ?></span>
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <div class="sidebar-section">
                        <h4>Filter by Tag</h4>
                        <div class="tag-filter">
                            <a href="<?php echo build_filter_link(['tag' => null]); ?>" 
                               class="tag-badge <?php echo ($tag_filter == 0) ? 'active' : ''; ?>">All Tags</a>
                            <?php foreach ($tags as $tag_item): ?>
                            <a href="<?php echo build_filter_link(['tag' => $tag_item['tag_id']]); ?>" 
                               class="tag-badge <?php echo ($tag_filter == $tag_item['tag_id']) ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($tag_item['name']); ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="sidebar-section">
                        <h4>Price Range</h4>
                        <form action="index.php" method="get" class="price-filter">
                             <?php if ($category_filter): ?><input type="hidden" name="category" value="<?php echo $category_filter; ?>"><?php endif; ?>
                             <?php if ($tag_filter): ?><input type="hidden" name="tag" value="<?php echo $tag_filter; ?>"><?php endif; ?>
                             <?php if ($search_term): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>"><?php endif; ?>
                            <div class="input-group mb-2">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="min_price" placeholder="Min" min="0" step="any"
                                       value="<?php echo $min_price !== null ? htmlspecialchars($min_price) : ''; ?>">
                            </div>
                            <div class="input-group mb-3">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="max_price" placeholder="Max" min="0" step="any"
                                       value="<?php echo $max_price !== null ? htmlspecialchars($max_price) : ''; ?>">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm w-100">Apply Price</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Center - Products Grid -->
            <div class="col-lg-6">
                <div class="filter-header">
                    <h2>
                        <?php 
                        if (!empty($category_name)) {
                            echo htmlspecialchars($category_name);
                        } elseif (!empty($tag_name)) {
                            echo "Tagged: " . htmlspecialchars($tag_name);
                        } elseif (!empty($search_term)) {
                            echo "Search: \"" . htmlspecialchars($search_term) . "\"";
                        } else {
                            echo "All Products";
                        }
                        ?>
                    </h2>
                    <p class="active-filters-text">
                        <?php
                        $active_filter_parts = [];
                        if (!empty($category_name) && empty($search_term) && empty($tag_name)) { /* Already shown in H2 */ }
                        elseif (!empty($category_name)) { $active_filter_parts[] = "Category: " . htmlspecialchars($category_name); }

                        if (!empty($tag_name) && empty($search_term) && empty($category_name)) { /* Already shown in H2 */ }
                        elseif(!empty($tag_name)) { $active_filter_parts[] = "Tag: " . htmlspecialchars($tag_name); }
                        
                        if ($min_price !== null) { $active_filter_parts[] = "Min: $" . htmlspecialchars($min_price); }
                        if ($max_price !== null) { $active_filter_parts[] = "Max: $" . htmlspecialchars($max_price); }

                        if (!empty($active_filter_parts)) {
                            echo "Active filters: " . implode(' &middot; ', $active_filter_parts) . ". ";
                        }
                        echo "Found " . count($products) . " product(s).";
                        ?>
                    </p>
                     <?php if (!empty($category_filter) || !empty($tag_filter) || !empty($search_term) || $min_price !== null || $max_price !== null): ?>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm btn-clear-filters">
                            <i class="bi bi-x-lg"></i> Clear All Filters
                        </a>
                    <?php endif; ?>
                </div>
                
                <section class="products-grid">
                    <?php if (!empty($products)): ?>
                    <div class="row row-cols-1 row-cols-md-2 g-4">
                        <?php foreach ($products as $product): ?>
                        <div class="col">
                            <div class="product-card">
                                <?php if (!empty($product['sale_price'])): ?>
                                <div class="product-badge sale">Sale</div>
                                <?php elseif ($product['featured'] == 1): ?>
                                <div class="product-badge">Featured</div>
                                <?php endif; ?>
                                
                                <a href="product.php?id=<?php echo $product['product_id']; ?>" class="product-link">
                                    <img src="<?php echo (!empty($product['primary_image']) && file_exists(trim($product['primary_image'], '/'))) ? $product['primary_image'] : 'images/placeholder.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                                </a>
                                <div class="product-info">
                                    <h3 class="product-title">
                                        <a href="product.php?id=<?php echo $product['product_id']; ?>">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </a>
                                    </h3>
                                    <div class="product-category">
                                        <?php echo !empty($product['category_name']) ? htmlspecialchars($product['category_name']) : 'Uncategorized'; ?>
                                    </div>
                                    <div class="product-price">
                                        <?php if (!empty($product['sale_price'])): ?>
                                        <span class="regular-price original">$<?php echo number_format($product['price'], 2); ?></span>
                                        <span class="sale-price">$<?php echo number_format($product['sale_price'], 2); ?></span>
                                        <?php else: ?>
                                        <span class="regular-price">$<?php echo number_format($product['price'], 2); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <form method="post" action="<?php echo get_redirect_url_with_filters(); ?>">
                                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                        <div class="product-actions">
                                            <button type="submit" name="add_to_cart" class="btn btn-primary btn-sm">
                                                <i class="bi bi-cart-plus"></i> Add to Cart
                                            </button>
                                            <a href="product.php?id=<?php echo $product['product_id']; ?>" class="btn btn-outline-secondary btn-sm">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle-fill me-2"></i> No products found matching your criteria. Please try adjusting your filters or search term.
                    </div>
                    <?php endif; ?>
                </section>
            </div>
            
            <!-- Right Sidebar - Featured & Top Sellers -->
            <div class="col-lg-3">
                <?php if (!empty($featured_products)): ?>
                <div class="sidebar mb-4">
                    <div class="sidebar-section">
                        <h4>Featured Products</h4>
                        <?php foreach ($featured_products as $product): ?>
                        <div class="d-flex mb-3 pb-3 border-bottom">
                            <div class="flex-shrink-0">
                                <a href="product.php?id=<?php echo $product['product_id']; ?>">
                                    <img src="<?php echo (!empty($product['primary_image']) && file_exists(trim($product['primary_image'], '/'))) ? $product['primary_image'] : 'images/placeholder.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 5px;">
                                </a>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1">
                                    <a href="product.php?id=<?php echo $product['product_id']; ?>" class="text-light text-decoration-none">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </a>
                                </h6>
                                <div class="text-muted small mb-1">
                                    <?php echo !empty($product['category_name']) ? htmlspecialchars($product['category_name']) : 'Uncategorized'; ?>
                                </div>
                                <div class="fw-bold">
                                    <?php if (!empty($product['sale_price'])): ?>
                                    <span class="text-muted text-decoration-line-through small">$<?php echo number_format($product['price'], 2); ?></span>
                                    <span class="text-info ms-1">$<?php echo number_format($product['sale_price'], 2); ?></span>
                                    <?php else: ?>
                                    <span>$<?php echo number_format($product['price'], 2); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($top_sellers)): ?>
                <div class="sidebar">
                    <div class="sidebar-section">
                        <h4>Top Sellers</h4>
                        <?php foreach ($top_sellers as $product): ?>
                        <div class="d-flex mb-3 pb-3 border-bottom">
                            <div class="flex-shrink-0">
                                <a href="product.php?id=<?php echo $product['product_id']; ?>">
                                    <img src="<?php echo (!empty($product['primary_image']) && file_exists(trim($product['primary_image'], '/'))) ? $product['primary_image'] : 'images/placeholder.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 5px;">
                                </a>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1">
                                    <a href="product.php?id=<?php echo $product['product_id']; ?>" class="text-light text-decoration-none">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </a>
                                </h6>
                                <div class="text-muted small mb-1">
                                    <?php echo !empty($product['category_name']) ? htmlspecialchars($product['category_name']) : 'Uncategorized'; ?>
                                </div>
                                <div class="fw-bold">
                                    <?php if (!empty($product['sale_price'])): ?>
                                    <span class="text-muted text-decoration-line-through small">$<?php echo number_format($product['price'], 2); ?></span>
                                    <span class="text-info ms-1">$<?php echo number_format($product['sale_price'], 2); ?></span>
                                    <?php else: ?>
                                    <span>$<?php echo number_format($product['price'], 2); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="offcanvas offcanvas-end" tabindex="-1" id="cartOffcanvas" aria-labelledby="cartOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="cartOffcanvasLabel">Shopping Cart</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <?php if (!empty($_SESSION['cart'])): ?>
            <form method="post" action="<?php echo get_redirect_url_with_filters(); ?>">
                <div class="cart-items">
                    <?php foreach ($_SESSION['cart'] as $item_id => $item): ?>
                    <div class="cart-item">
                        <div class="cart-item-image">
                            <img src="<?php echo (!empty($item['image']) && file_exists(trim($item['image'],'/'))) ? $item['image'] : 'images/placeholder.jpg'; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                        </div>
                        <div class="cart-item-details">
                            <h5 class="cart-item-title"><?php echo htmlspecialchars($item['name']); ?></h5>
                            <div class="cart-item-price">$<?php echo number_format($item['price'], 2); ?></div>
                            <input type="number" name="cart_quantity[<?php echo $item_id; ?>]" value="<?php echo $item['quantity']; ?>" min="1" class="form-control form-control-sm cart-item-quantity">
                        </div>
                        <div class="cart-item-total">
                            $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                        </div>
                        <button type="button" onclick="window.location.href='<?php echo build_filter_link(['remove_from_cart' => $item_id]); ?>'" class="cart-item-remove" title="Remove Item">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="cart-summary mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Subtotal:</span>
                        <strong>$<?php echo number_format($cart_total, 2); ?></strong>
                    </div>
                    <button type="submit" name="update_cart" class="btn btn-outline-primary w-100 mb-2"><i class="bi bi-arrow-repeat"></i> Update Cart</button>
                    <a href="checkout.php" class="btn btn-primary w-100"><i class="bi bi-credit-card"></i> Checkout</a>
                </div>
            </form>
            <?php else: ?>
            <div class="empty-cart">
                <i class="bi bi-cart-x"></i>
                <p>Your cart is empty.</p>
                <a href="index.php" class="btn btn-primary">Start Shopping</a>
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
                        <?php foreach ($categories as $footer_category_item): ?>
                        <li>
                            <a href="<?php echo build_filter_link(['category' => $footer_category_item['category_id']]); ?>">
                                <?php echo htmlspecialchars($footer_category_item['name']); ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                         <li><a href="<?php echo build_filter_link(['category' => null]); ?>">All Categories</a></li>
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
                <p>&copy; <?php echo date('Y'); ?> Your App Your Data. Your systems, your rules.</p>
                <p class="demo-disclaimer">This open sandbox is for demo purposes only—keep backups of anything you love.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- <script src="script.js"></script> --> <!-- If specific JS is needed for index page features -->
</body>
</html>