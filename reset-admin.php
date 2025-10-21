<?php
require_once __DIR__ . '/applications/config.php';

if (!isset($_POST['confirm'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Reset Admin Password</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background: #f8f9fa; padding: 40px 0; }
            .card { max-width: 500px; margin: 0 auto; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); }
            .alert-warning { background-color: #fff3cd; border-color: #ffe69c; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="card-header bg-warning">
                    <h3 class="mb-0">⚠️ Reset Admin Password</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> This will reset the admin password to <code>admin123</code>
                    </div>
                    <p>Click the button below to reset the admin account password:</p>
                    <form method="post">
                        <button type="submit" name="confirm" value="yes" class="btn btn-danger w-100">
                            Reset Admin Password to: admin123
                        </button>
                    </form>
                    <hr>
                    <p class="text-muted small">After resetting, you can log in with:</p>
                    <ul class="text-muted small">
                        <li><strong>Username:</strong> admin</li>
                        <li><strong>Password:</strong> admin123</li>
                    </ul>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
} else {
    // Reset the password
    $new_password = 'admin123';
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $sql = "UPDATE users SET password_hash = '$hashed_password' WHERE username = 'admin'";

    if ($conn->query($sql)) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Success</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { background: #f8f9fa; padding: 40px 0; }
                .card { max-width: 500px; margin: 0 auto; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h3 class="mb-0">✓ Success!</h3>
                    </div>
                    <div class="card-body">
                        <p class="mb-4">Admin password has been reset successfully.</p>
                        <p><strong>Login Credentials:</strong></p>
                        <ul>
                            <li><strong>Username:</strong> admin</li>
                            <li><strong>Password:</strong> admin123</li>
                        </ul>
                        <p class="mt-4">
                            <a href="login.php" class="btn btn-primary">Go to Login Page</a>
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
