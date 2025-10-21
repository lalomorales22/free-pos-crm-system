<?php
session_start();
require_once __DIR__ . '/applications/config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
    exit;
}

// Handle clear chat action
if (isset($input['action']) && $input['action'] === 'clear_chat') {
    $_SESSION['chat_history'] = [];
    echo json_encode(['success' => true, 'message' => 'Chat history cleared']);
    exit;
}

// Validate message input
if (!isset($input['message']) || empty(trim($input['message']))) {
    echo json_encode(['success' => false, 'error' => 'Message is required']);
    exit;
}

$user_message = trim($input['message']);

// Initialize chat history if not exists
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// System prompt with company information
$system_prompt = "You are an AI assistant for Your App Your Data, a modern POS + CRM sandbox. Here's what you should know about the project:

PLATFORM OVERVIEW:
- Your App Your Data lets teams explore checkout, inventory, and CRM workflows without giving up control of their data.
- The storefront, admin dashboard, and AI assistant all run on the same PHP/MySQL stack.
- The theme embraces white surfaces, bold black borders, rounded corners, and playful shadows.

CORE MODULES:
- Product catalog with categories, tags, pricing, media, and inventory counts.
- Customer accounts, saved addresses, order history, and password management.
- Cart and checkout flow with session persistence, quantity updates, and flexible payment options.
- Admin dashboard for managing products, customers, orders, tags, categories, and promotions.
- Contact center with incoming messages, AI chat history, and analytics views.

KEY CAPABILITIES:
- Offline-friendly POS sessions with quick cart editing and order confirmation pages.
- CRM utilities such as loyalty-ready customer data, address books, and order exports.
- Optional integrations (payments, analytics, messaging) that can be enabled in self-hosted deployments.
- AI assistant that provides guidance on operations, configuration, and feature usage.

SERVICE PRINCIPLES:
- Data ownership stays with the operator—sandbox data can be exported or reset.
- Demo accounts should avoid live customer or payment information; it is a testing environment only.
- Support responses should be clear, friendly, and geared toward builders evaluating the stack.

Tone: helpful, professional, and encouraging experimentation. Offer actionable guidance and reference relevant modules when answering questions.";

try {
    // Prepare messages for API call
    $messages = [
        [
            'role' => 'system',
            'content' => $system_prompt
        ]
    ];

    // Add chat history (limit to last 10 exchanges to manage token usage)
    $recent_history = array_slice($_SESSION['chat_history'], -10);
    foreach ($recent_history as $msg) {
        $messages[] = $msg;
    }

    // Add current user message
    $messages[] = [
        'role' => 'user',
        'content' => $user_message
    ];

    // Prepare API request
    $api_data = [
        'messages' => $messages,
        'model' => XAI_MODEL,
        'stream' => false,
        'temperature' => 0.7,
        'max_tokens' => 1000
    ];

    // Make API request to XAI
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => XAI_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($api_data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . XAI_API_KEY
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Handle cURL errors
    if ($curl_error) {
        error_log("Chat API cURL Error: " . $curl_error);
        echo json_encode(['success' => false, 'error' => 'Network error occurred. Please try again.']);
        exit;
    }

    // Handle HTTP errors
    if ($http_code !== 200) {
        error_log("Chat API HTTP Error: " . $http_code . " - " . $response);
        echo json_encode(['success' => false, 'error' => 'API service temporarily unavailable. Please try again later.']);
        exit;
    }

    // Parse API response
    $api_response = json_decode($response, true);
    
    if (!$api_response || !isset($api_response['choices'][0]['message']['content'])) {
        error_log("Chat API Invalid Response: " . $response);
        echo json_encode(['success' => false, 'error' => 'Invalid response from AI service. Please try again.']);
        exit;
    }

    $ai_response = trim($api_response['choices'][0]['message']['content']);

    // Add messages to chat history
    $_SESSION['chat_history'][] = [
        'role' => 'user',
        'content' => $user_message
    ];
    
    $_SESSION['chat_history'][] = [
        'role' => 'assistant',
        'content' => $ai_response
    ];

    // Limit chat history to prevent session from getting too large
    if (count($_SESSION['chat_history']) > 20) {
        $_SESSION['chat_history'] = array_slice($_SESSION['chat_history'], -20);
    }

    // Log successful interaction (optional - for analytics)
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $tokens_used = isset($api_response['usage']['total_tokens']) ? $api_response['usage']['total_tokens'] : 0;
    
    // Optional: Store in database for analytics (uncomment if you created the chat_interactions table)
    /*
    try {
        $stmt = $conn->prepare("INSERT INTO chat_interactions (user_id, session_id, user_message, ai_response, tokens_used, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $session_id = session_id();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $stmt->bind_param("isssiis", $user_id, $session_id, $user_message, $ai_response, $tokens_used, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $db_e) {
        // Don't fail the chat if database logging fails
        error_log("Chat DB Logging Error: " . $db_e->getMessage());
    }
    */
    
    // Log to PHP error log for debugging
    $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => $user_id,
        'session_id' => session_id(),
        'tokens_used' => $tokens_used
    ];
    error_log("Chat Interaction: " . json_encode($log_data));

    // Return successful response
    echo json_encode([
        'success' => true,
        'response' => $ai_response,
        'tokens_used' => $tokens_used
    ]);

} catch (Exception $e) {
    error_log("Chat API Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An unexpected error occurred. Please try again.']);
}
?> 