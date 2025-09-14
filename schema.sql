-- ========================================
-- 710 DEN GLASS E-COMMERCE DATABASE SCHEMA
-- ========================================
-- Version: 2.0
-- Last Updated: September 2025
-- Description: Complete database schema for 710 Den Glass e-commerce platform
-- Features: Products, Orders, Users, Categories, Tags, Contact Messages, Chat, Reviews
-- ========================================

-- Create the database
CREATE DATABASE IF NOT EXISTS den_glass_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE den_glass_shop;

-- Users table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL, -- Store hashed passwords, not plain text
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    address TEXT, -- Can be normalized further if needed (street, etc.)
    city VARCHAR(50),
    state VARCHAR(50),
    zip_code VARCHAR(20),
    phone VARCHAR(20),
    is_admin BOOLEAN DEFAULT 0,
    age_verified BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories table
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE, -- Added UNIQUE constraint
    description TEXT,
    parent_id INT,
    image VARCHAR(255), -- Path to category image
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(category_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products table
CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    sale_price DECIMAL(10, 2),
    quantity INT NOT NULL DEFAULT 0,
    category_id INT,
    featured BOOLEAN DEFAULT 0,
    sku VARCHAR(50) UNIQUE,
    weight DECIMAL(10, 2), -- Consider units (e.g., kg or lbs) in comments or app logic
    dimensions VARCHAR(255), -- e.g., "10in H x 4in W"
    material VARCHAR(100),
    color VARCHAR(100), -- Ensuring this is VARCHAR(100)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Images table
CREATE TABLE product_images (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL, -- Relative path to image file
    alt_text VARCHAR(255), -- Good for accessibility and SEO
    is_primary BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add a trigger to enforce maximum 5 images per product
DELIMITER $$
CREATE TRIGGER limit_product_images_before_insert
    BEFORE INSERT ON product_images
    FOR EACH ROW
BEGIN
    DECLARE img_count INT;
    SELECT COUNT(*) INTO img_count FROM product_images WHERE product_id = NEW.product_id;
    IF img_count >= 5 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Maximum 5 images allowed per product';
    END IF;
END$$
DELIMITER ;

-- Product Tags table for filtering
CREATE TABLE tags (
    tag_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product-Tag relationship (Many-to-Many)
CREATE TABLE product_tags (
    product_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (product_id, tag_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(tag_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders table
CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT, -- Can be NULL if user is deleted or guest checkout
    total_amount DECIMAL(10, 2) NOT NULL,
    shipping_address TEXT NOT NULL, -- Snapshot of address at time of order
    billing_address TEXT NOT NULL,  -- Snapshot of address at time of order
    payment_method VARCHAR(50) NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    order_status ENUM('pending', 'processing', 'shipped', 'delivered', 'canceled', 'refunded', 'awaiting_payment', 'awaiting_shipment', 'on_hold') NOT NULL DEFAULT 'pending',
    tracking_number VARCHAR(100),
    notes TEXT, -- Customer notes or internal notes
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order Items table
CREATE TABLE order_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price_at_purchase DECIMAL(10, 2) NOT NULL, -- Price of product when order was placed
    product_name VARCHAR(100), -- Snapshot of product name at time of order
    product_sku VARCHAR(50), -- Snapshot of product SKU at time of order
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE NO ACTION -- Prevent product deletion if in an order
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shopping Cart table (using user_id to link to a user's cart)
CREATE TABLE cart_items (
    cart_item_id INT AUTO_INCREMENT PRIMARY KEY, -- Changed cart_id to cart_item_id
    user_id INT NOT NULL, -- For logged-in users. For guests, session-based cart.
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, product_id), -- Ensure one entry per product per user cart
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Age Verification Attempts
CREATE TABLE age_verification_attempts ( -- Renamed for clarity
    verification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT, -- Can be NULL if attempted before login/signup
    ip_address VARCHAR(45) NOT NULL,
    attempt_successful BOOLEAN DEFAULT 0, -- Renamed 'verified'
    details TEXT, -- e.g., method used, or failure reason
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Renamed 'verification_date'
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL -- Changed to SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wishlist Table
CREATE TABLE wishlists (
    wishlist_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) DEFAULT 'My Wishlist',
    is_public BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wishlist_items (
    wishlist_item_id INT AUTO_INCREMENT PRIMARY KEY,
    wishlist_id INT NOT NULL,
    product_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wishlist_id) REFERENCES wishlists(wishlist_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    UNIQUE KEY (wishlist_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Promotions Table
CREATE TABLE promotions (
    promotion_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    discount_type ENUM('percentage', 'fixed_amount') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    code VARCHAR(50) UNIQUE, -- Coupon code
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    usage_limit INT, -- Max number of times this promo can be used overall
    usage_limit_per_user INT, -- Max number of times a single user can use this promo
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Reviews Table
CREATE TABLE reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(100),
    comment TEXT,
    is_approved BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- ESSENTIAL DATA & CONFIGURATION
-- --------------------------------------------------------

-- Create the main admin user
INSERT INTO users (user_id, username, email, password_hash, first_name, last_name, is_admin, age_verified) VALUES
(1, 'admin', 'admin@710denglass.com', '$2y$12$yd8Gc3U.OyFS3l0YpUEpLefCd6GKmgxy33ZV9CBT4ehrE73snSJrW', 'Admin', 'User', 1, 1);

-- Create categories
INSERT INTO categories (name, description, image) VALUES
('Water Pipes', 'High-quality glass water pipes designed for optimal smoke filtration and cooling.', 'images/categories/water_pipes.jpg'),
('Hand Pipes', 'Portable and convenient glass hand pipes available in a variety of creative designs and sizes.', 'images/categories/hand_pipes.jpg'),
('Rigs', 'Glass pieces and setups, often specialized, for consuming various materials; includes dab rigs and more.', 'images/categories/rigs.jpg'),
('Accessories', 'A broad category for essential add-ons and supplementary items for your smoking and vaping collection.', 'images/categories/accessories.jpg'),
('Ash Catchers', 'Attachments for water pipes that catch ash and debris, helping to keep the main piece cleaner.', 'images/categories/ash_catchers.jpg'),
('Bowl Pieces', 'Removable bowls that hold tobacco or dry herbs, fitting into water pipes and bongs; also known as slides.', 'images/categories/bowl_pieces.jpg'),
('Carb Caps', 'Tools used with dab rigs and bangers to regulate airflow and temperature for efficient vaporization of concentrates.', 'images/categories/carb_caps.jpg'),
('Dab Rigs', 'Specialized water pipes engineered specifically for vaporizing cannabis concentrates or extracts.', 'images/categories/dab_rigs.jpg'),
('Dabbers', 'Tools, typically made of glass, titanium, or quartz, used to handle and apply concentrates to a heated surface.', 'images/categories/dabbers.jpg'),
('Downstems', 'The crucial component of a water pipe that connects the bowl piece to the water chamber, diffusing smoke.', 'images/categories/downstems.jpg'),
('Marbles', 'Solid glass spheres, often used as decorative elements, carb caps, or valve stoppers in some pipe designs.', 'images/categories/marbles.jpg'),
('Pendants', 'Wearable glass art pieces, some of which may also be functional as small pipes or dab tools.', 'images/categories/pendants.jpg'),
('Puffco Accessories', 'A range of accessories, attachments, and replacement parts designed specifically for Puffco brand vaporizers.', 'images/categories/puffco.jpg'),
('Quartz Bangers', 'High-purity quartz components, primarily bangers and inserts, favored for their heat retention and flavor preservation in dabbing.', 'images/categories/quartz.jpg'), 
('Slides', 'Another term for bowl pieces; glass components that slide into the downstem of a water pipe to hold material.', 'images/categories/slides.jpg');

-- Create tags
INSERT INTO tags (name) VALUES
('Borosilicate'), ('Scientific Glass'), ('Heady Glass'), ('Recycler'), ('Percolator'),
('USA Made'), ('Import'), ('14mm Joint'), ('10mm Joint'), ('18mm Joint'),
('Male Joint'), ('Female Joint'), ('90 Degree Angle'), ('45 Degree Angle'), ('Glow in the Dark'),
('Thick Glass'), ('Colored Accents'), ('Klein Recycler'), ('Beaker Base'), ('Straight Tube'),
('Limited Edition'), ('Thermal Banger'), ('Carb Cap Included'), ('Ice Pinch');

-- Sample Promotions (Optional - Update dates as needed for actual promotions)
-- INSERT INTO promotions (name, description, discount_type, discount_value, code, start_date, end_date, usage_limit, usage_limit_per_user, is_active) VALUES
-- ('Grand Opening 15%', 'Celebrate our launch! Get 15% off your first order.', 'percentage', 15.00, 'WELCOME15', '2025-01-01 00:00:00', '2025-12-31 23:59:59', 2000, 1, 1),
-- ('Weekly Deal - Rigs', 'This week only, $10 off selected Dab Rigs.', 'fixed_amount', 10.00, 'RIGWEEK', '2025-01-01 00:00:00', '2025-01-07 23:59:59', 500, 1, 1);

-- --------------------------------------------------------
-- ADDITIONAL FEATURES
-- --------------------------------------------------------

CREATE TABLE chat_interactions (
    interaction_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    session_id VARCHAR(255) NULL,
    user_message TEXT NOT NULL,
    ai_response TEXT NOT NULL,
    tokens_used INT DEFAULT 0,
    response_time_ms INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Optional: Create a view for chat analytics
CREATE OR REPLACE VIEW chat_analytics AS
SELECT 
    DATE(created_at) as chat_date,
    COUNT(*) as total_interactions,
    COUNT(DISTINCT user_id) as unique_users,
    COUNT(DISTINCT session_id) as unique_sessions,
    AVG(tokens_used) as avg_tokens_per_interaction,
    AVG(response_time_ms) as avg_response_time_ms,
    SUM(tokens_used) as total_tokens_used
FROM chat_interactions 
GROUP BY DATE(created_at)
ORDER BY chat_date DESC;

-- Contact Messages table
CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    ip_address VARCHAR(45),
    is_read BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- PERFORMANCE INDEXES
-- --------------------------------------------------------
CREATE INDEX idx_products_name ON products(name);
CREATE INDEX idx_orders_user_id ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(order_status);
CREATE INDEX idx_order_items_product_id ON order_items(product_id);
CREATE INDEX idx_contact_messages_created_at ON contact_messages(created_at);
CREATE INDEX idx_contact_messages_is_read ON contact_messages(is_read);
CREATE INDEX idx_chat_interactions_user_id ON chat_interactions(user_id);
CREATE INDEX idx_chat_interactions_created_at ON chat_interactions(created_at);

-- ========================================
-- SCHEMA INSTALLATION COMPLETE
-- ========================================
-- 
-- This schema includes:
-- ✓ 16 Tables with proper relationships
-- ✓ Product management with up to 5 images per product
-- ✓ User management with admin capabilities
-- ✓ Order processing with detailed tracking
-- ✓ Contact message system
-- ✓ AI chat interaction logging
-- ✓ Performance indexes for optimal queries
-- ✓ Triggers for data validation
-- 
-- To install:
-- 1. Run this entire script on a fresh MySQL database
-- 2. Update denglass-config.php with your database credentials
-- 3. Login with admin/admin@710denglass.com (password: admin123)
-- 
-- ======================================== 