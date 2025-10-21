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

// Initialize chat session if not exists
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// Calculate cart total items for badge
$cart_items_count = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_items_count += $item['quantity'];
    }
}

$search_term_from_header = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get all categories for navigation
$categories_query = "SELECT category_id, name FROM categories WHERE parent_id IS NULL ORDER BY name";
$categories_result = $conn->query($categories_query);
$nav_categories = $categories_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chat Assistant - Your App Your Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Chat-specific styles */
        .chat-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        .chat-wrapper {
            border: 1px solid var(--border);
            border-radius: calc(var(--radius) * 2);
            overflow: hidden;
            background-color: var(--card);
            box-shadow: var(--shadow-lg);
        }

        .chat-header {
            background-color: var(--muted);
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid var(--border);
        }

        .chat-header h1 {
            margin: 0;
            color: var(--foreground);
            font-weight: 700;
        }

        .chat-header p {
            margin: 0.5rem 0 0 0;
            color: var(--muted-foreground);
        }

        .chat-messages {
            height: 500px;
            overflow-y: auto;
            padding: 1.5rem;
            background-color: var(--background);
        }

        .message {
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
        }

        .message.user {
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 600;
            margin: 0 1rem;
            border: 1px solid var(--border);
        }

        .message.user .message-avatar {
            background-color: var(--primary);
            color: var(--primary-foreground);
        }

        .message.assistant .message-avatar {
            background-color: var(--secondary);
            color: var(--secondary-foreground);
        }

        .message-content {
            max-width: 70%;
            padding: 1rem 1.25rem;
            border-radius: 1rem;
            position: relative;
            border: 1px solid var(--border);
        }

        .message.user .message-content {
            background-color: var(--primary);
            color: var(--primary-foreground);
            border-color: var(--primary);
        }

        .message.assistant .message-content {
            background-color: var(--card);
            color: var(--card-foreground);
        }

        .message-time {
            font-size: 0.75rem;
            color: var(--muted-foreground);
            margin-top: 0.5rem;
        }

        .chat-input-container {
            padding: 1.5rem;
            background-color: var(--card);
            border-top: 1px solid var(--border);
        }

        .chat-input {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .chat-input input {
            flex: 1;
            padding: 1rem 1.25rem;
            border: 1px solid var(--border);
            border-radius: 9999px;
            background-color: var(--background);
            color: var(--foreground);
            font-size: 1rem;
            transition: all 0.2s ease;
        }

        .chat-input input:focus {
            outline: 2px solid var(--ring);
            outline-offset: 2px;
            border-color: var(--ring);
        }

        .chat-input button {
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 9999px;
            background-color: var(--primary);
            color: var(--primary-foreground);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-height: 3rem;
        }

        .chat-input button:hover {
            background-color: var(--primary);
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .chat-input button:disabled {
            background-color: var(--muted);
            color: var(--muted-foreground);
            cursor: not-allowed;
            transform: none;
        }

        .typing-indicator {
            display: none;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }

        .typing-indicator .message-avatar {
            background-color: var(--secondary);
            color: var(--secondary-foreground);
        }

        .typing-dots {
            display: flex;
            gap: 0.25rem;
        }

        .typing-dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background-color: var(--muted-foreground);
            animation: typing 1.4s infinite;
        }

        .typing-dot:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-dot:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typing {
            0%, 60%, 100% {
                transform: translateY(0);
            }
            30% {
                transform: translateY(-0.5rem);
            }
        }

        .chat-suggestions {
            padding: 0 1.5rem 1.5rem;
            background-color: var(--card);
        }

        .suggestion-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .suggestion-chip {
            padding: 0.5rem 1rem;
            background-color: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 9999px;
            color: var(--secondary-foreground);
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .suggestion-chip:hover {
            background-color: var(--primary);
            color: var(--primary-foreground);
            border-color: var(--primary);
        }

        .clear-chat {
            text-align: center;
            padding: 1rem 1.5rem;
            background-color: var(--card);
            border-top: 1px solid var(--border);
        }

        .clear-chat button {
            background: none;
            border: 1px solid var(--destructive);
            color: var(--destructive);
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .clear-chat button:hover {
            background-color: var(--destructive);
            color: var(--destructive-foreground);
        }

        .welcome-message {
            text-align: center;
            padding: 3rem 1.5rem;
            color: var(--muted-foreground);
        }

        .welcome-message i {
            font-size: 4rem;
            color: var(--primary);
            margin-bottom: 1.5rem;
        }

        .welcome-message h3 {
            color: var(--foreground);
            margin-bottom: 1rem;
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .chat-container {
                padding: 1rem;
            }

            .message-content {
                max-width: 85%;
            }

            .chat-input {
                flex-direction: column;
                gap: 0.75rem;
            }

            .chat-input input {
                width: 100%;
            }

            .chat-input button {
                width: 100%;
                justify-content: center;
            }
        }
            --primary-dark: #1565c0;
            --primary-light: #64b5f6;
            --dark-surface-1: #121212;
            --dark-surface-2: #1e1e1e;
            --text-muted: #b0b0b0;
            --accent-color: #00acc1;
            --chat-user-bg: #2c3e50;
            --chat-assistant-bg: #34495e;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: #333333;
        }

        .chat-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .chat-wrapper {
            border: 2px solid #000000;
            border-radius: 15px;
            overflow: hidden;
            background-color: white;
        }

        .chat-header {
            background-color: #000000;
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #000000;
        }

        .chat-header h1 {
            margin: 0;
            color: white;
            font-weight: 600;
        }

        .chat-header p {
            margin: 10px 0 0 0;
            color: #cccccc;
        }

        .chat-messages {
            background-color: white;
            height: 500px;
            overflow-y: auto;
            padding: 20px;
        }

        .message {
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
        }

        .message.user {
            justify-content: flex-end;
        }

        .message.assistant {
            justify-content: flex-start;
        }

        .message-content {
            max-width: 70%;
            padding: 15px 20px;
            border-radius: 20px;
            position: relative;
            word-wrap: break-word;
        }

        .message.user .message-content {
            background-color: #000000;
            color: white;
            border-bottom-right-radius: 5px;
        }

        .message.assistant .message-content {
            background-color: #f8f9fa;
            color: #333333;
            border: 1px solid #e0e0e0;
            border-bottom-left-radius: 5px;
        }

        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin: 0 10px;
        }

        .message.user .message-avatar {
            background-color: #000000;
            color: white;
            order: 2;
        }

        .message.assistant .message-avatar {
            background-color: #666666;
            color: white;
            order: 1;
        }

        .chat-input-container {
            background-color: white;
            padding: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .chat-input-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .chat-input {
            flex-grow: 1;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 25px;
            padding: 12px 20px;
            color: #333333;
            resize: none;
            min-height: 50px;
            max-height: 120px;
        }

        .chat-input:focus {
            outline: none;
            border-color: #000000;
            box-shadow: 0 0 0 0.25rem rgba(0, 0, 0, 0.1);
        }

        .chat-input::placeholder {
            color: #999999;
            opacity: 1;
        }

        .chat-send-btn {
            background-color: #000000;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .chat-send-btn:hover {
            background-color: #333333;
            transform: scale(1.05);
        }

        .chat-send-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            transform: none;
        }

        .typing-indicator {
            display: none;
            align-items: center;
            gap: 10px;
            color: #666666;
            font-style: italic;
            margin-bottom: 15px;
        }

        .typing-dots {
            display: flex;
            gap: 3px;
        }

        .typing-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #666666;
            animation: typing 1.4s infinite ease-in-out;
        }

        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }

        @keyframes typing {
            0%, 80%, 100% { transform: scale(0); opacity: 0.5; }
            40% { transform: scale(1); opacity: 1; }
        }

        .chat-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .chat-action-btn {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 20px;
            padding: 8px 16px;
            color: #333333;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .chat-action-btn:hover {
            background-color: #000000;
            border-color: #000000;
            color: white;
        }

        .error-message {
            background-color: #dc3545;
            color: white;
            padding: 10px 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: none;
        }


        @media (max-width: 768px) {
            .chat-container {
                padding: 10px;
            }
            
            .message-content {
                max-width: 85%;
            }
            
            .chat-messages {
                height: 400px;
            }
        }
    </style>
</head>
<body>
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

    <div class="container mt-4">
        <div class="chat-container">
            <div class="chat-actions">
                <button class="btn chat-action-btn" onclick="sendQuickMessage('How do I run a quick checkout?')">
                    Checkout Flow
                </button>
                <button class="btn chat-action-btn" onclick="sendQuickMessage('How do I keep customer profiles organized?')">
                    Customer Management
                </button>
                <button class="btn chat-action-btn" onclick="sendQuickMessage('What reports can I generate in the admin?')">
                    Reporting
                </button>
                <button class="btn chat-action-btn" onclick="sendQuickMessage('Can I connect Your App Your Data to other tools?')">
                    Integrations
                </button>
                <button class="btn chat-action-btn" onclick="clearChat()">
                    <i class="bi bi-trash"></i> Clear Chat
                </button>
            </div>

            <div class="error-message" id="errorMessage"></div>

            <div class="chat-wrapper">
                <div class="chat-header">
                    <h1><i class="bi bi-robot"></i> AI Chat Assistant</h1>
                    <p>Ask me anything about Your App Your Data—POS workflows, CRM automation, or deployment tips!</p>
                </div>

                <div class="chat-messages" id="chatMessages">
                    <div class="message assistant">
                        <div class="message-avatar">
                            <i class="bi bi-robot"></i>
                        </div>
                        <div class="message-content">
                            Hello! I'm your AI assistant for Your App Your Data. I'm here to help you navigate checkout flows, customer journeys, analytics, and integrations. How can I assist you today?
                        </div>
                    </div>
                </div>

                <div class="typing-indicator" id="typingIndicator">
                    <div class="message-avatar">
                        <i class="bi bi-robot"></i>
                    </div>
                    <span>AI is typing</span>
                    <div class="typing-dots">
                        <div class="typing-dot"></div>
                        <div class="typing-dot"></div>
                        <div class="typing-dot"></div>
                    </div>
                </div>

                <div class="chat-input-container">
                    <form class="chat-input-form" id="chatForm">
                        <textarea 
                            class="form-control chat-input" 
                            id="messageInput" 
                            placeholder="Type your message here..." 
                            rows="1"
                            required
                        ></textarea>
                        <button type="submit" class="btn chat-send-btn" id="sendButton">
                            <i class="bi bi-send"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const chatMessages = document.getElementById('chatMessages');
        const messageInput = document.getElementById('messageInput');
        const chatForm = document.getElementById('chatForm');
        const sendButton = document.getElementById('sendButton');
        const typingIndicator = document.getElementById('typingIndicator');
        const errorMessage = document.getElementById('errorMessage');

        // Auto-resize textarea
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        // Handle Enter key (send message) and Shift+Enter (new line)
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                chatForm.dispatchEvent(new Event('submit'));
            }
        });

        // Handle form submission
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const message = messageInput.value.trim();
            if (message) {
                sendMessage(message);
            }
        });

        function sendMessage(message) {
            // Add user message to chat
            addMessage('user', message);
            
            // Clear input and disable send button
            messageInput.value = '';
            messageInput.style.height = 'auto';
            sendButton.disabled = true;
            
            // Show typing indicator
            showTypingIndicator();
            
            // Hide any previous error messages
            hideError();
            
            // Send message to backend
            fetch('chat_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                hideTypingIndicator();
                sendButton.disabled = false;
                
                if (data.success) {
                    addMessage('assistant', data.response);
                } else {
                    showError(data.error || 'An error occurred while processing your message.');
                }
            })
            .catch(error => {
                hideTypingIndicator();
                sendButton.disabled = false;
                showError('Network error. Please check your connection and try again.');
                console.error('Error:', error);
            });
        }

        function sendQuickMessage(message) {
            sendMessage(message);
        }

        function addMessage(role, content) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${role}`;
            
            const avatar = document.createElement('div');
            avatar.className = 'message-avatar';
            avatar.innerHTML = role === 'user' ? '<i class="bi bi-person-fill"></i>' : '<i class="bi bi-robot"></i>';
            
            const messageContent = document.createElement('div');
            messageContent.className = 'message-content';
            messageContent.textContent = content;
            
            messageDiv.appendChild(avatar);
            messageDiv.appendChild(messageContent);
            
            chatMessages.appendChild(messageDiv);
            scrollToBottom();
        }

        function showTypingIndicator() {
            typingIndicator.style.display = 'flex';
            scrollToBottom();
        }

        function hideTypingIndicator() {
            typingIndicator.style.display = 'none';
        }

        function showError(message) {
            errorMessage.textContent = message;
            errorMessage.style.display = 'block';
        }

        function hideError() {
            errorMessage.style.display = 'none';
        }

        function scrollToBottom() {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function clearChat() {
            if (confirm('Are you sure you want to clear the chat history?')) {
                fetch('chat_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'clear_chat'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Clear chat messages except the initial greeting
                        chatMessages.innerHTML = `
                            <div class="message assistant">
                                <div class="message-avatar">
                                    <i class="bi bi-robot"></i>
                                </div>
                                <div class="message-content">
                                    Hello! I'm your AI assistant for Your App Your Data. I'm here to help you navigate checkout flows, customer journeys, analytics, and integrations. How can I assist you today?
                                </div>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error clearing chat:', error);
                });
            }
        }

        // Focus on input when page loads
        document.addEventListener('DOMContentLoaded', function() {
            messageInput.focus();
        });
    </script>

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
                        <?php foreach ($nav_categories as $category): ?>
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
</body>
</html> 