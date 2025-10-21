<?php
require_once __DIR__ . '/applications/config.php';

if (!isset($_POST['confirm'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Seed Sample Products</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background: #f8f9fa; padding: 40px 0; }
            .card { max-width: 600px; margin: 0 auto; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); }
            .alert-info { background-color: #d1ecf1; border-color: #bee5eb; }
            .product-preview { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="card-header bg-info text-dark">
                    <h3 class="mb-0">📦 Seed Sample Products</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-4">
                        <strong>This will add 25 sample products to your database</strong><br>
                        Products will be distributed across various POS/CRM categories
                    </div>

                    <h5>Sample Products Preview:</h5>
                    <div class="product-preview">
                        <p><strong>POS Terminals:</strong> Premium Touch Terminal, Basic POS Station, etc.</p>
                    </div>
                    <div class="product-preview">
                        <p><strong>Payment Systems:</strong> Stripe Integration, Square Reader, PayPal Pro, etc.</p>
                    </div>
                    <div class="product-preview">
                        <p><strong>Hardware:</strong> Receipt Printers, Barcode Scanners, Cash Drawers, etc.</p>
                    </div>
                    <div class="product-preview">
                        <p><strong>Software/Tools:</strong> Inventory Management, Staff Training Module, Analytics Suite, etc.</p>
                    </div>

                    <p class="text-muted small mt-4">Each product will have:</p>
                    <ul class="text-muted small">
                        <li>Realistic pricing ($49 - $2,500)</li>
                        <li>Sale prices on some items (20-40% off)</li>
                        <li>Relevant descriptions</li>
                        <li>Some marked as featured</li>
                        <li>Proper SKUs and specs</li>
                    </ul>

                    <form method="post" class="mt-4">
                        <button type="submit" name="confirm" value="yes" class="btn btn-info w-100 btn-lg">
                            Add 25 Sample Products
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
} else {
    // Sample products data
    $products = [
        // Point of Sale Terminals
        [
            'name' => 'Premium Touch Terminal 15"',
            'description' => 'High-performance 15-inch touchscreen POS terminal with Intel processor. Perfect for retail, restaurants, and hospitality.',
            'price' => 1299.99,
            'sale_price' => 999.99,
            'category_id' => 1,
            'sku' => 'POS-TOUCH-15',
            'weight' => 12.5,
            'material' => 'Aluminum & Stainless Steel',
            'color' => 'Black',
            'featured' => 1
        ],
        [
            'name' => 'Basic POS Station - White',
            'description' => 'Compact and affordable POS terminal. Great for small businesses and pop-ups.',
            'price' => 499.99,
            'sale_price' => null,
            'category_id' => 1,
            'sku' => 'POS-BASIC-W',
            'weight' => 8.0,
            'material' => 'Plastic',
            'color' => 'White',
            'featured' => 0
        ],
        [
            'name' => 'Enterprise POS with Integrated Thermal Printer',
            'description' => 'All-in-one POS system with built-in receipt printer and barcode scanner. No additional hardware needed.',
            'price' => 1799.99,
            'sale_price' => 1449.99,
            'category_id' => 1,
            'sku' => 'POS-ENT-PRINT',
            'weight' => 15.0,
            'material' => 'Stainless Steel',
            'color' => 'Silver',
            'featured' => 1
        ],
        [
            'name' => 'Mobile POS Terminal - iOS Compatible',
            'description' => 'Portable POS device that connects to iPad or iPhone. Perfect for food trucks, events, and on-the-go sales.',
            'price' => 349.99,
            'sale_price' => null,
            'category_id' => 5,
            'sku' => 'POS-MOBILE-iOS',
            'weight' => 1.2,
            'material' => 'Plastic',
            'color' => 'Black',
            'featured' => 0
        ],

        // Payment Integrations
        [
            'name' => 'Stripe Payment Gateway Integration',
            'description' => 'Complete Stripe integration module. Accept credit cards, digital wallets, and more.',
            'price' => 299.99,
            'sale_price' => 249.99,
            'category_id' => 9,
            'sku' => 'PAY-STRIPE',
            'weight' => 0.5,
            'material' => 'Digital',
            'color' => 'Blue',
            'featured' => 0
        ],
        [
            'name' => 'Square Reader with Mobile Adapter',
            'description' => 'Compact card reader for mobile payments. Works with any smartphone.',
            'price' => 49.99,
            'sale_price' => 39.99,
            'category_id' => 9,
            'sku' => 'PAY-SQUARE',
            'weight' => 0.3,
            'material' => 'Plastic',
            'color' => 'White',
            'featured' => 0
        ],
        [
            'name' => 'PayPal Pro Merchant Account Bundle',
            'description' => 'PayPal Pro setup and integration. Includes fraud protection and settlement tools.',
            'price' => 199.99,
            'sale_price' => null,
            'category_id' => 9,
            'sku' => 'PAY-PAYPAL-PRO',
            'weight' => 0.1,
            'material' => 'Digital',
            'color' => 'Yellow',
            'featured' => 0
        ],

        // Hardware
        [
            'name' => 'Thermal Receipt Printer 80mm',
            'description' => 'Fast 80mm thermal printer for receipts. 150mm/sec print speed. USB and Ethernet connections.',
            'price' => 249.99,
            'sale_price' => 199.99,
            'category_id' => 2,
            'sku' => 'HW-PRINT-80MM',
            'weight' => 4.5,
            'material' => 'Metal & Plastic',
            'color' => 'Black',
            'featured' => 0
        ],
        [
            'name' => 'Wireless Barcode Scanner - Bluetooth',
            'description' => 'Cordless barcode scanner with 30-meter range. Works with all POS systems.',
            'price' => 149.99,
            'sale_price' => 119.99,
            'category_id' => 4,
            'sku' => 'HW-SCAN-BT',
            'weight' => 0.5,
            'material' => 'Plastic',
            'color' => 'Red',
            'featured' => 0
        ],
        [
            'name' => 'Electronic Cash Drawer - 5 Bill Slots',
            'description' => 'Heavy-duty cash drawer with RJ11 interface. Coin and bill storage. 5 compartments.',
            'price' => 399.99,
            'sale_price' => null,
            'category_id' => 3,
            'sku' => 'HW-DRAWER-5',
            'weight' => 8.0,
            'material' => 'Steel',
            'color' => 'Black',
            'featured' => 0
        ],
        [
            'name' => 'Customer Display Screen 7"',
            'description' => 'Customer-facing LCD display showing order total and items. VFD alternative.',
            'price' => 179.99,
            'sale_price' => 149.99,
            'category_id' => 2,
            'sku' => 'HW-DISPLAY-7',
            'weight' => 2.5,
            'material' => 'Plastic & Glass',
            'color' => 'Black',
            'featured' => 0
        ],

        // Software & Services
        [
            'name' => 'Inventory Management Pro',
            'description' => 'Real-time inventory tracking, automated reordering, and multi-location support. Cloud-based.',
            'price' => 89.99,
            'sale_price' => 69.99,
            'category_id' => 6,
            'sku' => 'SOFT-INV-PRO',
            'weight' => 0.1,
            'material' => 'Digital',
            'color' => 'Green',
            'featured' => 1
        ],
        [
            'name' => 'Staff Management & Scheduling',
            'description' => 'Employee scheduling, time tracking, and performance monitoring. Mobile app included.',
            'price' => 119.99,
            'sale_price' => null,
            'category_id' => 10,
            'sku' => 'SOFT-STAFF-MGT',
            'weight' => 0.1,
            'material' => 'Digital',
            'color' => 'Blue',
            'featured' => 0
        ],
        [
            'name' => 'CRM Automations Suite',
            'description' => 'Automated customer follow-ups, email campaigns, and loyalty programs.',
            'price' => 199.99,
            'sale_price' => 159.99,
            'category_id' => 7,
            'sku' => 'SOFT-CRM-AUTO',
            'weight' => 0.1,
            'material' => 'Digital',
            'color' => 'Purple',
            'featured' => 1
        ],
        [
            'name' => 'Advanced Analytics Dashboard',
            'description' => 'Real-time sales analytics, customer insights, and business intelligence reports.',
            'price' => 149.99,
            'sale_price' => null,
            'category_id' => 8,
            'sku' => 'SOFT-ANALYTICS',
            'weight' => 0.1,
            'material' => 'Digital',
            'color' => 'Orange',
            'featured' => 0
        ],
        [
            'name' => 'Email & SMS Marketing Module',
            'description' => 'Send targeted email and SMS campaigns. Track opens, clicks, and conversions.',
            'price' => 79.99,
            'sale_price' => 59.99,
            'category_id' => 12,
            'sku' => 'SOFT-MARKETING',
            'weight' => 0.1,
            'material' => 'Digital',
            'color' => 'Pink',
            'featured' => 0
        ],

        // API & Integration
        [
            'name' => 'REST API Full Documentation',
            'description' => 'Complete REST API with authentication, webhooks, and SDK libraries.',
            'price' => 299.99,
            'sale_price' => 249.99,
            'category_id' => 14,
            'sku' => 'API-REST-DOCS',
            'weight' => 0.1,
            'material' => 'Digital',
            'color' => 'Gray',
            'featured' => 0
        ],
        [
            'name' => 'Shopify Integration Plugin',
            'description' => 'Sync products, orders, and inventory between Shopify and your POS system.',
            'price' => 199.99,
            'sale_price' => null,
            'category_id' => 14,
            'sku' => 'INT-SHOPIFY',
            'weight' => 0.1,
            'material' => 'Digital',
            'color' => 'Green',
            'featured' => 0
        ],
        [
            'name' => 'WooCommerce Connector',
            'description' => 'Full integration with WooCommerce stores. Real-time data sync.',
            'price' => 149.99,
            'sale_price' => 119.99,
            'category_id' => 14,
            'sku' => 'INT-WOOCOMM',
            'weight' => 0.1,
            'material' => 'Digital',
            'color' => 'Purple',
            'featured' => 0
        ],

        // Omnichannel
        [
            'name' => 'Omnichannel Order Management',
            'description' => 'Manage orders from all sales channels. Unified inventory and shipping.',
            'price' => 249.99,
            'sale_price' => 199.99,
            'category_id' => 11,
            'sku' => 'OMNI-ORDERS',
            'weight' => 0.1,
            'material' => 'Digital',
            'color' => 'Blue',
            'featured' => 0
        ],
        [
            'name' => 'Social Commerce Integration',
            'description' => 'Sell directly on Facebook, Instagram, TikTok Shop, and more.',
            'price' => 179.99,
            'sale_price' => null,
            'category_id' => 11,
            'sku' => 'OMNI-SOCIAL',
            'weight' => 0.1,
            'material' => 'Digital',
            'color' => 'Pink',
            'featured' => 0
        ],

        // Support & Services
        [
            'name' => '24/7 Premium Support Package',
            'description' => 'Priority phone, email, and chat support. Maximum 1-hour response time.',
            'price' => 99.99,
            'sale_price' => 79.99,
            'category_id' => 15,
            'sku' => 'SUP-24-7',
            'weight' => 0.1,
            'material' => 'Service',
            'color' => 'Gold',
            'featured' => 0
        ],
        [
            'name' => 'Implementation & Setup Service',
            'description' => 'Professional installation, configuration, and staff training.',
            'price' => 499.99,
            'sale_price' => null,
            'category_id' => 15,
            'sku' => 'SUP-IMPL',
            'weight' => 0.1,
            'material' => 'Service',
            'color' => 'Gray',
            'featured' => 0
        ],
        [
            'name' => 'Custom Integration Service',
            'description' => 'Have our team build custom integrations with your existing systems.',
            'price' => 1499.99,
            'sale_price' => null,
            'category_id' => 15,
            'sku' => 'SUP-CUSTOM-INT',
            'weight' => 0.1,
            'material' => 'Service',
            'color' => 'Gray',
            'featured' => 0
        ],

        // Hardware Bundles
        [
            'name' => 'Small Business Starter Bundle',
            'description' => 'Complete POS setup for small retail: Terminal, Printer, Scanner, Drawer, and 6 months of software.',
            'price' => 2499.99,
            'sale_price' => 1999.99,
            'category_id' => 13,
            'sku' => 'BUNDLE-SB',
            'weight' => 35.0,
            'material' => 'Multiple',
            'color' => 'Mixed',
            'featured' => 1
        ],
        [
            'name' => 'Restaurant Pro Bundle',
            'description' => 'Everything for a restaurant: 2 Terminals, 2 Printers, Kitchen Display System, and CRM tools.',
            'price' => 3999.99,
            'sale_price' => 3299.99,
            'category_id' => 13,
            'sku' => 'BUNDLE-REST',
            'weight' => 50.0,
            'material' => 'Multiple',
            'color' => 'Mixed',
            'featured' => 1
        ]
    ];

    $inserted = 0;
    $errors = [];

    foreach ($products as $product) {
        $name = $conn->real_escape_string($product['name']);
        $description = $conn->real_escape_string($product['description']);
        $price = $product['price'];
        $sale_price = $product['sale_price'] ?? 'NULL';
        $category_id = $product['category_id'];
        $sku = $conn->real_escape_string($product['sku']);
        $weight = $product['weight'] ?? 'NULL';
        $material = $conn->real_escape_string($product['material']);
        $color = $conn->real_escape_string($product['color']);
        $featured = $product['featured'] ?? 0;

        if ($sale_price !== 'NULL') {
            $sale_price = $product['sale_price'];
        }

        $sql = "INSERT INTO products (name, description, price, sale_price, category_id, sku, weight, material, color, featured, quantity)
                VALUES ('$name', '$description', $price, $sale_price, $category_id, '$sku', $weight, '$material', '$color', $featured, 100)";

        if ($conn->query($sql)) {
            $inserted++;
        } else {
            $errors[] = "Failed to insert '$name': " . $conn->error;
        }
    }

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Seeding Complete</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background: #f8f9fa; padding: 40px 0; }
            .card { max-width: 600px; margin: 0 auto; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); }
            .success-box { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h3 class="mb-0">✓ Sample Products Added!</h3>
                </div>
                <div class="card-body">
                    <div class="success-box">
                        <h5>✓ Successfully added <strong><?php echo $inserted; ?></strong> products</h5>
                        <p class="mb-0 text-muted">Your database is now populated with sample POS/CRM products.</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-warning">
                        <strong>Warnings:</strong>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <p class="mt-4">Products added with:</p>
                    <ul>
                        <li>Realistic pricing ($49 - $3,999)</li>
                        <li>9 featured items</li>
                        <li>11 products with sale prices</li>
                        <li>Distributed across all 15 categories</li>
                        <li>SKU codes and specifications</li>
                    </ul>

                    <div class="mt-4">
                        <a href="index.php" class="btn btn-primary">View Shop</a>
                        <a href="backend.php" class="btn btn-secondary">Admin Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
