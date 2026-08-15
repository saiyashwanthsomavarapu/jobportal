<?php
require_once __DIR__ . '/admin/config.php';

// Create a new admin user with a simple password
$email = 'test@jobportal.com';
$password = 'test123';
$name = 'Test Admin';
$role = 'superadmin';

$hash = password_hash($password, PASSWORD_BCRYPT);

echo "Creating new admin user:\n";
echo "Email: $email\n";
echo "Password: $password\n";
echo "Name: $name\n";
echo "Role: $role\n";
echo "Hash: $hash\n\n";

$stmt = db()->prepare("INSERT INTO admin_users (name, email, password, role, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
$stmt->execute([$name, $email, $hash]);

echo "✅ Admin user created successfully!\n";
echo "You can now login with:\n";
echo "Email: $email\n";
echo "Password: $password\n";
