<?php
session_start();
require_once __DIR__ . '/applications/config.php';

// Check login status
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';

// Check for age verification
$age_verified = isset($_SESSION['age_verified']) && $_SESSION['age_verified'] === true;

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id <= 0) {
    header('Location: index.php');
    exit;
}

// Get product details
$product_query = "SELECT p.*, c.name as category_name 
                 FROM products p 
                 LEFT JOIN categories c ON p.category_id = c.category_id 
                 WHERE p.product_id = $product_id";
$product_result = $conn->query($product_query);

if ($product_result->num_rows === 0) {
    header('Location: index.php');
    exit;
}

$product = $product_result->fetch_assoc();

// Get product images
$images_query = "SELECT * FROM product_images WHERE product_id = $product_id ORDER BY is_primary DESC";
$images_result = $conn->query($images_query);
$product_images = $images_result->fetch_all(MYSQLI_ASSOC);

// Get primary image
$primary_image = 'images/placeholder.jpg';
foreach ($product_images as $image) {
    if ($image['is_primary']) {
        $primary_image = $image['image_path'];
        break;
    }
}

// Get product tags
$tags_query = "SELECT t.* FROM tags t 
              JOIN product_tags pt ON t.tag_id = pt.tag_id 
              WHERE pt.product_id = $product_id";
$tags_result = $conn->query($tags_query);
$product_tags = $tags_result->fetch_all(MYSQLI_ASSOC);

// Get related products (same category)
$related_query = "SELECT p.*, 
                 (SELECT image_path FROM product_images WHERE product_id = p.product_id AND is_primary = 1 LIMIT 1) as primary_image 
                 FROM products p 
                 WHERE p.category_id = {$product['category_id']} AND p.product_id != $product_id 
                 ORDER BY p.created_at DESC 
                 LIMIT 4";
$related_result = $conn->query($related_query);
$related_products = $related_result->fetch_all(MYSQLI_ASSOC);

// Handle add to cart
if (isset($_POST['add_to_cart'])) {
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    // Initialize cart if it doesn't exist
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Add to cart
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id]['quantity'] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = [
            'id' => $product_id,
            'name' => $product['name'],
            'price' => !empty($product['sale_price']) ? $product['sale_price'] : $product['price'],
            'quantity' => $quantity,
            'image' => $primary_image
        ];
    }
    
    // Redirect to prevent form resubmission
    header("Location: product.php?id=$product_id&added=1");
    exit;
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

// Calculate cart total for header
$cart_total = 0;
$cart_items_count = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_total += $item['price'] * $item['quantity'];
        $cart_items_count += $item['quantity'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product['name']; ?> - Your App Your Data</title>
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

    <!-- Added to Cart Alert -->
    <?php if (isset($_GET['added']) && $_GET['added'] == 1): ?>
    <div class="alert alert-success alert-dismissible fade show cart-alert" role="alert">
        <strong>Success!</strong> Item added to your cart.
        <a href="#" class="btn btn-sm btn-success ms-3" data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas">View Cart</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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

    <!-- Product Content -->
    <div class="container main-container">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <?php if (!empty($product['category_name'])): ?>
                <li class="breadcrumb-item"><a href="index.php?category=<?php echo $product['category_id']; ?>"><?php echo $product['category_name']; ?></a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active"><?php echo $product['name']; ?></li>
            </ol>
        </nav>
        
        <div class="row">
            <!-- Product Gallery -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-content">
                        <div style="aspect-ratio: 1; overflow: hidden; border-radius: var(--radius); background-color: var(--muted); display: flex; align-items: center; justify-content: center;">
                            <img src="<?php echo $primary_image; ?>" alt="<?php echo $product['name']; ?>" id="mainProductImage" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                        </div>
                        
                        <?php if (count($product_images) > 1): ?>
                        <div class="product-thumbnails mt-3">
                            <div class="d-flex gap-2 justify-content-center flex-wrap">
                                <?php foreach ($product_images as $index => $image): ?>
                                <div class="thumbnail <?php echo $image['is_primary'] ? 'active' : ''; ?>" 
                                     data-image="<?php echo $image['image_path']; ?>" 
                                     style="width: 80px; height: 80px; border-radius: var(--radius); overflow: hidden; cursor: pointer; border: 2px solid <?php echo $image['is_primary'] ? 'var(--primary)' : 'var(--border)'; ?>; flex-shrink: 0; transition: all 0.3s ease;">
                                    <img src="<?php echo $image['image_path']; ?>" 
                                         alt="<?php echo $product['name']; ?> thumbnail <?php echo $index + 1; ?>" 
                                         style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-2">
                                <small class="text-muted"><?php echo count($product_images); ?> of 5 images</small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Product Details -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-content">
                    <h1 class="product-title"><?php echo $product['name']; ?></h1>
                    
                    <div class="product-category">
                        <a href="index.php?category=<?php echo $product['category_id']; ?>"><?php echo $product['category_name']; ?></a>
                    </div>
                    
                    <!-- Product Price -->
                    <div class="product-price">
                        <?php if (!empty($product['sale_price'])): ?>
                        <span class="regular-price original">$<?php echo number_format($product['price'], 2); ?></span>
                        <span class="sale-price">$<?php echo number_format($product['sale_price'], 2); ?></span>
                        <?php else: ?>
                        <span class="regular-price">$<?php echo number_format($product['price'], 2); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Stock Status -->
                    <div class="product-status">
                        <?php if ($product['quantity'] > 10): ?>
                        <span class="status-badge in-stock">In Stock</span>
                        <?php elseif ($product['quantity'] > 0): ?>
                        <span class="status-badge low-stock">Low Stock</span>
                        <small class="text-muted">Only <?php echo $product['quantity']; ?> left</small>
                        <?php else: ?>
                        <span class="status-badge out-of-stock">Out of Stock</span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Product Description -->
                    <div class="product-description">
                        <p><?php echo nl2br($product['description']); ?></p>
                    </div>
                    
                    <!-- Product Tags -->
                    <?php if (!empty($product_tags)): ?>
                    <div class="product-tags">
                        <?php foreach ($product_tags as $tag): ?>
                        <a href="index.php?tag=<?php echo $tag['tag_id']; ?>" class="product-tag"><?php echo $tag['name']; ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Add to Cart -->
                    <?php if ($product['quantity'] > 0): ?>
                    <form method="post" class="product-actions">
                        <div class="quantity-selector">
                            <span class="quantity-label">Quantity:</span>
                            <div class="quantity-input">
                                <input type="number" name="quantity" class="form-control" value="1" min="1" max="<?php echo $product['quantity']; ?>">
                            </div>
                        </div>
                        
                        <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                        <button type="submit" name="add_to_cart" class="btn btn-primary btn-lg">
                            <i class="bi bi-cart-plus"></i> Add to Cart
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-secondary">
                        This item is currently out of stock. Please check back later.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Product Specifications -->
        <div class="product-specifications">
            <h3 class="mb-3">Specifications</h3>
            <div class="specifications-content">
                    <table class="specs-table">
                        <tr>
                            <td>Product Name</td>
                            <td><?php echo $product['name']; ?></td>
                        </tr>
                        <?php if (!empty($product['sku'])): ?>
                        <tr>
                            <td>SKU</td>
                            <td><?php echo $product['sku']; ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td>Category</td>
                            <td><?php echo $product['category_name']; ?></td>
                        </tr>
                        <?php if (!empty($product['material'])): ?>
                        <tr>
                            <td>Material</td>
                            <td><?php echo $product['material']; ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($product['color'])): ?>
                        <tr>
                            <td>Color</td>
                            <td><?php echo $product['color']; ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($product['dimensions'])): ?>
                        <tr>
                            <td>Dimensions</td>
                            <td><?php echo $product['dimensions']; ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($product['weight'])): ?>
                        <tr>
                            <td>Weight</td>
                            <td><?php echo $product['weight']; ?> g</td>
                        </tr>
                        <?php endif; ?>
                    </table>
            </div>
        </div>
        
        <!-- Related Products -->
        <?php if (!empty($related_products)): ?>
        <div class="related-products">
            <h2 class="related-title">Related Products</h2>
            
            <div class="row">
                <?php foreach ($related_products as $related): ?>
                <div class="col-md-3 mb-4">
                    <div class="product-card">
                        <?php if (!empty($related['sale_price'])): ?>
                        <div class="product-badge sale">Sale</div>
                        <?php endif; ?>
                        
                        <a href="product.php?id=<?php echo $related['product_id']; ?>" class="product-link">
                            <img src="<?php echo !empty($related['primary_image']) ? $related['primary_image'] : 'images/placeholder.jpg'; ?>" 
                                alt="<?php echo $related['name']; ?>" class="product-image">
                        </a>
                        <div class="product-info">
                            <h3 class="product-title">
                                <a href="product.php?id=<?php echo $related['product_id']; ?>">
                                    <?php echo $related['name']; ?>
                                </a>
                            </h3>
                            <div class="product-price">
                                <?php if (!empty($related['sale_price'])): ?>
                                <span class="regular-price original">$<?php echo number_format($related['price'], 2); ?></span>
                                <span class="sale-price">$<?php echo number_format($related['sale_price'], 2); ?></span>
                                <?php else: ?>
                                <span class="regular-price">$<?php echo number_format($related['price'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <form method="post" action="product.php?id=<?php echo $related['product_id']; ?>">
                                <input type="hidden" name="product_id" value="<?php echo $related['product_id']; ?>">
                                <input type="hidden" name="quantity" value="1">
                                <div class="product-actions">
                                    <button type="submit" name="add_to_cart" class="btn btn-primary btn-sm">
                                        <i class="bi bi-cart-plus"></i> Add to Cart
                                    </button>
                                    <a href="product.php?id=<?php echo $related['product_id']; ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle product image gallery
            const thumbnails = document.querySelectorAll('.thumbnail');
            const mainImage = document.getElementById('mainProductImage');
            
            thumbnails.forEach(thumbnail => {
                thumbnail.addEventListener('click', function() {
                    // Update active thumbnail
                    thumbnails.forEach(t => {
                        t.classList.remove('active');
                        t.style.border = '2px solid var(--border)';
                    });
                    this.classList.add('active');
                    this.style.border = '2px solid var(--primary)';
                    
                    // Update main image
                    mainImage.src = this.dataset.image;
                });
            });
            
            // Auto dismiss added to cart alert
            const alertElement = document.querySelector('.cart-alert');
            if (alertElement) {
                setTimeout(() => {
                    const alert = bootstrap.Alert.getOrCreateInstance(alertElement);
                    alert.close();
                }, 5000);
            }
        });
    </script>
</body>
</html>
