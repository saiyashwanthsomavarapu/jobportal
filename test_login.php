<?php
require_once __DIR__ . '/admin/config.php';

// Test credentials
$email = 'admin@accelonconsulting.com';
$password = 'admin123';

echo "Testing login for: $email\n";

// Fetch user
$stmt = db()->prepare("SELECT * FROM admin_users WHERE email = ? AND is_active = 1");
$stmt->execute([$email]);
$admin = $stmt->fetch();

if (!$admin) {
    echo "ERROR: User not found or inactive\n";
    exit;
}

echo "User found: " . $admin['name'] . "\n";
echo "Role: " . $admin['role'] . "\n";
echo "Is Active: " . $admin['is_active'] . "\n";
echo "Stored Hash: " . substr($admin['password'], 0, 30) . "...\n";

// Verify password
if (password_verify($password, $admin['password'])) {
    echo "✅ PASSWORD VERIFICATION: SUCCESS!\n";
} else {
    echo "❌ PASSWORD VERIFICATION: FAILED!\n";
    
    // Test with other common passwords
    $testPasswords = ['password', 'admin', '123456', 'admin123'];
    echo "\nTesting other passwords:\n";
    foreach ($testPasswords as $testPwd) {
        if (password_verify($testPwd, $admin['password'])) {
            echo "✅ Password is: $testPwd\n";
        }
    }
}
