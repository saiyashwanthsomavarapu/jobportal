<?php
// debug_login.php - Debug login form submission
require_once __DIR__ . '/admin/config.php';

session_start();

echo "<h2>Login Debug Information</h2>";

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>POST Data Received:</h3>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    echo "<h3>Credentials:</h3>";
    echo "Email: '$email'<br>";
    echo "Password: '$password'<br>";

    if ($email && $password) {
        $stmt = db()->prepare("SELECT * FROM admin_users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        echo "<h3>Database Query Result:</h3>";
        if ($admin) {
            echo "User found: " . $admin['name'] . "<br>";
            echo "Email: " . $admin['email'] . "<br>";
            echo "Role: " . $admin['role'] . "<br>";
            echo "Is Active: " . $admin['is_active'] . "<br>";
            echo "Password Hash: " . substr($admin['password'], 0, 30) . "...<br>";

            echo "<h3>Password Verification:</h3>";
            if (password_verify($password, $admin['password'])) {
                echo "✅ PASSWORD VERIFICATION: SUCCESS!<br>";
                echo "Login would be successful!";
            } else {
                echo "❌ PASSWORD VERIFICATION: FAILED!<br>";
                
                // Test with different passwords
                $testPasswords = ['admin123', 'password', 'admin'];
                echo "<br>Testing other passwords:<br>";
                foreach ($testPasswords as $testPwd) {
                    if (password_verify($testPwd, $admin['password'])) {
                        echo "✅ Password matches: '$testPwd'<br>";
                    }
                }
            }
        } else {
            echo "❌ User not found or inactive!";
        }
    } else {
        echo "❌ Empty email or password!";
    }
} else {
    echo "<p>Submit the login form to see debug information.</p>";
}
?>

<h3>Test Login Form:</h3>
<form method="POST">
    <label>Email: <input type="email" name="email" value="admin@accelonconsulting.com" required></label><br><br>
    <label>Password: <input type="password" name="password" value="admin123" required></label><br><br>
    <button type="submit">Test Login</button>
</form>

<h3>Session Info:</h3>
<pre><?php print_r($_SESSION); ?></pre>
