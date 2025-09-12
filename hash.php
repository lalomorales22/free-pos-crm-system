<?php
    $password = 'NORTHpark22$$'; // Replace with your actual password
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    if ($hash === false) {
        echo "Failed to generate hash.";
    } else {
        echo "Password: " . htmlspecialchars($password) . "\n";
        echo "Generated Hash: " . htmlspecialchars($hash) . "\n";
    }
    ?>
