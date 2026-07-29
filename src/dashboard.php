<?php
session_start();

// Check if logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// LFI vulnerability in authenticated area
$theme = isset($_GET['theme']) ? $_GET['theme'] : 'default';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
        .container { max-width: 800px; margin: 0 auto; }
        .vulnerability { background: #f5f5f5; padding: 15px; margin-bottom: 15px; border-left: 4px solid #e74c3c; }
    </style>
    <?php
    // Vulnerable inclusion of theme file
    if ($theme !== 'default') {
        echo "<!-- Including theme: $theme -->\n";
        include("themes/$theme");
    }
    ?>
</head>
<body>
    <div class="container">
        <h1>Dashboard</h1>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>

        <div class="vulnerability">
            <h3>Theme Selector (Vulnerable):</h3>
            <ul>
                <li><a href="?theme=blue.css">Blue Theme</a></li>
                <li><a href="?theme=dark.css">Dark Theme</a></li>
                <li><a href="?theme=../../../../etc/passwd">Try Path Traversal</a></li>
            </ul>
        </div>

        <p><a href="logout.php">Logout</a></p>
    </div>
</body>
</html>
