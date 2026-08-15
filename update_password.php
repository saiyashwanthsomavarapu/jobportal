<?php
$host = 'localhost';
$db   = 'tsqwpswmrm';
$user = 'tsqwpswmrm';
$pass = 'hUAQgP99nS';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$pdo = new PDO($dsn, $user, $pass);

// Generate password hash for 'admin123'
$password = 'admin123';
$hash = password_hash($password, PASSWORD_BCRYPT);

echo "Password hash for 'admin123': $hash\n";

// Update the password
$stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE email = ?");
$stmt->execute([$hash, 'admin@accelonconsulting.com']);

echo "Password updated successfully!\n";

// Verify
$stmt = $pdo->prepare("SELECT password FROM admin_users WHERE email = ?");
$stmt->execute(['admin@accelonconsulting.com']);
$storedHash = $stmt->fetchColumn();

if (password_verify('admin123', $storedHash)) {
    echo "Password verification: SUCCESS!\n";
} else {
    echo "Password verification: FAILED!\n";
}
