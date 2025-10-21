<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/applications/config.php';

// Debug session information - useful for development
// error_log("Session data in backend.php: " . print_r($_SESSION, true));

// Check if user is admin
function isAdmin() {
    $is_admin = isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    return $is_admin;
}

// Helper function to sanitize input
function sanitize($input) {
    global $conn; // Database connection
    if ($conn && $conn instanceof mysqli) {
        return mysqli_real_escape_string($conn, htmlspecialchars(trim($input)));
    }
    return htmlspecialchars(trim($input));
}

// Helper function to upload images
function uploadImage($file, $destination = 'uploads/') {
    $target_dir = rtrim($destination, '/') . '/'; 
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0777, true)) {
            error_log("Upload failed: Could not create directory " . $target_dir);
            return false;
        }
    }
    if (!is_writable($target_dir)) {
        error_log("Upload failed: Directory is not writable " . $target_dir);
        return false;
    }
    $imageFileType = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $safe_filename = preg_replace('/[^A-Za-z0-9_.-]/', '_', basename($file["name"]));
    $target_file = $target_dir . uniqid() . "_" . $safe_filename;
    if (empty($file["tmp_name"]) || !file_exists($file["tmp_name"])) {
        error_log("Upload failed: Temporary file not found for " . $file["name"]);
        return false;
    }
    $check = getimagesize($file["tmp_name"]);
    if($check === false) {
        error_log("Upload failed: File is not an image. " . $file["name"]);
        return false;
    }
    if ($file["size"] > 5000000) { // 5MB limit
        error_log("Upload failed: File is too large (max 5MB). " . $file["name"]);
        return false;
    }
    $allowed_types = ["jpg", "png", "jpeg", "gif", "webp"];
    if(!in_array($imageFileType, $allowed_types)) {
        error_log("Upload failed: Invalid file type (allowed: " . implode(", ", $allowed_types) . "). " . $file["name"]);
        return false;
    }
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return $target_file;
    } else {
        error_log("Upload failed: Could not move uploaded file. " . $file["name"] . " to " . $target_file . ". Error: " . print_r(error_get_last(), true));
        return false;
    }
}

$message = ''; 
$error = '';   
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';

if (!isAdmin()) { 
    error_log("Non-admin user attempted to access backend.php for action: {$action} - Redirecting to login.php");
    header('Location: login.php'); 
    exit;
}

// Initialize variables
$product_count = 0; $category_count = 0; $order_count = 0; $user_count = 0;
$products = []; $categories = []; $tags = []; $orders = []; $users = [];
$product = null; $category = null; $order = null; $order_items = [];
$admin_user = null; $user_data = null; $parent_categories_list = [];

// --- LOGIC SECTION ---
if ($action === 'dashboard') {
    // Basic counts
    $product_count_res = $conn->query("SELECT COUNT(*) as count FROM products");
    $product_count = $product_count_res ? $product_count_res->fetch_assoc()['count'] : 0;
    $category_count_res = $conn->query("SELECT COUNT(*) as count FROM categories");
    $category_count = $category_count_res ? $category_count_res->fetch_assoc()['count'] : 0;
    $order_count_res = $conn->query("SELECT COUNT(*) as count FROM orders");
    $order_count = $order_count_res ? $order_count_res->fetch_assoc()['count'] : 0;
    $user_count_res = $conn->query("SELECT COUNT(*) as count FROM users");
    $user_count = $user_count_res ? $user_count_res->fetch_assoc()['count'] : 0;
    $message_count_res = $conn->query("SELECT COUNT(*) as count FROM contact_messages");
    $message_count = $message_count_res ? $message_count_res->fetch_assoc()['count'] : 0;
    $unread_message_count_res = $conn->query("SELECT COUNT(*) as count FROM contact_messages WHERE is_read = 0");
    $unread_message_count = $unread_message_count_res ? $unread_message_count_res->fetch_assoc()['count'] : 0;
    
    // Advanced analytics
    $total_revenue_res = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid'");
    $total_revenue = $total_revenue_res ? ($total_revenue_res->fetch_assoc()['total'] ?? 0) : 0;
    
    $monthly_revenue_res = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $monthly_revenue = $monthly_revenue_res ? ($monthly_revenue_res->fetch_assoc()['total'] ?? 0) : 0;
    
    $pending_orders_res = $conn->query("SELECT COUNT(*) as count FROM orders WHERE order_status IN ('pending', 'processing')");
    $pending_orders = $pending_orders_res ? $pending_orders_res->fetch_assoc()['count'] : 0;
    
    $low_stock_res = $conn->query("SELECT COUNT(*) as count FROM products WHERE quantity <= 5 AND quantity > 0");
    $low_stock_count = $low_stock_res ? $low_stock_res->fetch_assoc()['count'] : 0;
    
    $out_of_stock_res = $conn->query("SELECT COUNT(*) as count FROM products WHERE quantity = 0");
    $out_of_stock_count = $out_of_stock_res ? $out_of_stock_res->fetch_assoc()['count'] : 0;
    
    // Recent activity
    $recent_orders_res = $conn->query("SELECT o.*, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.user_id ORDER BY o.created_at DESC LIMIT 5");
    $recent_orders = $recent_orders_res ? $recent_orders_res->fetch_all(MYSQLI_ASSOC) : [];
    
    $recent_messages_res = $conn->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5");
    $recent_messages = $recent_messages_res ? $recent_messages_res->fetch_all(MYSQLI_ASSOC) : [];
    
    // Top products
    $top_products_res = $conn->query("SELECT p.name, SUM(oi.quantity) as sold_qty, SUM(oi.price_at_purchase * oi.quantity) as revenue FROM products p JOIN order_items oi ON p.product_id = oi.product_id JOIN orders o ON oi.order_id = o.order_id WHERE o.payment_status = 'paid' GROUP BY p.product_id ORDER BY sold_qty DESC LIMIT 5");
    $top_products = $top_products_res ? $top_products_res->fetch_all(MYSQLI_ASSOC) : [];
    
    // Sales by status
    $order_status_res = $conn->query("SELECT order_status, COUNT(*) as count FROM orders GROUP BY order_status");
    $order_status_data = $order_status_res ? $order_status_res->fetch_all(MYSQLI_ASSOC) : [];
}
elseif ($action === 'products') {
    $result = $conn->query("SELECT p.*, c.name as category_name, 
                                   (SELECT image_path FROM product_images WHERE product_id = p.product_id AND is_primary = 1 LIMIT 1) as primary_image 
                                   FROM products p 
                                   LEFT JOIN categories c ON p.category_id = c.category_id 
                                   ORDER BY p.created_at DESC");
    $products = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
elseif ($action === 'export_products_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=products_export_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    $header_fields = [
        'Product ID', 'SKU', 'Name', 'Description', 'Price', 'Sale Price', 'Quantity',
        'Category Name', 'Featured (0/1)', 'Weight', 'Dimensions', 'Material', 'Color',
        'Tags (comma-separated)', 'Primary Image Path', 'Date Created', 'Date Updated'
    ];
    fputcsv($output, $header_fields, ',', '"', '\\'); 
    $sql = "SELECT
                p.product_id, p.sku, p.name, p.description, p.price, p.sale_price, p.quantity,
                c.name as category_name, p.featured, p.weight, p.dimensions, p.material, p.color,
                (SELECT GROUP_CONCAT(t.name SEPARATOR ', ')
                 FROM tags t
                 JOIN product_tags pt ON t.tag_id = pt.tag_id
                 WHERE pt.product_id = p.product_id) as product_tags_csv,
                (SELECT image_path
                 FROM product_images pi
                 WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) as primary_image_path,
                p.created_at, p.updated_at
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            ORDER BY p.product_id ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data_fields = [
                $row['product_id'], $row['sku'], $row['name'], $row['description'], $row['price'], $row['sale_price'],
                $row['quantity'], $row['category_name'], $row['featured'], $row['weight'], $row['dimensions'],
                $row['material'], $row['color'], $row['product_tags_csv'], $row['primary_image_path'],
                $row['created_at'], $row['updated_at']
            ];
            fputcsv($output, $data_fields, ',', '"', '\\');
        }
    }
    fclose($output);
    exit;
}
elseif ($action === 'import_products_csv' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $import_success_count = 0;
    $import_error_count = 0;
    $import_errors = [];

    if (isset($_FILES['products_csv_file'])) {
        if ($_FILES['products_csv_file']['error'] == UPLOAD_ERR_OK) {
            $file_tmp_path = $_FILES['products_csv_file']['tmp_name'];
            $file_name = $_FILES['products_csv_file']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if ($file_ext === 'csv') {
                if (($handle = fopen($file_tmp_path, 'r')) !== FALSE) {
                    $conn->begin_transaction();
                    try {
                        // Use fgetcsv with all parameters to avoid deprecation
                        $header = fgetcsv($handle, 0, ',', '"', '\\'); // Read header
                        $row_num = 1;

                        // Use fgetcsv with all parameters to avoid deprecation
                        while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== FALSE) {
                            $row_num++;
                            if (count($data) < 17) { // Adjust count based on expected columns in your CSV
                                $import_errors[] = "Row {$row_num}: Incorrect column count. Expected 17, got " . count($data) . ".";
                                $import_error_count++;
                                continue;
                            }

                            $product_id_csv = trim($data[0]);
                            $sku = sanitize(trim($data[1]));
                            $name = sanitize(trim($data[2]));
                            $description = sanitize(trim($data[3]));
                            $price = !empty(trim($data[4])) ? floatval(trim($data[4])) : 0.0;
                            $sale_price = !empty(trim($data[5])) ? floatval(trim($data[5])) : null;
                            $quantity = !empty(trim($data[6])) ? intval(trim($data[6])) : 0;
                            $category_name = sanitize(trim($data[7]));
                            $featured = (trim($data[8]) == '1' || strtolower(trim($data[8])) === 'true') ? 1 : 0;
                            $weight = !empty(trim($data[9])) ? floatval(trim($data[9])) : null;
                            $dimensions = sanitize(trim($data[10]));
                            $material = sanitize(trim($data[11]));
                            $color = sanitize(trim($data[12]));
                            $tags_csv = trim($data[13]);
                            // primary_image_path ($data[14]) is ignored for now
                            $created_at_csv = !empty(trim($data[15])) ? trim($data[15]) : null;
                            // updated_at ($data[16]) will be set to NOW() on update

                            if (empty($sku) && empty($product_id_csv)) {
                                $import_errors[] = "Row {$row_num}: SKU or Product ID is required.";
                                $import_error_count++;
                                continue;
                            }
                            if (empty($name)) {
                                $import_errors[] = "Row {$row_num}: Product Name is required for SKU '{$sku}'.";
                                $import_error_count++;
                                continue;
                            }

                            $category_id = null;
                            if (!empty($category_name)) {
                                $stmt_cat = $conn->prepare("SELECT category_id FROM categories WHERE name = ?");
                                $stmt_cat->bind_param("s", $category_name);
                                $stmt_cat->execute();
                                $res_cat = $stmt_cat->get_result();
                                if ($res_cat->num_rows > 0) {
                                    $category_id = $res_cat->fetch_assoc()['category_id'];
                                } else {
                                    $import_errors[] = "Row {$row_num} (SKU: {$sku}): Category '{$category_name}' not found. Product will have no category.";
                                }
                                $stmt_cat->close();
                            }

                            $tag_ids = [];
                            if (!empty($tags_csv)) {
                                $tag_names = array_map('trim', explode(',', $tags_csv));
                                foreach ($tag_names as $tag_name_item) {
                                    if (empty($tag_name_item)) continue;
                                    $safe_tag_name = sanitize($tag_name_item);
                                    $stmt_tag_find = $conn->prepare("SELECT tag_id FROM tags WHERE name = ?");
                                    $stmt_tag_find->bind_param("s", $safe_tag_name);
                                    $stmt_tag_find->execute();
                                    $res_tag = $stmt_tag_find->get_result();
                                    if ($res_tag->num_rows > 0) {
                                        $tag_ids[] = $res_tag->fetch_assoc()['tag_id'];
                                    } else {
                                        $stmt_tag_insert = $conn->prepare("INSERT INTO tags (name) VALUES (?)");
                                        $stmt_tag_insert->bind_param("s", $safe_tag_name);
                                        if ($stmt_tag_insert->execute()) {
                                            $tag_ids[] = $conn->insert_id;
                                        } else {
                                            $import_errors[] = "Row {$row_num} (SKU: {$sku}): Failed to create tag '{$safe_tag_name}'. Error: " . $conn->error;
                                        }
                                        $stmt_tag_insert->close();
                                    }
                                    $stmt_tag_find->close();
                                }
                            }

                            $existing_product_id = null;
                            if (!empty($product_id_csv) && is_numeric($product_id_csv)) {
                                 $stmt_check = $conn->prepare("SELECT product_id FROM products WHERE product_id = ?");
                                 $stmt_check->bind_param("i", $product_id_csv);
                                 $stmt_check->execute();
                                 $res_check = $stmt_check->get_result();
                                 if($res_check->num_rows > 0) $existing_product_id = $product_id_csv;
                                 $stmt_check->close();
                            }
                            if (!$existing_product_id && !empty($sku)) {
                                $stmt_check_sku = $conn->prepare("SELECT product_id FROM products WHERE sku = ?");
                                $stmt_check_sku->bind_param("s", $sku);
                                $stmt_check_sku->execute();
                                $res_check_sku = $stmt_check_sku->get_result();
                                if ($res_check_sku->num_rows > 0) {
                                    $existing_product_id = $res_check_sku->fetch_assoc()['product_id'];
                                }
                                $stmt_check_sku->close();
                            }

                            if ($existing_product_id) { // Update existing product
                                $stmt_update = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, sale_price = ?, quantity = ?, category_id = ?, featured = ?, sku = ?, weight = ?, dimensions = ?, material = ?, color = ?, updated_at = NOW() WHERE product_id = ?");
                                // Corrected type string: 13 characters for 13 placeholders
                                $stmt_update->bind_param("ssddiiisdsdsi", $name, $description, $price, $sale_price, $quantity, $category_id, $featured, $sku, $weight, $dimensions, $material, $color, $existing_product_id);
                                if ($stmt_update->execute()) {
                                    $current_product_id = $existing_product_id;
                                    $stmt_del_tags = $conn->prepare("DELETE FROM product_tags WHERE product_id = ?");
                                    $stmt_del_tags->bind_param("i", $current_product_id);
                                    $stmt_del_tags->execute();
                                    $stmt_del_tags->close();
                                    if (!empty($tag_ids)) {
                                        $stmt_add_tag = $conn->prepare("INSERT INTO product_tags (product_id, tag_id) VALUES (?, ?)");
                                        foreach ($tag_ids as $tid) {
                                            $stmt_add_tag->bind_param("ii", $current_product_id, $tid);
                                            $stmt_add_tag->execute();
                                        }
                                        $stmt_add_tag->close();
                                    }
                                    $import_success_count++;
                                } else {
                                    $import_errors[] = "Row {$row_num} (SKU: {$sku}): Failed to update product. Error: " . $stmt_update->error;
                                    $import_error_count++;
                                }
                                $stmt_update->close();
                            } else { // Insert new product
                                $created_at_to_insert = $created_at_csv ? date('Y-m-d H:i:s', strtotime($created_at_csv)) : date('Y-m-d H:i:s');
                                $stmt_insert = $conn->prepare("INSERT INTO products (name, description, price, sale_price, quantity, category_id, featured, sku, weight, dimensions, material, color, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                                // Corrected type string: 13 characters for 13 placeholders
                                $stmt_insert->bind_param("ssddiiisdsdss", $name, $description, $price, $sale_price, $quantity, $category_id, $featured, $sku, $weight, $dimensions, $material, $color, $created_at_to_insert);
                                if ($stmt_insert->execute()) {
                                    $current_product_id = $conn->insert_id;
                                    if (!empty($tag_ids)) {
                                        $stmt_add_tag = $conn->prepare("INSERT INTO product_tags (product_id, tag_id) VALUES (?, ?)");
                                        foreach ($tag_ids as $tid) {
                                            $stmt_add_tag->bind_param("ii", $current_product_id, $tid);
                                            $stmt_add_tag->execute();
                                        }
                                        $stmt_add_tag->close();
                                    }
                                    $import_success_count++;
                                } else {
                                    $import_errors[] = "Row {$row_num} (SKU: {$sku}): Failed to insert product. Error: " . $stmt_insert->error;
                                    $import_error_count++;
                                }
                                $stmt_insert->close();
                            }
                        }
                        
                        if ($import_error_count > 0) {
                            $conn->rollback();
                            $_SESSION['flash_error'] = "Import failed. {$import_error_count} errors occurred. Transaction rolled back. Details: <br>" . implode("<br>", $import_errors);
                            error_log("CSV Import Errors (transaction rolled back): " . implode("\n", $import_errors));
                        } else {
                            $conn->commit();
                            $_SESSION['flash_message'] = "CSV imported successfully! {$import_success_count} products processed.";
                            if(!empty($import_errors)){ 
                                $_SESSION['flash_message'] .= "<br>Minor issues (committed with these): <br>" . implode("<br>", $import_errors);
                                error_log("CSV Import Minor Issues (committed with these): " . implode("\n", $import_errors));
                            }
                        }
                    } catch (Exception $e) {
                        $conn->rollback();
                        $_SESSION['flash_error'] = "An exception occurred during import: " . $e->getMessage() . ". Transaction rolled back.";
                        error_log("CSV Import Exception: " . $e->getMessage());
                    } finally {
                        if (is_resource($handle)) {
                           fclose($handle);
                        }
                    }
                } else {
                    $_SESSION['flash_error'] = "Could not open the uploaded CSV file for reading.";
                    error_log("CSV Import Error: Could not open file: " . $file_tmp_path);
                }
            } else {
                $_SESSION['flash_error'] = "Invalid file type. Please upload a CSV file. (Uploaded: " . htmlspecialchars($file_ext) . ")";
                error_log("CSV Import Error: Invalid file type: " . $file_ext);
            }
        } else {
            $upload_error_message = "Upload error code: " . $_FILES['products_csv_file']['error'];
            switch ($_FILES['products_csv_file']['error']) {
                case UPLOAD_ERR_INI_SIZE: $upload_error_message = "The uploaded file exceeds the upload_max_filesize directive in php.ini."; break;
                case UPLOAD_ERR_FORM_SIZE: $upload_error_message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form."; break;
                case UPLOAD_ERR_PARTIAL: $upload_error_message = "The uploaded file was only partially uploaded."; break;
                case UPLOAD_ERR_NO_FILE: $upload_error_message = "No file was uploaded."; break;
                case UPLOAD_ERR_NO_TMP_DIR: $upload_error_message = "Missing a temporary folder for uploads."; break;
                case UPLOAD_ERR_CANT_WRITE: $upload_error_message = "Failed to write file to disk."; break;
                case UPLOAD_ERR_EXTENSION: $upload_error_message = "A PHP extension stopped the file upload."; break;
                default: $upload_error_message = "Unknown upload error. Code: " . $_FILES['products_csv_file']['error']; break;
            }
            $_SESSION['flash_error'] = "File upload failed: " . $upload_error_message;
            error_log("CSV Import File Upload Error: " . $upload_error_message);
        }
    } else {
        $_SESSION['flash_error'] = "No file data received in upload. Please ensure you selected a file.";
        error_log("CSV Import Error: No \$_FILES['products_csv_file'] array set.");
    }
    header('Location: backend.php?action=products');
    exit;
}
elseif ($action === 'edit_product') {
    $product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $is_new_product = ($product_id === 0);
    $categories_result = $conn->query("SELECT * FROM categories ORDER BY name");
    $categories = $categories_result ? $categories_result->fetch_all(MYSQLI_ASSOC) : [];
    $tags_result = $conn->query("SELECT * FROM tags ORDER BY name");
    $tags = $tags_result ? $tags_result->fetch_all(MYSQLI_ASSOC) : [];
    if (!$is_new_product) {
        $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $product = $result->fetch_assoc();
            $product_tags_result = $conn->query("SELECT tag_id FROM product_tags WHERE product_id = $product_id");
            $product_tags_arr = [];
            if($product_tags_result){
                while ($tag_row = $product_tags_result->fetch_assoc()) {
                    $product_tags_arr[] = $tag_row['tag_id'];
                }
            }
            $product['tags'] = $product_tags_arr;
            $images_result = $conn->query("SELECT * FROM product_images WHERE product_id = $product_id ORDER BY is_primary DESC, image_id ASC");
            $product['images'] = $images_result ? $images_result->fetch_all(MYSQLI_ASSOC) : [];
        } else {
            $_SESSION['flash_error'] = "Product not found.";
            header('Location: backend.php?action=products');
            exit;
        }
        $stmt->close();
    } else {
        $product = [
            'name' => '', 'description' => '', 'price' => '', 'sale_price' => null, 'quantity' => 0,
            'category_id' => null, 'featured' => 0, 'sku' => '', 'weight' => null, 'dimensions' => '',
            'material' => '', 'color' => '', 'tags' => [], 'images' => []
        ];
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $price = floatval($_POST['price']);
        $sale_price = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : null;
        $quantity = intval($_POST['quantity']);
        $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
        $featured = isset($_POST['featured']) ? 1 : 0;
        $sku = sanitize($_POST['sku']);
        $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
        $dimensions = sanitize($_POST['dimensions']);
        $material = sanitize($_POST['material']);
        $color = sanitize($_POST['color']);
        if (empty($name) || empty($_POST['price']) || !isset($_POST['quantity']) || empty($category_id)) {
            $_SESSION['flash_error'] = "Please fill in all required fields: Name, Price, Quantity, Category.";
            header("Location: backend.php?action=edit_product" . ($is_new_product ? '' : '&id=' . $product_id));
            exit;
        }
        $conn->begin_transaction();
        try {
            if (!$is_new_product) { 
                $sql = "UPDATE products SET name = ?, description = ?, price = ?, sale_price = ?, quantity = ?, category_id = ?, featured = ?, sku = ?, weight = ?, dimensions = ?, material = ?, color = ?, updated_at = NOW() WHERE product_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssddiiisdsssi", $name, $description, $price, $sale_price, $quantity, $category_id, $featured, $sku, $weight, $dimensions, $material, $color, $product_id);
            } else { 
                $sql = "INSERT INTO products (name, description, price, sale_price, quantity, category_id, featured, sku, weight, dimensions, material, color, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssddiiisdsss", $name, $description, $price, $sale_price, $quantity, $category_id, $featured, $sku, $weight, $dimensions, $material, $color);
            }
            if ($stmt->execute()) {
                $current_product_id = $is_new_product ? $conn->insert_id : $product_id;
                $_SESSION['flash_message'] = "Product " . ($is_new_product ? "added" : "updated") . " successfully!";
                if ($is_new_product) $product_id = $current_product_id;
                $conn->query("DELETE FROM product_tags WHERE product_id = $current_product_id");
                if (isset($_POST['tags']) && is_array($_POST['tags'])) {
                    $stmt_tag = $conn->prepare("INSERT INTO product_tags (product_id, tag_id) VALUES (?, ?)");
                    foreach ($_POST['tags'] as $tag_id_selected) {
                        $tag_id_val = intval($tag_id_selected);
                        $stmt_tag->bind_param("ii", $current_product_id, $tag_id_val);
                        $stmt_tag->execute();
                    }
                    $stmt_tag->close();
                }
                if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
                    // Check current image count
                    $current_image_count = 0;
                    if ($current_product_id > 0) {
                        $count_res = $conn->query("SELECT COUNT(*) as count FROM product_images WHERE product_id = $current_product_id");
                        if ($count_res) {
                            $current_image_count = $count_res->fetch_assoc()['count'];
                        }
                    }
                    
                    // Count new images to upload
                    $new_images_count = 0;
                    foreach ($_FILES['images']['name'] as $index => $name) {
                        if (!empty($name) && $_FILES['images']['error'][$index] === UPLOAD_ERR_OK) {
                            $new_images_count++;
                        }
                    }
                    
                    // Check if total would exceed 5 images
                    if ($current_image_count + $new_images_count > 5) {
                        $_SESSION['flash_error'] = "Cannot upload " . $new_images_count . " images. Product already has " . $current_image_count . " images. Maximum 5 images allowed per product.";
                        header("Location: backend.php?action=edit_product&id=" . $product_id);
                        exit;
                    }
                    
                    $has_primary_image = false;
                    if ($current_product_id > 0) {
                        $check_primary_res = $conn->query("SELECT COUNT(*) as count FROM product_images WHERE product_id = $current_product_id AND is_primary = 1");
                        if ($check_primary_res && $check_primary_res->fetch_assoc()['count'] > 0) {
                            $has_primary_image = true;
                        }
                    }
                    for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                        if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                            $file_for_upload = ["name" => $_FILES['images']['name'][$i], "type" => $_FILES['images']['type'][$i], "tmp_name" => $_FILES['images']['tmp_name'][$i], "error" => $_FILES['images']['error'][$i], "size" => $_FILES['images']['size'][$i]];
                            $image_path = uploadImage($file_for_upload, 'uploads/products/');
                            if ($image_path && $current_product_id > 0) {
                                $is_primary_upload = 0;
                                if (!$has_primary_image && $i == 0) {
                                    $conn->query("UPDATE product_images SET is_primary = 0 WHERE product_id = $current_product_id");
                                    $is_primary_upload = 1;
                                    $has_primary_image = true;
                                }
                                $stmt_img = $conn->prepare("INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, ?)");
                                $stmt_img->bind_param("isi", $current_product_id, $image_path, $is_primary_upload);
                                $stmt_img->execute();
                                $stmt_img->close();
                            } elseif(!$image_path) {
                                $_SESSION['flash_error'] = ($_SESSION['flash_error'] ?? "") . "Failed to upload image: " . $_FILES['images']['name'][$i] . ". ";
                            }
                        } else if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                            $error_message = "Unknown error uploading file " . $_FILES['images']['name'][$i] . ".";
                            switch ($_FILES['images']['error'][$i]) {
                                case UPLOAD_ERR_INI_SIZE:
                                    $error_message = "File " . $_FILES['images']['name'][$i] . " is too large (exceeds server's upload_max_filesize).";
                                    break;
                                case UPLOAD_ERR_FORM_SIZE:
                                    $error_message = "File " . $_FILES['images']['name'][$i] . " is too large (exceeds form's MAX_FILE_SIZE).";
                                    break;
                                case UPLOAD_ERR_PARTIAL:
                                    $error_message = "File " . $_FILES['images']['name'][$i] . " was only partially uploaded.";
                                    break;
                                // UPLOAD_ERR_NO_FILE is already handled
                                case UPLOAD_ERR_NO_TMP_DIR:
                                    $error_message = "Missing a temporary folder for file " . $_FILES['images']['name'][$i] . ".";
                                    break;
                                case UPLOAD_ERR_CANT_WRITE:
                                    $error_message = "Failed to write file " . $_FILES['images']['name'][$i] . " to disk.";
                                    break;
                                case UPLOAD_ERR_EXTENSION:
                                    $error_message = "A PHP extension stopped the upload of file " . $_FILES['images']['name'][$i] . ".";
                                    break;
                            }
                             $_SESSION['flash_error'] = ($_SESSION['flash_error'] ?? "") . $error_message . " ";
                        }
                    }
                    if($current_product_id > 0){
                        $img_count_res = $conn->query("SELECT COUNT(*) as count FROM product_images WHERE product_id = $current_product_id");
                        if($img_count_res && $img_count_res->fetch_assoc()['count'] > 0){
                            $primary_check = $conn->query("SELECT COUNT(*) as count FROM product_images WHERE product_id = $current_product_id AND is_primary = 1");
                            if ($primary_check && $primary_check->fetch_assoc()['count'] === 0) {
                                $conn->query("UPDATE product_images SET is_primary = 1 WHERE product_id = $current_product_id ORDER BY image_id ASC LIMIT 1");
                            }
                        }
                    }
                }
                $conn->commit();
            } else {
                $_SESSION['flash_error'] = "Error " . ($is_new_product ? "adding" : "updating") . " product: " . $stmt->error;
                $conn->rollback();
            }
            $stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_error'] = "An exception occurred: " . $e->getMessage();
        }
        header("Location: backend.php?action=edit_product&id=" . $product_id);
        exit;
    }
}
elseif ($action === 'set_primary_image') {
    $image_id = isset($_GET['image_id']) ? intval($_GET['image_id']) : 0;
    $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
    if ($image_id > 0 && $product_id > 0) {
        $conn->begin_transaction();
        try {
            $stmt_reset = $conn->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?");
            $stmt_reset->bind_param("i", $product_id);
            $stmt_reset->execute();
            $stmt_reset->close();
            $stmt_set = $conn->prepare("UPDATE product_images SET is_primary = 1 WHERE image_id = ? AND product_id = ?");
            $stmt_set->bind_param("ii", $image_id, $product_id);
            if ($stmt_set->execute()) {
                $_SESSION['flash_message'] = "Primary image updated successfully!";
                $conn->commit();
            } else {
                $_SESSION['flash_error'] = "Error updating primary image: " . $stmt_set->error;
                $conn->rollback();
            }
            $stmt_set->close();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_error'] = "Exception updating primary image: " . $e->getMessage();
        }
    } else {
        $_SESSION['flash_error'] = "Invalid image or product ID.";
    }
    header("Location: backend.php?action=edit_product&id=$product_id");
    exit;
}
elseif ($action === 'delete_image') {
    $image_id = isset($_GET['image_id']) ? intval($_GET['image_id']) : 0;
    $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
    if ($image_id > 0) {
        $stmt_get = $conn->prepare("SELECT image_path, is_primary FROM product_images WHERE image_id = ? AND product_id = ?");
        $stmt_get->bind_param("ii", $image_id, $product_id);
        $stmt_get->execute();
        $image_res = $stmt_get->get_result();
        if ($image_res->num_rows > 0) {
            $image = $image_res->fetch_assoc();
            $conn->begin_transaction();
            try {
                if (file_exists($image['image_path'])) {
                    unlink($image['image_path']);
                }
                $stmt_del = $conn->prepare("DELETE FROM product_images WHERE image_id = ?");
                $stmt_del->bind_param("i", $image_id);
                if ($stmt_del->execute()) {
                    $_SESSION['flash_message'] = "Image deleted successfully!";
                    if ($image['is_primary'] && $product_id > 0) {
                        $stmt_new_primary = $conn->prepare("UPDATE product_images SET is_primary = 1 WHERE product_id = ? ORDER BY image_id ASC LIMIT 1");
                        $stmt_new_primary->bind_param("i", $product_id);
                        $stmt_new_primary->execute();
                        $stmt_new_primary->close();
                    }
                    $conn->commit();
                } else {
                    $_SESSION['flash_error'] = "Error deleting image record: " . $stmt_del->error;
                    $conn->rollback();
                }
                $stmt_del->close();
            } catch (Exception $e) {
                 $conn->rollback();
                 $_SESSION['flash_error'] = "Exception deleting image: " . $e->getMessage();
            }
        } else {
            $_SESSION['flash_error'] = "Image not found or does not belong to this product.";
        }
        $stmt_get->close();
    } else {
        $_SESSION['flash_error'] = "Invalid Image ID.";
    }
    header("Location: backend.php?action=edit_product&id=$product_id");
    exit;
}
elseif ($action === 'delete_product') {
    $product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($product_id > 0) {
        $conn->begin_transaction();
        try {
            $images_result = $conn->query("SELECT image_path FROM product_images WHERE product_id = $product_id");
            if ($images_result) {
                while ($img = $images_result->fetch_assoc()) {
                    if (file_exists($img['image_path'])) {
                        unlink($img['image_path']);
                    }
                }
            }
            $conn->query("DELETE FROM product_images WHERE product_id = $product_id");
            $conn->query("DELETE FROM product_tags WHERE product_id = $product_id");
            $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
            $stmt->bind_param("i", $product_id);
            if ($stmt->execute()) {
                $_SESSION['flash_message'] = "Product deleted successfully!";
                $conn->commit();
            } else {
                if ($conn->errno == 1451) { // Foreign key constraint error
                    $_SESSION['flash_error'] = "Error deleting product: This product is part of an existing order and cannot be deleted. Consider unlisting it instead.";
                } else {
                    $_SESSION['flash_error'] = "Error deleting product: " . $stmt->error;
                }
                $conn->rollback();
            }
            $stmt->close();
        } catch (Exception $e) {
            if ($conn->errno == 1451) { // Catch Exception that might wrap a foreign key error
                 $_SESSION['flash_error'] = "Error deleting product: This product is part of an existing order and cannot be deleted. Consider unlisting it instead.";
            } else {
                $_SESSION['flash_error'] = "Exception deleting product: " . $e->getMessage();
            }
            $conn->rollback();
        }
    } else {
        $_SESSION['flash_error'] = "Invalid Product ID.";
    }
    header("Location: backend.php?action=products");
    exit;
}
elseif ($action === 'categories') {
    $result = $conn->query("SELECT c.*, p.name as parent_name 
                                   FROM categories c 
                                   LEFT JOIN categories p ON c.parent_id = p.category_id 
                                   ORDER BY c.name");
    $categories = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
elseif ($action === 'edit_category') {
    $category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $is_new_category = ($category_id === 0);
    $parent_cat_sql = "SELECT category_id, name FROM categories" . (!$is_new_category ? " WHERE category_id != $category_id AND (parent_id IS NULL OR parent_id != $category_id)" : "") . " ORDER BY name";
    $parent_categories_result = $conn->query($parent_cat_sql);
    $parent_categories_list = $parent_categories_result ? $parent_categories_result->fetch_all(MYSQLI_ASSOC) : [];
    if (!$is_new_category) {
        $stmt = $conn->prepare("SELECT * FROM categories WHERE category_id = ?");
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $category = $result->fetch_assoc();
        } else {
            $_SESSION['flash_error'] = "Category not found.";
            header('Location: backend.php?action=categories');
            exit;
        }
        $stmt->close();
    } else {
         $category = ['name' => '', 'description' => '', 'parent_id' => null, 'image' => ''];
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
        $image_path = $category['image'] ?? ''; 
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $new_image_path = uploadImage($_FILES['image'], 'uploads/categories/');
            if ($new_image_path) {
                if (!empty($image_path) && $image_path !== $new_image_path && file_exists($image_path)) {
                    unlink($image_path);
                }
                $image_path = $new_image_path;
            } else {
                $_SESSION['flash_error'] = ($_SESSION['flash_error'] ?? "") . "Category image upload failed. ";
            }
        }
        if (!$is_new_category) {
            $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ?, parent_id = ?, image = ? WHERE category_id = ?");
            $stmt->bind_param("ssisi", $name, $description, $parent_id, $image_path, $category_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO categories (name, description, parent_id, image) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssis", $name, $description, $parent_id, $image_path);
        }
        if ($stmt->execute()) {
            $_SESSION['flash_message'] = "Category " . (!$is_new_category ? "updated" : "added") . " successfully!";
        } else {
            $_SESSION['flash_error'] = "Error " . (!$is_new_category ? "updating" : "adding") . " category: " . $stmt->error;
        }
        $stmt->close();
        header("Location: backend.php?action=categories");
        exit;
    }
}
elseif ($action === 'delete_category') {
    $category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($category_id > 0) {
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
        $check_stmt->bind_param("i", $category_id);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        if ($check_res['count'] > 0) {
            $_SESSION['flash_error'] = "Cannot delete category: It has {$check_res['count']} associated products. Please reassign them first.";
        } else {
            $child_check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM categories WHERE parent_id = ?");
            $child_check_stmt->bind_param("i", $category_id);
            $child_check_stmt->execute();
            $child_res = $child_check_stmt->get_result()->fetch_assoc();
            $child_check_stmt->close();
            if($child_res['count'] > 0){
                $_SESSION['flash_error'] = "Cannot delete category: It has {$child_res['count']} child categories. Please reassign them first.";
            } else {
                $img_stmt = $conn->prepare("SELECT image FROM categories WHERE category_id = ?");
                $img_stmt->bind_param("i", $category_id);
                $img_stmt->execute();
                $img_path_res = $img_stmt->get_result();
                if($img_path_res->num_rows > 0){
                    $cat_img = $img_path_res->fetch_assoc()['image'];
                    if(!empty($cat_img) && file_exists($cat_img)){
                        unlink($cat_img);
                    }
                }
                $img_stmt->close();
                $del_stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
                $del_stmt->bind_param("i", $category_id);
                if ($del_stmt->execute()) {
                    $_SESSION['flash_message'] = "Category deleted successfully!";
                } else {
                    $_SESSION['flash_error'] = "Error deleting category: " . $del_stmt->error;
                }
                $del_stmt->close();
            }
        }
    } else {
        $_SESSION['flash_error'] = "Invalid Category ID.";
    }
    header("Location: backend.php?action=categories");
    exit;
}
elseif ($action === 'tags') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['new_tag']) && !empty(trim($_POST['new_tag']))) {
            $tag_name = sanitize(trim($_POST['new_tag']));
            $check_stmt = $conn->prepare("SELECT tag_id FROM tags WHERE name = ?");
            $check_stmt->bind_param("s", $tag_name);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows === 0) {
                $insert_stmt = $conn->prepare("INSERT INTO tags (name) VALUES (?)");
                $insert_stmt->bind_param("s", $tag_name);
                if ($insert_stmt->execute()) {
                    $_SESSION['flash_message'] = "Tag added successfully!";
                } else {
                    $_SESSION['flash_error'] = "Error adding tag: " . $insert_stmt->error;
                }
                $insert_stmt->close();
            } else {
                $_SESSION['flash_error'] = "Tag already exists!";
            }
            $check_stmt->close();
        } else {
            $_SESSION['flash_error'] = "Tag name cannot be empty.";
        }
        header("Location: backend.php?action=tags");
        exit;
    }
    $result = $conn->query("SELECT * FROM tags ORDER BY name");
    $tags = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
elseif ($action === 'delete_tag') {
    $tag_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($tag_id > 0) {
        $conn->begin_transaction();
        try {
            $stmt_pt = $conn->prepare("DELETE FROM product_tags WHERE tag_id = ?");
            $stmt_pt->bind_param("i", $tag_id);
            $stmt_pt->execute();
            $stmt_pt->close();
            $stmt = $conn->prepare("DELETE FROM tags WHERE tag_id = ?");
            $stmt->bind_param("i", $tag_id);
            if ($stmt->execute()) {
                $_SESSION['flash_message'] = "Tag deleted successfully!";
                $conn->commit();
            } else {
                $_SESSION['flash_error'] = "Error deleting tag: " . $stmt->error;
                $conn->rollback();
            }
            $stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_error'] = "Exception deleting tag: " . $e->getMessage();
        }
    } else {
        $_SESSION['flash_error'] = "Invalid Tag ID.";
    }
    header("Location: backend.php?action=tags");
    exit;
}
elseif ($action === 'orders') {
    $user_id_filter = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $sql = "SELECT o.*, u.username, u.email 
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.user_id";
    if ($user_id_filter > 0) {
        $sql .= " WHERE o.user_id = ? ORDER BY o.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id_filter);
    } else {
        $sql .= " ORDER BY o.created_at DESC";
        $stmt = $conn->prepare($sql);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}
elseif ($action === 'view_order') {
    $order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $order_id > 0) {
        $new_status = sanitize($_POST['status']);
        $tracking_number = isset($_POST['tracking_number']) ? sanitize($_POST['tracking_number']) : null;
        $update_stmt = $conn->prepare("UPDATE orders SET order_status = ?, tracking_number = ? WHERE order_id = ?");
        $update_stmt->bind_param("ssi", $new_status, $tracking_number, $order_id);
        if ($update_stmt->execute()) {
            $_SESSION['flash_message'] = "Order status updated successfully!";
        } else {
            $_SESSION['flash_error'] = "Error updating order status: " . $update_stmt->error;
        }
        $update_stmt->close();
        header("Location: backend.php?action=view_order&id=$order_id");
        exit;
    }
    if ($order_id > 0) {
        $stmt = $conn->prepare("SELECT o.*, u.username, u.email, u.first_name, u.last_name, u.phone
                                       FROM orders o 
                                       LEFT JOIN users u ON o.user_id = u.user_id 
                                       WHERE o.order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $order = $result->fetch_assoc();
            $items_stmt = $conn->prepare("SELECT oi.*, p.name, p.sku, 
                                                (SELECT image_path FROM product_images WHERE product_id = p.product_id AND is_primary = 1 LIMIT 1) as image
                                                FROM order_items oi 
                                                JOIN products p ON oi.product_id = p.product_id 
                                                WHERE oi.order_id = ?");
            $items_stmt->bind_param("i", $order_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            $order_items = $items_result ? $items_result->fetch_all(MYSQLI_ASSOC) : [];
            $items_stmt->close();
        } else {
            $_SESSION['flash_error'] = "Order not found.";
        }
        $stmt->close();
    } else {
        $_SESSION['flash_error'] = "Invalid Order ID.";
    }
}
elseif ($action === 'delete_order') {
    $order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($order_id > 0) {
        // Start transaction to ensure data integrity
        $conn->begin_transaction();
        try {
            // First delete order items
            $delete_items_stmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
            $delete_items_stmt->bind_param("i", $order_id);
            $delete_items_stmt->execute();
            $delete_items_stmt->close();
            
            // Then delete the order
            $delete_order_stmt = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
            $delete_order_stmt->bind_param("i", $order_id);
            $delete_order_stmt->execute();
            $delete_order_stmt->close();
            
            $conn->commit();
            $_SESSION['flash_message'] = "Order #$order_id has been successfully deleted.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['flash_error'] = "Error deleting order: " . $e->getMessage();
        }
    } else {
        $_SESSION['flash_error'] = "Invalid order ID.";
    }
    header('Location: backend.php?action=orders');
    exit;
}
elseif ($action === 'users') {
    $result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
elseif ($action === 'edit_user') {
    $user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id > 0) {
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        $phone = sanitize($_POST['phone']);
        $is_admin_role = isset($_POST['is_admin']) ? intval($_POST['is_admin']) : 0; 
        $age_verified = isset($_POST['age_verified']) ? 1 : 0;
        if (empty($username) || empty($email)) {
            $_SESSION['flash_error'] = "Username and Email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = "Invalid email format.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, phone = ?, is_admin = ?, age_verified = ? WHERE user_id = ?");
            $stmt->bind_param("sssssiii", $username, $email, $first_name, $last_name, $phone, $is_admin_role, $age_verified, $user_id);
            if ($stmt->execute()) {
                $_SESSION['flash_message'] = "User profile updated successfully!";
            } else {
                $_SESSION['flash_error'] = "Error updating user profile: " . $stmt->error;
            }
            $stmt->close();
        }
        header("Location: backend.php?action=edit_user&id=" . $user_id);
        exit;
    }
    if ($user_id > 0) {
        $stmt = $conn->prepare("SELECT user_id, username, email, first_name, last_name, phone, is_admin, age_verified FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
        } else {
            $_SESSION['flash_error'] = "User not found.";
        }
        $stmt->close();
    } elseif ($user_id === 0 && $action === 'edit_user') { 
         $_SESSION['flash_error'] = "Adding new users directly is not supported by this form.";
    } else {
        $_SESSION['flash_error'] = "No user ID provided.";
    }
}
elseif ($action === 'delete_user') {
    $user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($user_id > 0) {
        if (isset($_SESSION['user_id']) && $user_id == $_SESSION['user_id']) {
            $_SESSION['flash_error'] = "You cannot delete your own account.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                if($stmt->affected_rows > 0){
                    $_SESSION['flash_message'] = "User (ID: $user_id) deleted successfully!";
                } else {
                    $_SESSION['flash_error'] = "User (ID: $user_id) not found or already deleted.";
                }
            } else {
                $_SESSION['flash_error'] = "Error deleting user: " . $stmt->error;
            }
            $stmt->close();
        }
    } else {
        $_SESSION['flash_error'] = "Invalid user ID for deletion.";
    }
    header("Location: backend.php?action=users");
    exit;
}
elseif ($action === 'admin_profile') {
    $admin_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $admin_id > 0) {
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        $phone = sanitize($_POST['phone']);
        if (empty($username) || empty($email)) {
            $_SESSION['flash_error'] = "Username and Email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = "Invalid email format.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, phone = ? WHERE user_id = ? AND is_admin = 1");
            $stmt->bind_param("sssssi", $username, $email, $first_name, $last_name, $phone, $admin_id);
            if ($stmt->execute()) {
                $_SESSION['flash_message'] = "Profile updated successfully!";
            } else {
                $_SESSION['flash_error'] = "Error updating profile: " . $stmt->error;
            }
            $stmt->close();
        }
        if (!empty($_POST['current_password']) || !empty($_POST['new_password']) || !empty($_POST['confirm_password'])) {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $_SESSION['flash_error'] .= (empty($_SESSION['flash_error']) ? '' : '<br>') . "All password fields are required to change password.";
            } elseif ($new_password !== $confirm_password) {
                $_SESSION['flash_error'] .= (empty($_SESSION['flash_error']) ? '' : '<br>') . "New passwords do not match.";
            } else {
                $stmt_pass = $conn->prepare("SELECT password FROM users WHERE user_id = ? AND is_admin = 1");
                $stmt_pass->bind_param("i", $admin_id);
                $stmt_pass->execute();
                $result_pass = $stmt_pass->get_result();
                if ($db_user = $result_pass->fetch_assoc()) {
                    if (password_verify($current_password, $db_user['password'])) {
                        $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt_update_pass = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                        $stmt_update_pass->bind_param("si", $hashed_new_password, $admin_id);
                        if ($stmt_update_pass->execute()) {
                            $_SESSION['flash_message'] .= (empty($_SESSION['flash_message']) ? '' : '<br>') . "Password updated successfully!";
                        } else {
                            $_SESSION['flash_error'] .= (empty($_SESSION['flash_error']) ? '' : '<br>') . "Error updating password: " . $stmt_update_pass->error;
                        }
                        $stmt_update_pass->close();
                    } else {
                        $_SESSION['flash_error'] .= (empty($_SESSION['flash_error']) ? '' : '<br>') . "Incorrect current password.";
                    }
                }
                $stmt_pass->close();
            }
        }
        header("Location: backend.php?action=admin_profile");
        exit;
    }
    if ($admin_id > 0) {
        $stmt = $conn->prepare("SELECT username, email, first_name, last_name, phone FROM users WHERE user_id = ? AND is_admin = 1");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $admin_user = $result->fetch_assoc();
        } else {
            $_SESSION['flash_error'] = "Admin user not found.";
        }
        $stmt->close();
    } else {
        $_SESSION['flash_error'] = "No admin user session found.";
    }
}
elseif ($action === 'contact_messages') {
    // Handle marking messages as read/unread
    if (isset($_GET['mark_read']) && $_GET['mark_read'] > 0) {
        $message_id = intval($_GET['mark_read']);
        $conn->query("UPDATE contact_messages SET is_read = 1 WHERE id = $message_id");
        $_SESSION['flash_message'] = "Message marked as read.";
        header('Location: backend.php?action=contact_messages');
        exit;
    }
    
    if (isset($_GET['mark_unread']) && $_GET['mark_unread'] > 0) {
        $message_id = intval($_GET['mark_unread']);
        $conn->query("UPDATE contact_messages SET is_read = 0 WHERE id = $message_id");
        $_SESSION['flash_message'] = "Message marked as unread.";
        header('Location: backend.php?action=contact_messages');
        exit;
    }
    
    // Handle deleting messages
    if (isset($_GET['delete']) && $_GET['delete'] > 0) {
        $message_id = intval($_GET['delete']);
        $conn->query("DELETE FROM contact_messages WHERE id = $message_id");
        $_SESSION['flash_message'] = "Message deleted.";
        header('Location: backend.php?action=contact_messages');
        exit;
    }
    
    // Get all contact messages
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $where_clause = '';
    if ($filter === 'unread') {
        $where_clause = 'WHERE is_read = 0';
    } elseif ($filter === 'read') {
        $where_clause = 'WHERE is_read = 1';
    }
    
    $result = $conn->query("SELECT * FROM contact_messages $where_clause ORDER BY created_at DESC");
    $contact_messages = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    
    // Get counts for filter badges
    $unread_count_res = $conn->query("SELECT COUNT(*) as count FROM contact_messages WHERE is_read = 0");
    $unread_count = $unread_count_res ? $unread_count_res->fetch_assoc()['count'] : 0;
    
    $total_count_res = $conn->query("SELECT COUNT(*) as count FROM contact_messages");
    $total_count = $total_count_res ? $total_count_res->fetch_assoc()['count'] : 0;
}
elseif ($action === 'admin_settings') {
    // Placeholder
}

// --- HTML GENERATION SECTION ---
$html_head = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Your App Your Data - Admin Dashboard</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css"><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"><style>:root{--foreground:#111111;--background:#f6f7fb;--card:#ffffff;--card-foreground:#111111;--primary:#111111;--primary-foreground:#fdfdfd;--secondary:#ffffff;--secondary-foreground:#111111;--muted:#f1f1f3;--muted-foreground:#3a3a3a;--destructive:#d92d20;--destructive-foreground:#ffffff;--border:#111111;--border-muted:#1f1f1f;--ring:#111111;--border-width:3px;--shadow-sm:0 3px 0 #111111,0 10px 20px rgba(17,17,17,.08);--shadow:0 6px 0 #111111,0 16px 32px rgba(17,17,17,.12);--shadow-md:0 8px 0 #111111,0 24px 40px rgba(17,17,17,.15);--shadow-lg:0 12px 0 #111111,0 28px 48px rgba(17,17,17,.16);--success-color:#2ecc71;--warning-color:#f39c12;--info-color:#3498db;}*{box-sizing:border-box;}body{background:radial-gradient(circle at top,#ffffff 0%,#f6f7fb 45%,#f2f3f8 100%);color:var(--foreground);font-family:"Plus Jakarta Sans",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;line-height:1.6;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;margin:0;padding:0;}.sidebar{background-color:var(--card);border-right:var(--border-width) solid var(--border);padding:1.75rem;min-height:100vh;box-shadow:var(--shadow-sm);}.sidebar .nav-link{color:var(--foreground);border-radius:0.75rem;padding:0.75rem 1rem;margin-bottom:0.5rem;transition:all .2s ease;border:var(--border-width) solid transparent;font-weight:600;font-size:0.95rem;}.sidebar .nav-link:hover{color:var(--foreground);background-color:var(--muted);border-color:var(--border);transform:translate(-2px,-2px);box-shadow:var(--shadow-sm);}.sidebar .nav-link.active{background-color:var(--primary);color:var(--primary-foreground);border-color:var(--primary);transform:translate(-2px,-2px);box-shadow:var(--shadow-sm);}.sidebar .nav-link i{margin-right:0.75rem;}.sidebar-section{margin-bottom:2rem;}.sidebar-section:last-child{margin-bottom:0;}.sidebar-section h4{font-size:1rem;font-weight:700;margin-bottom:1.25rem;color:var(--foreground);padding-bottom:0.75rem;border-bottom:var(--border-width) solid var(--border);}.brand-logo{font-size:1.5rem;font-weight:700;color:var(--foreground);letter-spacing:-0.02em;}.brand-logo span{color:var(--primary);}.admin-label{font-size:0.875rem;color:var(--muted-foreground);}.main-content{padding:2rem;background:var(--background);min-height:100vh;}.top-bar{background-color:var(--card);border-bottom:var(--border-width) solid var(--border);padding:1rem 2rem;box-shadow:var(--shadow-sm);display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;border-radius:1rem;}.top-bar h4{margin:0;color:var(--foreground);font-weight:700;}.user-dropdown .dropdown-toggle{color:var(--foreground);font-weight:600;text-decoration:none;}.user-dropdown .dropdown-toggle:hover{color:var(--foreground);}.dropdown-menu{background-color:var(--card);border:var(--border-width) solid var(--border);border-radius:0.75rem;box-shadow:var(--shadow-lg);}.dropdown-item{color:var(--foreground);transition:all .2s ease;}.dropdown-item:hover,.dropdown-item:focus{background-color:var(--primary);color:var(--primary-foreground);border-radius:0.5rem;}.dropdown-divider{border-top-color:var(--border);}.card{background-color:var(--card);border:var(--border-width) solid var(--border);border-radius:1rem;box-shadow:var(--shadow);margin-bottom:2rem;overflow:hidden;}.card:hover{box-shadow:var(--shadow-md);transform:translateY(-2px);transition:all .2s ease;}.card-header{background:linear-gradient(135deg,rgba(17,17,17,.04),rgba(17,17,17,0));border-bottom:var(--border-width) solid var(--border);padding:1.5rem;}.card-header h5,.card-header h6{margin:0;color:var(--foreground);font-weight:700;}.card-body{padding:1.5rem;color:var(--foreground);}.card-title{color:var(--foreground);font-weight:700;}.table{color:var(--foreground);}.table thead th{background-color:var(--muted);border:var(--border-width) solid var(--border);color:var(--foreground);font-weight:700;padding:1rem;vertical-align:middle;}.table td{border:var(--border-width) solid var(--border);padding:1rem;vertical-align:middle;color:var(--foreground);}.table tbody tr{transition:background-color .2s ease;}.table tbody tr:hover{background-color:var(--muted);}.alert{padding:1rem;margin-bottom:1rem;border-radius:1rem;border:var(--border-width) solid;font-size:0.875rem;}.alert-success{background-color:#dcfce7;border-color:#86efac;color:#166534;}.alert-danger{background-color:#fef2f2;border-color:#fca5a5;color:#dc2626;}.alert-info{background-color:#dbeafe;border-color:#93c5fd;color:#1e40af;}.form-control,.form-select{border-radius:0.75rem;border:var(--border-width) solid var(--border);background-color:var(--card);padding:0.65rem 0.85rem;font-size:0.9rem;color:var(--foreground);min-height:2.75rem;box-shadow:var(--shadow-sm);transition:all .2s ease;font-family:"Plus Jakarta Sans",sans-serif;}.form-control:focus,.form-select:focus{outline:none;border-color:var(--ring);transform:translate(-2px,-2px);box-shadow:var(--shadow-md);background-color:var(--card);color:var(--foreground);}.form-control::placeholder{color:var(--muted-foreground);}.form-label{font-size:0.875rem;font-weight:600;color:var(--foreground);margin-bottom:0.5rem;display:block;}.form-check-input{width:1rem;height:1rem;margin:0;border:var(--border-width) solid var(--border);border-radius:0.375rem;background-color:var(--background);}.form-check-input:checked{background-color:var(--primary);border-color:var(--primary);}.form-check-label{font-size:0.875rem;color:var(--foreground);cursor:pointer;user-select:none;}.btn{display:inline-flex;align-items:center;justify-content:center;white-space:nowrap;border-radius:0.75rem;font-size:0.95rem;font-weight:600;transition:all .2s ease;border:var(--border-width) solid var(--border);cursor:pointer;text-decoration:none;gap:0.6rem;padding:0.65rem 1.25rem;min-height:2.75rem;box-shadow:var(--shadow-sm);background-color:var(--secondary);color:var(--secondary-foreground);}.btn:focus-visible{outline:2px solid var(--ring);outline-offset:2px;}.btn:disabled{pointer-events:none;opacity:.5;}.btn-primary{background-color:var(--primary);color:var(--primary-foreground);border-color:var(--primary);box-shadow:var(--shadow);}.btn-primary:hover{transform:translate(-2px,-2px);box-shadow:var(--shadow-md);}.btn-success{background-color:var(--success-color);color:#fff;border-color:var(--success-color);box-shadow:var(--shadow);}.btn-success:hover{opacity:.9;transform:translate(-2px,-2px);}.btn-info{background-color:var(--info-color);color:#fff;border-color:var(--info-color);box-shadow:var(--shadow);}.btn-info:hover{opacity:.9;transform:translate(-2px,-2px);}.btn-warning{background-color:var(--warning-color);color:#fff;border-color:var(--warning-color);box-shadow:var(--shadow);}.btn-warning:hover{opacity:.9;transform:translate(-2px,-2px);}.btn-danger{background-color:var(--destructive);color:var(--destructive-foreground);border-color:var(--destructive);box-shadow:var(--shadow);}.btn-danger:hover{opacity:.9;transform:translate(-2px,-2px);}.btn-outline-primary{color:var(--primary);border-color:var(--border);background-color:transparent;}.btn-outline-primary:hover{background-color:var(--primary);color:var(--primary-foreground);transform:translate(-2px,-2px);box-shadow:var(--shadow);}.btn-sm{height:2rem;padding:0.25rem 0.75rem;font-size:0.75rem;}.btn-lg{height:2.75rem;padding:0.5rem 2rem;font-size:1rem;}.badge{display:inline-flex;align-items:center;border-radius:9999px;border:var(--border-width) solid var(--border);background-color:var(--secondary);padding:0.35rem 1rem;font-size:0.8rem;font-weight:600;color:var(--foreground);}.badge.bg-primary{background-color:var(--primary);color:var(--primary-foreground);border-color:var(--primary);}.badge.bg-success{background-color:var(--success-color);color:#fff;border-color:var(--success-color);}.badge.bg-warning{background-color:var(--warning-color);color:#fff;border-color:var(--warning-color);}.badge.bg-danger{background-color:var(--destructive);color:var(--destructive-foreground);border-color:var(--destructive);}.badge.bg-info{background-color:var(--info-color);color:#fff;border-color:var(--info-color);}.badge.bg-secondary{background-color:var(--muted);color:var(--muted-foreground);border-color:var(--border-muted);}.stat-icon{font-size:2.5rem;opacity:.8;}.stat-value{font-size:2rem;font-weight:700;color:var(--foreground);}.stat-label{font-size:1rem;color:var(--muted-foreground);}.dashboard-stats .card:hover{transform:translateY(-5px);}.product-img{width:60px;height:60px;object-fit:cover;border-radius:0.75rem;border:var(--border-width) solid var(--border);}.category-badge{background-color:var(--primary);color:#fff;padding:.25em .6em;border-radius:10px;font-size:.8rem;}.order-status{font-weight:700;padding:5px 10px;border-radius:20px;font-size:.8rem;text-align:center;color:#fff;}.status-pending{background-color:var(--warning-color);}.status-processing{background-color:var(--info-color);}.status-shipped{background-color:var(--success-color);}.status-delivered{background-color:#1abc9c;}.status-canceled{background-color:var(--destructive);}.product-image-card{position:relative;border:var(--border-width) solid var(--border);border-radius:0.75rem;overflow:hidden;background-color:var(--card);margin-bottom:1rem;box-shadow:var(--shadow-sm);}.product-image-card .product-image{width:100%;height:120px;object-fit:cover;display:block;}.product-image-card .image-actions{padding:1rem;text-align:center;border-top:var(--border-width) solid var(--border);background-color:var(--muted);}.product-image-card .image-actions .btn{display:block;width:100%;margin-bottom:0.5rem;}.product-image-card .image-actions .btn:last-child{margin-bottom:0;}.product-image-card .image-actions .badge{display:block;width:100%;margin-bottom:0.5rem;padding:.5em;}.input-group .form-control{border-right:0;}.input-group .input-group-text{background-color:var(--muted);border:var(--border-width) solid var(--border);color:var(--foreground);font-weight:600;}.table-hover tbody tr:hover{background-color:var(--muted);}.text-center{text-align:center;}.text-muted{color:var(--muted-foreground);}.text-danger{color:var(--destructive);}.text-success{color:var(--success-color);}.text-warning{color:var(--warning-color);}.text-info{color:var(--info-color);}.text-primary{color:var(--primary);}.mb-0{margin-bottom:0;}.mb-2{margin-bottom:0.5rem;}.mb-3{margin-bottom:0.75rem;}.mb-4{margin-bottom:1rem;}.ms-2{margin-left:0.5rem;}.ms-3{margin-left:0.75rem;}.ms-auto{margin-left:auto;}.me-2{margin-right:0.5rem;}.me-3{margin-right:0.75rem;}.me-auto{margin-right:auto;}.d-flex{display:flex;}.d-inline-block{display:inline-block;}.align-items-center{align-items:center;}.justify-content-between{justify-content:space-between;}.flex-wrap{flex-wrap:wrap;}.flex-column{flex-direction:column;}.h-100{height:100%;}.w-100{width:100%;}.mx-4{margin-left:1rem;margin-right:1rem;}h1,h2,h3,h4,h5,h6{font-weight:700;color:var(--foreground);margin:0 0 1rem 0;line-height:1.2;}h1{font-size:2.25rem;}h2{font-size:1.875rem;}h3{font-size:1.5rem;}h4{font-size:1.25rem;}h5{font-size:1.125rem;}h6{font-size:1rem;}p{margin:0 0 1rem 0;color:var(--muted-foreground);}a{color:var(--foreground);text-decoration:none;transition:all .2s ease;}a:hover{color:var(--foreground);transform:translateY(-1px);}hr{border:none;border-top:var(--border-width) solid var(--border);margin:1.5rem 0;}@media(max-width:991px){.sidebar{border-right:none;border-bottom:var(--border-width) solid var(--border);}.main-content{padding:1.5rem;}}</style></head><body>';
$html_nav = '<div class="container-fluid"><div class="row"><div class="col-md-2 px-0 sidebar"><div class="text-center my-4"><h5 class="brand-logo">Your App <span>Your Data</span></h5><div class="admin-label">Admin Dashboard</div></div><ul class="nav flex-column"><li class="nav-item"><a class="nav-link ' . ($action === 'dashboard' ? 'active' : '') . '" href="backend.php?action=dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a></li><li class="nav-item"><a class="nav-link ' . (in_array($action, ['products', 'edit_product', 'export_products_csv', 'import_products_csv']) ? 'active' : '') . '" href="backend.php?action=products"><i class="bi bi-box-seam"></i> Products</a></li><li class="nav-item"><a class="nav-link ' . (in_array($action, ['categories', 'edit_category']) ? 'active' : '') . '" href="backend.php?action=categories"><i class="bi bi-list-nested"></i> Categories</a></li><li class="nav-item"><a class="nav-link ' . ($action === 'tags' ? 'active' : '') . '" href="backend.php?action=tags"><i class="bi bi-tags"></i> Tags</a></li><li class="nav-item"><a class="nav-link ' . (in_array($action, ['orders', 'view_order']) ? 'active' : '') . '" href="backend.php?action=orders"><i class="bi bi-cart3"></i> Orders</a></li><li class="nav-item"><a class="nav-link ' . (in_array($action, ['users', 'edit_user']) ? 'active' : '') . '" href="backend.php?action=users"><i class="bi bi-people"></i> Users</a></li><li class="nav-item"><a class="nav-link ' . ($action === 'contact_messages' ? 'active' : '') . '" href="backend.php?action=contact_messages"><i class="bi bi-envelope"></i> Contact Messages</a></li><li class="nav-item mt-auto mb-3"><a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-left"></i> Logout</a></li></ul></div><div class="col-md-10 px-0"><div class="top-bar d-flex justify-content-between align-items-center"><h4 class="mb-0">' . ucfirst(str_replace('_', ' ', $action)) . '</h4><div class="user-dropdown dropdown"><div class="d-flex align-items-center dropdown-toggle" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-person-circle fs-4 me-2"></i> Admin <i class="bi bi-caret-down-fill ms-1 small"></i></div><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="backend.php?action=admin_profile"><i class="bi bi-person me-2"></i> Profile</a></li><li><hr class="dropdown-divider"></li><li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-left me-2"></i> Logout</a></li></ul></div></div><div class="main-content">' . (!empty($message) ? '<div class="alert alert-success alert-dismissible fade show" role="alert">' . $message . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>' : '') . (!empty($error) ? '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . $error . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>' : '') . '';
$html_content = ''; // Reset for action-specific content

// --- Action Specific HTML Content (with null coalescing and other fixes) ---
if ($action === 'dashboard') {
    $html_content .= '
    <!-- Key Metrics Row -->
    <div class="row dashboard-stats mb-4">
        <div class="col-md-3 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <div class="stat-icon text-success"><i class="bi bi-currency-dollar"></i></div>
                    <div class="stat-value">$' . number_format($total_revenue, 2) . '</div>
                    <div class="stat-label">Total Revenue</div>
                    <small class="text-muted">All time</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <div class="stat-icon text-primary"><i class="bi bi-graph-up"></i></div>
                    <div class="stat-value">$' . number_format($monthly_revenue, 2) . '</div>
                    <div class="stat-label">Monthly Revenue</div>
                    <small class="text-muted">Last 30 days</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <div class="stat-icon text-warning"><i class="bi bi-cart3"></i></div>
                    <div class="stat-value">' . $order_count . '</div>
                    <div class="stat-label">Total Orders</div>
                    <small class="text-warning">' . $pending_orders . ' pending</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <div class="stat-icon text-info"><i class="bi bi-people"></i></div>
                    <div class="stat-value">' . $user_count . '</div>
                    <div class="stat-label">Customers</div>
                    <small class="text-muted">Registered users</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Secondary Metrics Row -->
    <div class="row dashboard-stats mb-4">
        <div class="col-md-3 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <div class="stat-icon text-primary"><i class="bi bi-box-seam"></i></div>
                    <div class="stat-value">' . $product_count . '</div>
                    <div class="stat-label">Products</div>
                    <small class="text-muted">' . $category_count . ' categories</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <div class="stat-icon text-danger"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="stat-value">' . $low_stock_count . '</div>
                    <div class="stat-label">Low Stock</div>
                    <small class="text-danger">' . $out_of_stock_count . ' out of stock</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <div class="stat-icon text-info"><i class="bi bi-envelope"></i></div>
                    <div class="stat-value">' . $message_count . '</div>
                    <div class="stat-label">Messages</div>
                    <small class="text-warning">' . $unread_message_count . ' unread</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <div class="stat-icon text-success"><i class="bi bi-check-circle"></i></div>
                    <div class="stat-value">' . number_format(($total_revenue / max($order_count, 1)), 2) . '</div>
                    <div class="stat-label">Avg Order Value</div>
                    <small class="text-muted">Per order</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Analytics Cards Row -->
    <div class="row mb-4">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0"><i class="bi bi-graph-up me-2"></i>Top Selling Products</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Sold</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>';
    
    if (!empty($top_products)) {
        foreach ($top_products as $product) {
            $html_content .= '<tr>
                <td>' . htmlspecialchars($product['name']) . '</td>
                <td><span class="badge bg-primary">' . $product['sold_qty'] . '</span></td>
                <td>$' . number_format($product['revenue'], 2) . '</td>
            </tr>';
        }
    } else {
        $html_content .= '<tr><td colspan="3" class="text-center text-muted">No sales data available</td></tr>';
    }
    
    $html_content .= '
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0"><i class="bi bi-pie-chart me-2"></i>Order Status Distribution</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Count</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>';
    
    if (!empty($order_status_data)) {
        foreach ($order_status_data as $status) {
            $percentage = $order_count > 0 ? round(($status['count'] / $order_count) * 100, 1) : 0;
            $status_class = '';
            switch ($status['order_status']) {
                case 'pending': $status_class = 'warning'; break;
                case 'processing': $status_class = 'info'; break;
                case 'shipped': $status_class = 'primary'; break;
                case 'delivered': $status_class = 'success'; break;
                case 'canceled': $status_class = 'danger'; break;
                default: $status_class = 'secondary';
            }
            
            $html_content .= '<tr>
                <td><span class="badge bg-' . $status_class . '">' . ucfirst($status['order_status']) . '</span></td>
                <td>' . $status['count'] . '</td>
                <td>' . $percentage . '%</td>
            </tr>';
        }
    } else {
        $html_content .= '<tr><td colspan="3" class="text-center text-muted">No order data available</td></tr>';
    }
    
    $html_content .= '
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity Row -->
    <div class="row mb-4">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0"><i class="bi bi-clock me-2"></i>Recent Orders</h6>
                    <a href="backend.php?action=orders" class="btn btn-outline-primary btn-sm">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>';
    
    if (!empty($recent_orders)) {
        foreach ($recent_orders as $order) {
            $status_class = '';
            switch ($order['order_status']) {
                case 'pending': $status_class = 'warning'; break;
                case 'processing': $status_class = 'info'; break;
                case 'shipped': $status_class = 'primary'; break;
                case 'delivered': $status_class = 'success'; break;
                case 'canceled': $status_class = 'danger'; break;
                default: $status_class = 'secondary';
            }
            
            $html_content .= '<tr>
                <td><a href="backend.php?action=view_order&id=' . $order['order_id'] . '">#' . $order['order_id'] . '</a></td>
                <td>' . htmlspecialchars($order['username'] ?? 'Guest') . '</td>
                <td>$' . number_format($order['total_amount'], 2) . '</td>
                <td><span class="badge bg-' . $status_class . '">' . ucfirst($order['order_status']) . '</span></td>
            </tr>';
        }
    } else {
        $html_content .= '<tr><td colspan="4" class="text-center text-muted">No recent orders</td></tr>';
    }
    
    $html_content .= '
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0"><i class="bi bi-envelope me-2"></i>Recent Messages</h6>
                    <a href="backend.php?action=contact_messages" class="btn btn-outline-primary btn-sm">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>From</th>
                                    <th>Subject</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>';
    
    if (!empty($recent_messages)) {
        foreach ($recent_messages as $msg) {
            $status_icon = $msg['is_read'] ? '<i class="bi bi-envelope-open text-muted"></i>' : '<i class="bi bi-envelope-fill text-primary"></i>';
            $html_content .= '<tr>
                <td>' . htmlspecialchars($msg['name']) . '</td>
                <td>' . htmlspecialchars(substr($msg['subject'], 0, 30)) . (strlen($msg['subject']) > 30 ? '...' : '') . '</td>
                <td>' . date('M d', strtotime($msg['created_at'])) . '</td>
                <td>' . $status_icon . '</td>
            </tr>';
        }
    } else {
        $html_content .= '<tr><td colspan="4" class="text-center text-muted">No recent messages</td></tr>';
    }
    
    $html_content .= '
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}
elseif ($action === 'products') {
    $html_content .= '<div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
            <h5 class="card-title mb-0 me-auto">Products List</h5>
            <div class="ms-auto d-flex align-items-center">
                <a href="backend.php?action=export_products_csv" class="btn btn-success btn-sm me-2"><i class="bi bi-download"></i> Export CSV</a>
                <form action="backend.php?action=import_products_csv" method="post" enctype="multipart/form-data" class="d-inline-block me-2">
                    <div class="input-group input-group-sm">
                        <input type="file" name="products_csv_file" accept=".csv" required class="form-control" id="csvFile">
                        <button type="submit" class="btn btn-info"><i class="bi bi-upload"></i> Import CSV</button>
                    </div>
                </form>
                <a href="backend.php?action=edit_product" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add New Product</a>
            </div>
        </div>
        <div class="card-body"><div class="table-responsive"><table class="table table-hover">
            <thead><tr><th>Image</th><th>Name</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock</th><th>Featured</th><th>Actions</th></tr></thead><tbody>';
    if (!empty($products)) {
        foreach ($products as $p) {
            $image = !empty($p['primary_image']) ? htmlspecialchars($p['primary_image']) : 'uploads/placeholder.jpg';
            $html_content .= '<tr>
                <td><img src="' . $image . '" class="product-img" alt="' . htmlspecialchars($p['name'] ?? '') . '"></td>
                <td>' . htmlspecialchars($p['name'] ?? '') . '</td><td>' . htmlspecialchars($p['sku'] ?? '') . '</td>
                <td>' . (!empty($p['category_name']) ? '<span class="category-badge">' . htmlspecialchars($p['category_name']) . '</span>' : '-') . '</td>
                <td>$' . number_format($p['price'] ?? 0, 2) . (!empty($p['sale_price']) ? '<br><span class="text-success small">Sale: $' . number_format($p['sale_price'], 2) . '</span>' : '') . '</td>
                <td>' . ($p['quantity'] ?? 0) . '</td><td>' . (($p['featured'] ?? 0) ? '<i class="bi bi-star-fill text-warning"></i>' : '<i class="bi bi-star text-muted"></i>') . '</td>
                <td><div class="btn-group"><a href="backend.php?action=edit_product&id=' . $p['product_id'] . '" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a><a href="backend.php?action=delete_product&id=' . $p['product_id'] . '" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm(\'Delete this product?\')"><i class="bi bi-trash"></i></a></div></td></tr>';
        }
    } else { $html_content .= '<tr><td colspan="8" class="text-center">No products found</td></tr>'; }
    $html_content .= '</tbody></table></div></div></div>';
}
elseif ($action === 'edit_product') {
    $is_new_product = !isset($product['product_id']) || $product['product_id'] == 0;
    $form_action_id = $is_new_product ? '' : '&id=' . ($product['product_id'] ?? '');
    $html_content .= '<div class="card"><div class="card-header"><h5 class="card-title mb-0">' . ($is_new_product ? 'Add New Product' : 'Edit Product: ' . htmlspecialchars($product['name'] ?? '')) . '</h5></div>
    <div class="card-body"><form action="backend.php?action=edit_product' . $form_action_id . '" method="post" enctype="multipart/form-data"><div class="row"><div class="col-md-8">
    <div class="mb-3"><label for="name" class="form-label">Product Name <span class="text-danger">*</span></label><input type="text" class="form-control" id="name" name="name" value="' . htmlspecialchars($product['name'] ?? '') . '" required></div>
    <div class="mb-3"><label for="description" class="form-label">Description</label><textarea class="form-control" id="description" name="description" rows="5">' . htmlspecialchars($product['description'] ?? '') . '</textarea></div>
    <div class="row"><div class="col-md-4 mb-3"><label for="price" class="form-label">Regular Price ($) <span class="text-danger">*</span></label><input type="number" class="form-control" id="price" name="price" step="0.01" value="' . htmlspecialchars($product['price'] ?? '') . '" required></div><div class="col-md-4 mb-3"><label for="sale_price" class="form-label">Sale Price ($)</label><input type="number" class="form-control" id="sale_price" name="sale_price" step="0.01" value="' . htmlspecialchars($product['sale_price'] ?? '') . '"></div><div class="col-md-4 mb-3"><label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label><input type="number" class="form-control" id="quantity" name="quantity" value="' . htmlspecialchars($product['quantity'] ?? '0') . '" required></div></div>
    <div class="row"><div class="col-md-6 mb-3"><label for="sku" class="form-label">SKU</label><input type="text" class="form-control" id="sku" name="sku" value="' . htmlspecialchars($product['sku'] ?? '') . '"></div><div class="col-md-6 mb-3"><label for="category_id" class="form-label">Category <span class="text-danger">*</span></label><select class="form-select" id="category_id" name="category_id" required><option value="">Select Category</option>';
    foreach ($categories as $cat) { $selected = (isset($product['category_id']) && $product['category_id'] == $cat['category_id']) ? 'selected' : ''; $html_content .= '<option value="' . $cat['category_id'] . '" ' . $selected . '>' . htmlspecialchars($cat['name']) . '</option>'; }
    $current_image_count = 0;
    if (!$is_new_product && isset($product['product_id'])) {
        $count_res = $conn->query("SELECT COUNT(*) as count FROM product_images WHERE product_id = " . intval($product['product_id']));
        if ($count_res) {
            $current_image_count = $count_res->fetch_assoc()['count'];
        }
    }
    $remaining_slots = 5 - $current_image_count;
    $html_content .= '</select></div></div><div class="mb-3"><label for="images" class="form-label">Upload New Images</label><input type="file" class="form-control" id="images" name="images[]" multiple accept="image/*" ' . ($remaining_slots <= 0 ? 'disabled' : '') . '><small class="text-muted">Maximum 5 images per product. Current: ' . $current_image_count . '/5. ' . ($remaining_slots > 0 ? 'You can upload ' . $remaining_slots . ' more image(s).' : 'Maximum reached - remove images to add new ones.') . '</small></div>';
    if (!$is_new_product && !empty($product['images'])) {
        $html_content .= '<h6>Current Images</h6><div class="row">';
        foreach ($product['images'] as $img) {
            $html_content .= '<div class="col-md-4 col-lg-3"><div class="product-image-card"><img src="' . htmlspecialchars($img['image_path'] ?? '') . '" class="product-image" alt="Product Image"><div class="image-actions">' . ($img['is_primary'] ? '<span class="badge bg-primary">Primary</span>' : '<a href="backend.php?action=set_primary_image&image_id=' . $img['image_id'] . '&product_id=' . ($product['product_id'] ?? '') . '" class="btn btn-sm btn-outline-success">Set Primary</a>') . '<a href="backend.php?action=delete_image&image_id=' . $img['image_id'] . '&product_id=' . ($product['product_id'] ?? '') . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\'Delete this image?\')"><i class="bi bi-trash"></i> Remove</a></div></div></div>';
        } $html_content .= '</div><hr>';
    }
    $html_content .= '</div> <div class="col-md-4"><div class="card mb-3 bg-dark-surface-2"><div class="card-body"><h6 class="card-title">Publish</h6><hr><div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="featured" name="featured" ' . (isset($product['featured']) && $product['featured'] ? 'checked' : '') . '><label class="form-check-label" for="featured">Featured Product</label></div><button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i> ' . ($is_new_product ? 'Add Product' : 'Update Product') . '</button>' . (!$is_new_product ? '<a href="backend.php?action=delete_product&id=' . ($product['product_id'] ?? '') . '" class="btn btn-danger w-100 mt-2" onclick="return confirm(\'Delete this product?\')"><i class="bi bi-trash"></i> Delete Product</a>' : '') . '<a href="backend.php?action=products" class="btn btn-outline-secondary w-100 mt-2">Cancel</a></div></div><div class="card mb-3 bg-dark-surface-2"><div class="card-body"><h6 class="card-title">Tags</h6><hr>';
    if (!empty($tags)) { foreach ($tags as $tag) { $checked = isset($product['tags']) && in_array($tag['tag_id'], $product['tags']) ? 'checked' : ''; $html_content .= '<div class="form-check mb-1"><input class="form-check-input" type="checkbox" id="tag_' . $tag['tag_id'] . '" name="tags[]" value="' . $tag['tag_id'] . '" ' . $checked . '><label class="form-check-label" for="tag_' . $tag['tag_id'] . '">' . htmlspecialchars($tag['name']) . '</label></div>'; } } else { $html_content .= '<p class="small text-muted">No tags defined.</p>'; }
    $html_content .= '<a href="backend.php?action=tags" class="btn btn-sm btn-outline-light mt-2"><i class="bi bi-plus-circle"></i> Manage Tags</a>
                      </div>
                  </div>
                  
                  <div class="card bg-dark-surface-2">
                      <div class="card-body">
                          <h6 class="card-title">Additional Details <small class="text-muted">(Optional)</small></h6>
                          <hr>
                          <div class="mb-3">
                              <label for="weight" class="form-label">Weight (lbs)</label>
                              <input type="number" class="form-control form-control-sm" id="weight" name="weight" step="0.01" value="' . htmlspecialchars($product['weight'] ?? '') . '" placeholder="e.g., 0.5">
                          </div>
                          <div class="mb-3">
                              <label for="dimensions" class="form-label">Dimensions</label>
                              <input type="text" class="form-control form-control-sm" id="dimensions" name="dimensions" maxlength="255" value="' . htmlspecialchars($product['dimensions'] ?? '') . '" placeholder="e.g., 10in H x 4in W x 3in D">
                          </div>
                          <div class="mb-3">
                              <label for="material" class="form-label">Material</label>
                              <input type="text" class="form-control form-control-sm" id="material" name="material" value="' . htmlspecialchars($product['material'] ?? '') . '" placeholder="e.g., Borosilicate Glass">
                          </div>
                          <div class="mb-0">
                              <label for="color" class="form-label">Color</label>
                              <input type="text" class="form-control form-control-sm" id="color" name="color" value="' . htmlspecialchars($product['color'] ?? '') . '" placeholder="e.g., Clear, Blue, etc.">
                          </div>
                      </div>
                  </div>
              </div>
          </div>
      </form>
  </div>
</div>';
}
elseif ($action === 'categories') {
    $html_content .= '<div class="card"><div class="card-header d-flex justify-content-between align-items-center"><h5 class="card-title mb-0">Categories List</h5><a href="backend.php?action=edit_category" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add New Category</a></div><div class="card-body"><div class="table-responsive"><table class="table table-hover"><thead><tr><th>Image</th><th>Name</th><th>Parent Category</th><th>Products</th><th>Actions</th></tr></thead><tbody>';
    if (!empty($categories)) { foreach ($categories as $cat) { $image = !empty($cat['image']) ? htmlspecialchars($cat['image']) : 'uploads/placeholder.jpg'; $count_result = $conn->query("SELECT COUNT(*) as count FROM products WHERE category_id = " . $cat['category_id']); $product_count_cat = $count_result ? $count_result->fetch_assoc()['count'] : 0; $html_content .= '<tr><td><img src="' . $image . '" class="product-img" alt="' . htmlspecialchars($cat['name'] ?? '') . '" style="width:50px;height:50px;"></td><td>' . htmlspecialchars($cat['name'] ?? '') . '</td><td>' . (!empty($cat['parent_name']) ? htmlspecialchars($cat['parent_name']) : '-') . '</td><td>' . $product_count_cat . '</td><td><div class="btn-group"><a href="backend.php?action=edit_category&id=' . $cat['category_id'] . '" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a><a href="backend.php?action=delete_category&id=' . $cat['category_id'] . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\'Are you sure?\')"><i class="bi bi-trash"></i></a></div></td></tr>'; } } else { $html_content .= '<tr><td colspan="5" class="text-center">No categories found</td></tr>'; }
    $html_content .= '</tbody></table></div></div></div>';
}
elseif ($action === 'edit_category') {
    $is_new_category = !isset($category['category_id']) || $category['category_id'] == 0;
    $form_action_id = $is_new_category ? '' : '&id=' . ($category['category_id'] ?? '');
    $html_content .= '<div class="card"><div class="card-header"><h5 class="card-title mb-0">' . ($is_new_category ? 'Add New Category' : 'Edit Category: ' . htmlspecialchars($category['name'] ?? '')) . '</h5></div><div class="card-body"><form action="backend.php?action=edit_category' . $form_action_id . '" method="post" enctype="multipart/form-data"><div class="mb-3"><label for="name" class="form-label">Category Name</label><input type="text" class="form-control" id="name" name="name" value="' . htmlspecialchars($category['name'] ?? '') . '" required></div><div class="mb-3"><label for="description" class="form-label">Description</label><textarea class="form-control" id="description" name="description" rows="3">' . htmlspecialchars($category['description'] ?? '') . '</textarea></div><div class="mb-3"><label for="parent_id" class="form-label">Parent Category</label><select class="form-select" id="parent_id" name="parent_id"><option value="">None (Top Level)</option>';
    foreach ($parent_categories_list as $p_cat) { $selected = (isset($category['parent_id']) && $category['parent_id'] == $p_cat['category_id']) ? 'selected' : ''; $html_content .= '<option value="' . $p_cat['category_id'] . '" ' . $selected . '>' . htmlspecialchars($p_cat['name']) . '</option>'; }
    $html_content .= '</select></div><div class="mb-3"><label for="image" class="form-label">Category Image</label><input type="file" class="form-control" id="image" name="image" accept="image/*">';
    if (!$is_new_category && !empty($category['image'])) { $html_content .= '<div class="mt-2"><img src="' . htmlspecialchars($category['image'] ?? '') . '" alt="Current Image" style="max-width:100px;max-height:100px;border-radius:4px;"></div>'; }
    $html_content .= '</div><div class="text-end mt-4"><a href="backend.php?action=categories" class="btn btn-outline-secondary me-2">Cancel</a><button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> ' . ($is_new_category ? 'Add Category' : 'Update Category') . '</button></div></form></div></div>';
}
elseif ($action === 'tags') {
    $html_content .= '<div class="row"><div class="col-md-6"><div class="card"><div class="card-header"><h5 class="card-title mb-0">Manage Tags</h5></div><div class="card-body"><form action="backend.php?action=tags" method="post" class="mb-4"><div class="input-group"><input type="text" class="form-control" name="new_tag" placeholder="Enter new tag name..." required><button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Tag</button></div></form><div class="table-responsive"><table class="table table-hover"><thead><tr><th>Tag Name</th><th>Actions</th></tr></thead><tbody>';
    if (!empty($tags)) { foreach ($tags as $tag) { $html_content .= '<tr><td>' . htmlspecialchars($tag['name'] ?? '') . '</td><td><a href="backend.php?action=delete_tag&id=' . $tag['tag_id'] . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\'Delete this tag?\')"><i class="bi bi-trash"></i></a></td></tr>'; } } else { $html_content .= '<tr><td colspan="2" class="text-center">No tags found.</td></tr>'; }
    $html_content .= '</tbody></table></div></div></div></div><div class="col-md-6"></div></div>';
}
elseif ($action === 'orders') {
    $html_content .= '<div class="card"><div class="card-header"><h5 class="card-title mb-0">Orders List</h5></div><div class="card-body"><div class="table-responsive"><table class="table table-hover"><thead><tr><th>Order ID</th><th>Customer</th><th>Date</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    if(!empty($orders)){ foreach($orders as $o){ $html_content .= '<tr><td>#' . ($o['order_id'] ?? '') . '</td><td>' . htmlspecialchars($o['username'] ?? 'N/A') . '</td><td>' . date('M d, Y H:i', strtotime($o['created_at'] ?? time())) . '</td><td>$' . number_format($o['total_amount'] ?? 0, 2) . '</td><td><span class="order-status status-' . htmlspecialchars(strtolower($o['order_status'] ?? 'pending')) . '">' . ucfirst(htmlspecialchars($o['order_status'] ?? 'Pending')) . '</span></td><td><div class="btn-group"><a href="backend.php?action=view_order&id=' . ($o['order_id'] ?? '') . '" class="btn btn-sm btn-outline-primary" title="View Order"><i class="bi bi-eye"></i></a><a href="backend.php?action=delete_order&id=' . ($o['order_id'] ?? '') . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\'Are you sure you want to delete Order #' . ($o['order_id'] ?? '') . '? This action cannot be undone and will permanently remove the order and all its items.\')" title="Delete Order"><i class="bi bi-trash"></i></a></div></td></tr>'; } } else { $html_content .= '<tr><td colspan="6" class="text-center">No orders found.</td></tr>'; }
    $html_content .= '</tbody></table></div></div></div>';
}
elseif ($action === 'view_order') {
    if ($order) {
        $html_content .= '<div class="card"><div class="card-header d-flex justify-content-between align-items-center"><h5 class="card-title mb-0">Order Details #' . ($order['order_id'] ?? '') . '</h5><span class="order-status status-' . strtolower(htmlspecialchars($order['order_status'] ?? 'pending')) . '">' . ucfirst(htmlspecialchars($order['order_status'] ?? 'Pending')) . '</span></div><div class="card-body"><div class="row mb-4"><div class="col-md-6"><h6>Customer:</h6><p>' . htmlspecialchars(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')) . '<br>' . htmlspecialchars($order['email'] ?? '') . '<br>' . htmlspecialchars($order['phone'] ?? 'N/A') . '</p></div><div class="col-md-6"><h6>Order Info:</h6><p>Date: ' . date('M d, Y H:i', strtotime($order['created_at'] ?? time())) . '<br>Payment: ' . htmlspecialchars($order['payment_method'] ?? 'N/A') . '<br>Tracking: ' . (!empty($order['tracking_number']) ? htmlspecialchars($order['tracking_number']) : 'N/A') . '</p></div></div><div class="row mb-4"><div class="col-md-6"><h6>Shipping Address:</h6><address>' . nl2br(htmlspecialchars($order['shipping_address'] ?? '')) . '</address></div><div class="col-md-6"><h6>Billing Address:</h6><address>' . nl2br(htmlspecialchars($order['billing_address'] ?? '')) . '</address></div></div><h6>Order Items:</h6><div class="table-responsive mb-4"><table class="table"><thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Total</th></tr></thead><tbody>';
        $subtotal = 0; foreach ($order_items as $item) { $item_total = ($item['price'] ?? 0) * ($item['quantity'] ?? 0); $subtotal += $item_total; $image = !empty($item['image']) ? htmlspecialchars($item['image']) : 'uploads/placeholder.jpg'; $html_content .= '<tr><td><img src="'.$image.'" class="product-img me-2" style="width:40px;height:40px;" alt="">' . htmlspecialchars($item['name'] ?? 'N/A') . '<br><small class="text-muted">SKU: ' . htmlspecialchars($item['sku'] ?? 'N/A') . '</small></td><td>$' . number_format($item['price'] ?? 0, 2) . '</td><td>' . ($item['quantity'] ?? 0) . '</td><td>$' . number_format($item_total, 2) . '</td></tr>'; }
        $html_content .= '</tbody><tfoot><tr><td colspan="3" class="text-end"><strong>Subtotal:</strong></td><td>$' . number_format($subtotal, 2) . '</td></tr><tr><td colspan="3" class="text-end"><strong>Shipping:</strong></td><td>$' . number_format(($order['total_amount'] ?? 0) - $subtotal, 2) . '</td></tr><tr><td colspan="3" class="text-end"><strong>Total:</strong></td><td><strong>$' . number_format($order['total_amount'] ?? 0, 2) . '</strong></td></tr></tfoot></table></div><hr><h6>Update Order:</h6><form action="backend.php?action=view_order&id=' . ($order['order_id'] ?? '') . '" method="post"><div class="row align-items-end"><div class="col-md-5 mb-3"><label for="status" class="form-label">Order Status</label><select class="form-select" id="status" name="status">';
        $statuses = ['pending', 'processing', 'shipped', 'delivered', 'canceled']; foreach ($statuses as $s) { $html_content .= '<option value="' . $s . '" ' . (($order['order_status'] ?? '') == $s ? 'selected' : '') . '>' . ucfirst($s) . '</option>'; }
        $html_content .= '</select></div><div class="col-md-5 mb-3"><label for="tracking_number" class="form-label">Tracking Number</label><input type="text" class="form-control" id="tracking_number" name="tracking_number" value="' . htmlspecialchars($order['tracking_number'] ?? '') . '"></div><div class="col-md-2 mb-3"><button type="submit" class="btn btn-primary w-100">Update</button></div></div></form></div><div class="card-footer text-center"><a href="backend.php?action=orders" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i> Back to Orders</a><a href="backend.php?action=delete_order&id=' . ($order['order_id'] ?? '') . '" class="btn btn-outline-danger" onclick="return confirm(\'Are you sure you want to delete Order #' . ($order['order_id'] ?? '') . '? This action cannot be undone and will permanently remove the order and all its items.\')" title="Delete Order"><i class="bi bi-trash"></i> Delete Order</a></div></div>';
    } else { $html_content .= '<div class="alert alert-danger">Order not found or no ID specified.</div>'; }
}
elseif ($action === 'users') {
    $html_content .= '<div class="card"><div class="card-header"><h5 class="card-title mb-0">Users List</h5></div><div class="card-body"><div class="table-responsive"><table class="table table-hover"><thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Age Verified</th><th>Registered</th><th>Actions</th></tr></thead><tbody>';
    if(!empty($users)){ foreach($users as $u){ $html_content .= '<tr><td>' . ($u['user_id'] ?? '') . '</td><td>' . htmlspecialchars($u['username'] ?? '') . '</td><td>' . htmlspecialchars($u['email'] ?? '') . '</td><td>' . (($u['is_admin'] ?? 0) ? '<span class="badge bg-danger">Admin</span>' : '<span class="badge bg-secondary">Customer</span>') . '</td><td>' . (($u['age_verified'] ?? 0) ? '<i class="bi bi-check-circle-fill text-success"></i> Yes' : '<i class="bi bi-x-circle-fill text-danger"></i> No') . '</td><td>' . date('M d, Y', strtotime($u['created_at'] ?? time())) . '</td><td><div class="btn-group"><a href="backend.php?action=edit_user&id=' . ($u['user_id'] ?? '') . '" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a><a href="backend.php?action=orders&user_id=' . ($u['user_id'] ?? '') . '" class="btn btn-sm btn-outline-info"><i class="bi bi-cart3"></i> Orders</a><a href="backend.php?action=delete_user&id=' . ($u['user_id'] ?? '') . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\'Delete this user?\')"><i class="bi bi-trash"></i> Delete</a></div></td></tr>'; } } else { $html_content .= '<tr><td colspan="7" class="text-center">No users found.</td></tr>'; }
    $html_content .= '</tbody></table></div></div></div>';
}
elseif ($action === 'edit_user') {
    if ($user_data) {
        $html_content .= '<div class="card"><div class="card-header"><h5 class="card-title mb-0">Edit User: ' . htmlspecialchars($user_data['username'] ?? '') . '</h5></div><div class="card-body"><form method="POST" action="backend.php?action=edit_user&id=' . ($user_data['user_id'] ?? '') . '"><div class="row"><div class="col-md-6 mb-3"><label for="username" class="form-label">Username</label><input type="text" class="form-control" id="username" name="username" value="' . htmlspecialchars($user_data['username'] ?? '') . '" required></div><div class="col-md-6 mb-3"><label for="email" class="form-label">Email</label><input type="email" class="form-control" id="email" name="email" value="' . htmlspecialchars($user_data['email'] ?? '') . '" required></div></div><div class="row"><div class="col-md-6 mb-3"><label for="first_name" class="form-label">First Name</label><input type="text" class="form-control" id="first_name" name="first_name" value="' . htmlspecialchars($user_data['first_name'] ?? '') . '"></div><div class="col-md-6 mb-3"><label for="last_name" class="form-label">Last Name</label><input type="text" class="form-control" id="last_name" name="last_name" value="' . htmlspecialchars($user_data['last_name'] ?? '') . '"></div></div><div class="mb-3"><label for="phone" class="form-label">Phone</label><input type="text" class="form-control" id="phone" name="phone" value="' . htmlspecialchars($user_data['phone'] ?? '') . '"></div><hr><div class="row"><div class="col-md-6 mb-3"><label for="is_admin" class="form-label">User Role</label><select class="form-select" id="is_admin" name="is_admin"><option value="0" ' . (!($user_data['is_admin'] ?? 0) ? 'selected' : '') . '>Customer</option><option value="1" ' . (($user_data['is_admin'] ?? 0) ? 'selected' : '') . '>Admin</option></select></div><div class="col-md-6 mb-3 align-self-center"><div class="form-check form-switch mt-3"><input class="form-check-input" type="checkbox" id="age_verified" name="age_verified" ' . (($user_data['age_verified'] ?? 0) ? 'checked' : '') . '><label class="form-check-label" for="age_verified">Age Verified</label></div></div></div><div class="text-end mt-4"><a href="backend.php?action=users" class="btn btn-outline-secondary me-2">Cancel</a><button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button></div></form></div></div>';
    } else { $html_content .= '<div class="alert alert-danger">User not found or ID not specified.</div>'; }
}
elseif ($action === 'admin_profile') {
     if ($admin_user) {
        $html_content .= '<div class="card"><div class="card-header"><h5 class="card-title mb-0">Admin Profile</h5></div><div class="card-body"><form method="POST" action="backend.php?action=admin_profile"><h6>Account Information</h6><div class="row"><div class="col-md-6 mb-3"><label for="username" class="form-label">Username</label><input type="text" class="form-control" id="username" name="username" value="' . htmlspecialchars($admin_user['username'] ?? '') . '" required></div><div class="col-md-6 mb-3"><label for="email" class="form-label">Email</label><input type="email" class="form-control" id="email" name="email" value="' . htmlspecialchars($admin_user['email'] ?? '') . '" required></div></div><div class="row"><div class="col-md-6 mb-3"><label for="first_name" class="form-label">First Name</label><input type="text" class="form-control" id="first_name" name="first_name" value="' . htmlspecialchars($admin_user['first_name'] ?? '') . '"></div><div class="col-md-6 mb-3"><label for="last_name" class="form-label">Last Name</label><input type="text" class="form-control" id="last_name" name="last_name" value="' . htmlspecialchars($admin_user['last_name'] ?? '') . '"></div></div><div class="mb-3"><label for="phone" class="form-label">Phone</label><input type="text" class="form-control" id="phone" name="phone" value="' . htmlspecialchars($admin_user['phone'] ?? '') . '"></div><hr class="my-4"><h6>Change Password</h6><div class="mb-3"><label for="current_password" class="form-label">Current Password</label><input type="password" class="form-control" id="current_password" name="current_password"><small class="form-text text-muted">Leave blank if not changing.</small></div><div class="row"><div class="col-md-6 mb-3"><label for="new_password" class="form-label">New Password</label><input type="password" class="form-control" id="new_password" name="new_password"></div><div class="col-md-6 mb-3"><label for="confirm_password" class="form-label">Confirm New Password</label><input type="password" class="form-control" id="confirm_password" name="confirm_password"></div></div><div class="text-end mt-3"><button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Save Changes</button></div></form></div></div>';
    } else { $html_content .= '<div class="alert alert-danger">Could not load admin profile.</div>'; }
}
elseif ($action === 'contact_messages') {
    $html_content .= '<div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Contact Messages</h5>
            <div>
                <a href="backend.php?action=contact_messages&filter=all" class="btn btn-sm ' . ($filter === 'all' ? 'btn-primary' : 'btn-outline-secondary') . '">All (' . $total_count . ')</a>
                <a href="backend.php?action=contact_messages&filter=unread" class="btn btn-sm ' . ($filter === 'unread' ? 'btn-primary' : 'btn-outline-secondary') . '">Unread (' . $unread_count . ')</a>
                <a href="backend.php?action=contact_messages&filter=read" class="btn btn-sm ' . ($filter === 'read' ? 'btn-primary' : 'btn-outline-secondary') . '">Read</a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Subject</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>';
    
    if (!empty($contact_messages)) {
        foreach ($contact_messages as $msg) {
            $status_icon = $msg['is_read'] ? '<i class="bi bi-envelope-open text-muted"></i>' : '<i class="bi bi-envelope-fill text-primary"></i>';
            $row_class = $msg['is_read'] ? '' : 'table-warning';
            
            $html_content .= '<tr class="' . $row_class . '">
                <td>' . $status_icon . '</td>
                <td>' . htmlspecialchars($msg['name']) . '</td>
                <td>' . htmlspecialchars($msg['email']) . '</td>
                <td>' . htmlspecialchars(substr($msg['subject'], 0, 50)) . (strlen($msg['subject']) > 50 ? '...' : '') . '</td>
                <td>' . date('M d, Y H:i', strtotime($msg['created_at'])) . '</td>
                <td>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#messageModal' . $msg['id'] . '">
                            <i class="bi bi-eye"></i> View
                        </button>';
            
            if ($msg['is_read']) {
                $html_content .= '<a href="backend.php?action=contact_messages&mark_unread=' . $msg['id'] . '&filter=' . $filter . '" class="btn btn-sm btn-outline-warning" title="Mark as Unread">
                    <i class="bi bi-envelope"></i>
                </a>';
            } else {
                $html_content .= '<a href="backend.php?action=contact_messages&mark_read=' . $msg['id'] . '&filter=' . $filter . '" class="btn btn-sm btn-outline-success" title="Mark as Read">
                    <i class="bi bi-envelope-open"></i>
                </a>';
            }
            
            $html_content .= '<a href="backend.php?action=contact_messages&delete=' . $msg['id'] . '&filter=' . $filter . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\'Delete this message?\')" title="Delete">
                            <i class="bi bi-trash"></i>
                        </a>
                    </div>
                </td>
            </tr>';
        }
    } else {
        $html_content .= '<tr><td colspan="6" class="text-center">No messages found.</td></tr>';
    }
    
    $html_content .= '</tbody>
                </table>
            </div>
        </div>
    </div>';
    
    // Add modals for viewing messages
    if (!empty($contact_messages)) {
        foreach ($contact_messages as $msg) {
            $html_content .= '
            <div class="modal fade" id="messageModal' . $msg['id'] . '" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content bg-dark">
                        <div class="modal-header">
                            <h5 class="modal-title">Message from ' . htmlspecialchars($msg['name']) . '</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Name:</strong> ' . htmlspecialchars($msg['name']) . '
                                </div>
                                <div class="col-md-6">
                                    <strong>Email:</strong> <a href="mailto:' . htmlspecialchars($msg['email']) . '">' . htmlspecialchars($msg['email']) . '</a>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Subject:</strong> ' . htmlspecialchars($msg['subject']) . '
                                </div>
                                <div class="col-md-6">
                                    <strong>Date:</strong> ' . date('M d, Y H:i', strtotime($msg['created_at'])) . '
                                </div>
                            </div>
                            <div class="mb-3">
                                <strong>Message:</strong>
                                <div class="mt-2 p-3 bg-dark-surface-2 rounded">
                                    ' . nl2br(htmlspecialchars($msg['message'])) . '
                                </div>
                            </div>
                            <div class="text-muted small">
                                IP Address: ' . htmlspecialchars($msg['ip_address']) . '
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="mailto:' . htmlspecialchars($msg['email']) . '?subject=Re: ' . htmlspecialchars($msg['subject']) . '" class="btn btn-primary">
                                <i class="bi bi-reply"></i> Reply via Email
                            </a>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>';
        }
    }
}
else {
    $html_content .= '<div class="alert alert-warning">The requested page or action was not found.</div>';
}

$html_footer = '</div></div></div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script><script>document.addEventListener("DOMContentLoaded",function(){const e=document.getElementById("csvFile");e&&(e.value="")});</script></body></html>';
echo $html_head . $html_nav . $html_content . $html_footer;
if ($conn) {
    $conn->close();
}
?>
